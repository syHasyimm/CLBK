<?php

namespace Tests\Feature;

use App\Actions\Finance\ManualCash;
use App\Actions\Finance\PostFinance;
use App\Actions\Weighings\SaveDirectWeighing;
use App\Enums\FinanceKind;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\DailyExpense;
use App\Models\ExpenseCategory;
use App\Models\LoadingWorker;
use App\Models\PayrollPayment;
use App\Models\PriceSchedule;
use App\Models\SaleReceipt;
use App\Models\SortSale;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BusinessCash;
use App\Services\CashLedger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class SingleCashTest extends TestCase
{
    use DatabaseMigrations;

    private User $manager;

    private User $cashier;

    private User $driver;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
            DB::disconnect();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 03:00:00', 'UTC'));
        $this->manager = User::factory()->manager()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->driver = User::factory()->create();
    }

    private function cash(string $code = 'KAS-UTAMA', bool $active = true, string $type = 'cash'): CashAccount
    {
        return CashAccount::create(['code' => $code, 'name' => $code === 'KAS-UTAMA' ? 'Kas Utama' : $code, 'type' => $type, 'is_active' => $active]);
    }

    private function key(array $data): array
    {
        return $data + ['idempotency_key' => (string) Str::uuid()];
    }

    private function capital(int $amount = 1000000): array
    {
        $data = $this->key(['kind' => 'capital', 'amount_idr' => $amount, 'occurred_at' => '2026-10-09T10:00:00', 'reason' => 'Modal usaha']);
        $this->actingAs($this->manager)->post(route('cash.store'), $data)->assertSessionHasNoErrors();

        return $data;
    }

    private function legacyEntry(CashAccount $account, int $amount, array $attributes = []): int
    {
        return DB::table('cash_entries')->insertGetId($attributes + [
            'cash_account_id' => $account->id, 'number' => 'KAS-LAMA-'.Str::uuid(), 'direction' => 'in',
            'kind' => 'capital', 'amount_idr' => $amount, 'occurred_at' => now(), 'business_date' => '2026-10-09',
            'reason' => 'Catatan kas lama', 'created_by' => $this->manager->id,
        ]);
    }

    private function sortSale(): SortSale
    {
        $vehicle = Vehicle::firstOrCreate(['code' => 'M1'], ['plate_number' => 'BK SINGLE', 'is_active' => true]);
        $this->actingAs($this->manager)->post(route('sort-sales.store'), $this->key([
            'vehicle_id' => $vehicle->id, 'sold_kg' => '10', 'sell_price_snapshot_idr' => 1000,
        ]))->assertSessionHasNoErrors();

        return SortSale::latest('id')->firstOrFail();
    }

    public function test_primary_cash_is_preferred_over_older_accounts_and_inactive_primary_never_falls_back(): void
    {
        $this->cash('BANK-LAMA', type: 'bank');
        $this->cash('KAS-LAMA');
        $primary = $this->cash();
        $this->assertSame($primary->id, app(BusinessCash::class)->account()->id);
        $primary->update(['is_active' => false]);
        $this->assertSame($primary->id, app(BusinessCash::class)->account()->id);
        try {
            app(BusinessCash::class)->forPosting();
            $this->fail('An inactive primary must not redirect posting to another account');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('cash_account_id', $error->errors());
        }
    }

    public function test_without_a_primary_code_the_first_cash_account_is_used_instead_of_bank_accounts(): void
    {
        $this->cash('BANK-LAMA', type: 'bank');
        $first = $this->cash('KAS-LAMA');
        $this->cash('KAS-LAIN');
        $this->assertSame($first->id, app(BusinessCash::class)->account()->id);
        $this->capital(5000);
        $this->assertSame(5000, app(CashLedger::class)->balance($first->id));
        $this->assertDatabaseCount('cash_entries', 1);
    }

    public function test_shared_cash_ledger_and_current_balances_expose_only_primary_cash_to_staff(): void
    {
        $legacy = $this->cash('KAS-LAMA');
        $primary = $this->cash();
        $this->legacyEntry($legacy, 7000);
        $this->capital(5000);
        $original = DB::table('cash_entries')->orderBy('id')->get()->map(fn ($entry) => (array) $entry)->all();
        $shared = ['id' => $primary->id, 'name' => 'Kas Utama', 'is_active' => true, 'balance_idr' => 5000];
        foreach ([$this->manager, $this->cashier] as $actor) {
            $this->actingAs($actor)->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->where('cash', $shared));
            $this->get(route('cash.index', ['cash_account_id' => $legacy->id]))->assertInertia(fn (Assert $page) => $page
                ->where('account.id', $primary->id)->where('account.balance_idr', 5000)
                ->missing('filters.cash_account_id')->has('entries.data', 1)
                ->where('entries.data.0.cash_account_id', $primary->id));
            $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
                ->where('cash', $shared)->where('summary.balances.cash_idr', 5000));
            $this->get(route('reports.index'))->assertInertia(fn (Assert $page) => $page
                ->where('summary.balances.cash_idr', 5000)->has('summary.cashflow.accounts', 1)
                ->where('summary.cashflow.accounts.0.id', $primary->id));
        }
        $this->actingAs($this->driver)->get(route('profile.edit'))->assertInertia(fn (Assert $page) => $page->where('cash', null));
        $this->get(route('cash.index'))->assertForbidden();
        auth()->logout();
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('cash', null));
        $this->assertSame($original, DB::table('cash_entries')->orderBy('id')->get()->map(fn ($entry) => (array) $entry)->all());
        $this->assertDatabaseCount('cash_accounts', 2);
    }

    public function test_cash_reference_endpoints_return_only_the_primary_account(): void
    {
        $primary = $this->cash();
        $this->cash('BANK-LAMA', type: 'bank');
        $this->cash('KAS-LAMA');
        foreach ([$this->manager, $this->cashier] as $actor) {
            $this->actingAs($actor)->getJson(route('references.index', 'cash-accounts'))
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $primary->id);
            foreach (['accounts', 'ledger-accounts'] as $kind) {
                $this->getJson(route('finance-references.index', ['referenceKind' => $kind]))
                    ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $primary->id);
                $this->getJson(route('finance-references.index', ['referenceKind' => $kind, 'q' => 'LAMA']))
                    ->assertOk()->assertJsonCount(0, 'data');
            }
        }
        $this->actingAs($this->driver)->getJson(route('references.index', 'cash-accounts'))->assertForbidden();
        $this->getJson(route('finance-references.index', ['referenceKind' => 'accounts']))->assertForbidden();
    }

    public function test_all_new_finance_documents_use_primary_cash_when_account_is_omitted_for_cash_and_transfer(): void
    {
        $primary = $this->cash();
        $legacy = $this->cash('BANK-LAMA', type: 'bank');
        $capital = $this->capital(10000000);
        $this->post(route('cash.store'), $capital + ['cash_account_id' => $primary->id])->assertSessionHasNoErrors();
        $customer = Customer::create(['code' => 'K1', 'name' => 'Konsumen', 'is_active' => true]);
        $worker = LoadingWorker::create(['code' => 'A1', 'name' => 'Anggota', 'is_active' => true]);
        $vehicle = Vehicle::create(['code' => 'M1', 'plate_number' => 'BK SINGLE', 'is_active' => true]);
        $price = PriceSchedule::create(['business_date' => '2026-10-09', 'effective_from' => '2026-10-08 17:00:00',
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
        $ticket = app(SaveDirectWeighing::class)->handle($this->manager, [
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'tbs_kg' => '1000', 'sort_kg' => '0',
            'worker_ids' => [$worker->id], 'price_schedule_id' => $price->id, 'idempotency_key' => (string) Str::uuid(),
        ]);
        $category = ExpenseCategory::create(['code' => 'BBM', 'name' => 'BBM', 'is_active' => true]);
        foreach (['cash', 'bank_transfer'] as $index => $method) {
            $paymentCustomer = $customer;
            $paymentTicket = $ticket;
            if ($index > 0) {
                $paymentCustomer = Customer::create(['code' => 'K2', 'name' => 'Konsumen Kedua']);
                $paymentTicket = app(SaveDirectWeighing::class)->handle($this->manager, [
                    'customer_id' => $paymentCustomer->id, 'vehicle_id' => $vehicle->id, 'tbs_kg' => '1000', 'sort_kg' => '0',
                    'worker_ids' => [$worker->id], 'price_schedule_id' => $price->id, 'idempotency_key' => (string) Str::uuid(),
                ]);
            }
            $base = ['method' => $method, 'occurred_at' => '2026-10-09T10:00:00', 'amount_idr' => 1000];
            if ($method === 'bank_transfer') {
                $base['reference'] = 'TF-001';
            }
            $sale = $this->sortSale();
            $payloads = [
                ['customer-payments.store', array_replace($base, ['recipient_id' => $paymentCustomer->id, 'selected_ids' => [$paymentTicket->id], 'expected_remaining_idr' => 3000000, 'amount_idr' => 3000000])],
                ['payroll-payments.store', $base + ['recipient_id' => $worker->id, 'selected_ids' => [$ticket->loaders()->sole()->id],
                    'expected_remaining_idr' => 100000 - $index * 1000, 'period_start' => '2026-10-09', 'period_end' => '2026-10-09']],
                ['daily-expenses.store', $base + ['expense_category_id' => $category->id, 'description' => 'BBM']],
                ['sale-receipts.store', array_replace($base, ['sale_kind' => 'sort', 'sale_id' => $sale->id, 'amount_idr' => 10000, 'expected_remaining_idr' => 10000])],
            ];
            foreach ($payloads as [$route, $payload]) {
                $this->actingAs($this->cashier)->post(route($route), $this->key($payload))->assertSessionHasNoErrors();
            }
        }
        foreach ([CustomerPayment::class, PayrollPayment::class, DailyExpense::class, SaleReceipt::class] as $model) {
            $this->assertSame([$primary->id], $model::query()->distinct()->pluck('cash_account_id')->all());
            $this->assertSame(['cash', 'bank_transfer'], $model::query()->orderBy('id')->pluck('method')->all());
            $this->assertSame('TF-001', $model::query()->latest('id')->firstOrFail()->reference);
            $this->assertDatabaseCount((new $model)->getTable(), 2);
        }
        $this->assertSame(4016000, app(CashLedger::class)->balance($primary->id));
        $this->assertSame(0, app(CashLedger::class)->balance($legacy->id));
        $this->assertDatabaseCount('cash_entries', 9);
    }

    public function test_forged_secondary_accounts_are_rejected_inside_http_and_actions_without_partial_writes(): void
    {
        $primary = $this->cash();
        $secondary = $this->cash('BANK-LAMA', type: 'bank');
        $this->capital(5000);
        $category = ExpenseCategory::create(['code' => 'BBM', 'name' => 'BBM', 'is_active' => true]);
        $expense = $this->key(['cash_account_id' => $secondary->id, 'method' => 'bank_transfer', 'reference' => 'TF-LAMA',
            'occurred_at' => '2026-10-09T10:00:00', 'amount_idr' => 1000, 'expense_category_id' => $category->id, 'description' => 'BBM']);
        $manual = $this->key(['cash_account_id' => $secondary->id, 'kind' => 'capital', 'amount_idr' => 5000,
            'occurred_at' => '2026-10-09T10:00:00', 'reason' => 'Modal ke akun lama']);
        $this->actingAs($this->cashier)->post(route('daily-expenses.store'), $expense)->assertSessionHasErrors('cash_account_id');
        $this->actingAs($this->manager)->post(route('cash.store'), $manual)->assertSessionHasErrors('cash_account_id');
        foreach ([
            fn () => app(PostFinance::class)->handle($this->cashier, FinanceKind::Expense, $expense),
            fn () => app(ManualCash::class)->post($this->manager, $manual),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Posting directly must not bypass the single cash restriction');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('cash_account_id', $error->errors());
            }
        }
        $this->assertSame(5000, app(CashLedger::class)->balance($primary->id));
        $this->assertSame(0, app(CashLedger::class)->balance($secondary->id));
        $this->assertDatabaseCount('cash_entries', 1);
        $this->assertDatabaseCount('daily_expenses', 0);
        $this->assertDatabaseCount('profit_entries', 0);
        $this->assertDatabaseCount('idempotency_requests', 1);
    }

    public function test_inactive_primary_blocks_new_posts_even_when_a_secondary_has_funds(): void
    {
        $primary = $this->cash(active: false);
        $secondary = $this->cash('KAS-LAMA');
        $this->legacyEntry($secondary, 10000);
        $category = ExpenseCategory::create(['code' => 'BBM', 'name' => 'BBM', 'is_active' => true]);
        $this->actingAs($this->cashier)->post(route('daily-expenses.store'), $this->key([
            'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00', 'amount_idr' => 1000,
            'expense_category_id' => $category->id, 'description' => 'BBM',
        ]))->assertSessionHasErrors('cash_account_id');
        $this->actingAs($this->manager)->post(route('cash.store'), $this->key([
            'kind' => 'capital', 'occurred_at' => '2026-10-09T10:00:00', 'amount_idr' => 1000, 'reason' => 'Modal',
        ]))->assertSessionHasErrors('cash_account_id');
        $this->get(route('cash.index'))->assertInertia(fn (Assert $page) => $page
            ->where('cash.id', $primary->id)->where('cash.is_active', false)->where('account.id', $primary->id)->has('entries.data', 0));
        $this->assertSame(10000, app(CashLedger::class)->balance($secondary->id));
        $this->assertDatabaseCount('cash_entries', 1);
        $this->assertDatabaseCount('daily_expenses', 0);
    }

    public function test_historical_secondary_account_vouchers_and_manual_entries_can_be_reversed_on_the_original_account(): void
    {
        $primary = $this->cash();
        $legacy = $this->cash('KAS-LAMA');
        $this->capital(5000);
        $sale = $this->sortSale();
        $receipt = SaleReceipt::create(['number' => 'TERIMA-LAMA', 'sort_sale_id' => $sale->id, 'cash_account_id' => $legacy->id,
            'amount_idr' => 10000, 'method' => 'cash', 'status' => 'posted', 'occurred_at' => now(), 'posted_at' => now(),
            'business_date' => '2026-10-09', 'created_by' => $this->manager->id, 'version' => 1]);
        $receiptEntry = $this->legacyEntry($legacy, 10000, ['kind' => 'receipt', 'sale_receipt_id' => $receipt->id]);
        $manualEntry = $this->legacyEntry($legacy, 5000);
        $legacy->update(['is_active' => false]);
        $original = DB::table('cash_entries')->whereIn('id', [$receiptEntry, $manualEntry])->orderBy('id')->get()->map(fn ($entry) => (array) $entry)->all();
        $this->actingAs($this->manager)->post(route('finance.reverse', ['kind' => 'receipt', 'document' => $receipt->id]), $this->key([
            'version' => $receipt->version, 'reason' => 'Koreksi penerimaan lama', 'return_reference' => 'Dana dikembalikan',
        ]))->assertSessionHasNoErrors();
        $this->post(route('cash.reverse', $manualEntry), $this->key([
            'reason' => 'Koreksi modal lama', 'return_reference' => 'Dana dikembalikan',
        ]))->assertSessionHasNoErrors();
        $this->assertSame($original, DB::table('cash_entries')->whereIn('id', [$receiptEntry, $manualEntry])->orderBy('id')->get()->map(fn ($entry) => (array) $entry)->all());
        $this->assertSame($legacy->id, $receipt->fresh()->cash_account_id);
        $this->assertSame('reversed', $receipt->fresh()->status);
        $this->assertSame(0, app(CashLedger::class)->balance($legacy->id));
        $this->assertSame(5000, app(CashLedger::class)->balance($primary->id));
        $this->assertDatabaseHas('cash_entries', ['reversal_of_id' => $receiptEntry, 'cash_account_id' => $legacy->id, 'amount_idr' => 10000]);
        $this->assertDatabaseHas('cash_entries', ['reversal_of_id' => $manualEntry, 'cash_account_id' => $legacy->id, 'amount_idr' => 5000]);
        $this->assertDatabaseCount('cash_accounts', 2);
        $this->assertDatabaseCount('cash_entries', 5);
    }
}
