<?php

namespace Tests\Feature;

use App\Actions\Finance\ManualCash;
use App\Actions\Finance\PostFinance;
use App\Actions\Finance\ReverseFinance;
use App\Actions\Sales\ReverseSale;
use App\Actions\Sales\SaveDirectSale;
use App\Actions\Weighings\SaveDirectWeighing;
use App\Actions\Weighings\VoidWeighing;
use App\Enums\FinanceKind;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\User;
use App\Models\Vehicle;
use App\Queries\OperationalReport;
use App\Support\BusinessClock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\DatabaseMigrations;
use Tests\Concerns\LegacyCustomerPayments;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use DatabaseMigrations;
    use LegacyCustomerPayments;

    public function runDatabaseMigrations(): void
    {
        $this->refreshTestDatabase();
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
            DB::disconnect();
        });
    }

    private User $manager;

    private User $cashier;

    private User $driver;

    private Vehicle $vehicle;

    private Customer $customer;

    private Factory $factory;

    private CashAccount $cash;

    private ExpenseCategory $category;

    private PriceSchedule $price;

    private FactoryPriceSchedule $factoryPrice;

    private array $workers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 03:00:00', 'UTC'));
        $this->manager = User::factory()->manager()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->driver = User::factory()->create();
        $this->vehicle = Vehicle::create(['code' => 'M', 'plate_number' => 'BK LAPORAN', 'is_active' => true]);
        $this->driver->update(['assigned_vehicle_id' => $this->vehicle->id]);
        $this->customer = Customer::create(['code' => 'K', 'name' => 'Konsumen Laporan', 'is_active' => true]);
        $this->factory = Factory::create(['code' => 'P', 'name' => 'Pabrik Laporan', 'is_active' => true]);
        $this->workers = collect(range(1, 3))->map(fn ($i) => LoadingWorker::create(['code' => 'A'.$i, 'name' => 'Anggota '.$i, 'is_active' => true])->id)->all();
        $this->cash = CashAccount::create(['code' => 'KAS', 'name' => 'Kas laporan', 'type' => 'cash', 'is_active' => true]);
        $this->category = ExpenseCategory::create(['code' => 'BBM', 'name' => 'BBM', 'is_active' => true]);
        $this->price = PriceSchedule::create(['business_date' => '2026-10-09', 'effective_from' => '2026-10-08 17:00:00', 'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
        $this->factoryPrice = FactoryPriceSchedule::create(['factory_id' => $this->factory->id, 'effective_from' => '2026-10-08 17:00:00', 'tbs_sell_price_idr' => 3500, 'published_by' => $this->manager->id]);
    }

    private function key(array $data): array
    {
        return $data + ['idempotency_key' => (string) Str::uuid()];
    }

    private function sale(string $kind = 'factory', array $extra = [], ?User $actor = null)
    {
        return app(SaveDirectSale::class)->handle($actor ?? $this->driver, $kind, $this->key(array_replace(['vehicle_id' => $this->vehicle->id, 'vehicle_assignment_version' => 1]
            + ($kind === 'factory' ? ['factory_id' => $this->factory->id, 'factory_price_schedule_id' => $this->factoryPrice->id, 'accepted_kg' => '900', 'deduction_idr' => 0]
                : ['sold_kg' => '100', 'sell_price_snapshot_idr' => 2000, 'buyer_name' => 'Pembeli Sortir']), $extra)));
    }

    private function finance(FinanceKind $kind, array $data)
    {
        return app(PostFinance::class)->handle($this->cashier, $kind, $this->key($data + ['cash_account_id' => $this->cash->id, 'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00']));
    }

    private function fixture(): array
    {
        $weighing = app(SaveDirectWeighing::class)->handle($this->driver, $this->key(['customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
            'vehicle_assignment_version' => 1,
            'tbs_kg' => '1000', 'sort_kg' => '100', 'worker_ids' => $this->workers, 'price_schedule_id' => $this->price->id]));
        $factory = $this->sale();
        $sort = $this->sale('sort');
        foreach ([$factory, $sort] as $sale) {
            $this->finance(FinanceKind::Receipt, ['sale_kind' => $sale->kind(), 'sale_id' => $sale->id,
                'amount_idr' => $sale->kind() === 'factory' ? 3150000 : 50000, 'expected_remaining_idr' => $sale->revenue_idr]);
        }
        $customerPayment = $this->legacyCustomerPayment($this->cashier, $this->cash, [$weighing->id => 1000000]);
        $loader = $weighing->loaders()->where('worker_id', $this->workers[0])->firstOrFail();
        $payrollPayment = $this->finance(FinanceKind::Payroll, ['recipient_id' => $this->workers[0], 'selected_ids' => [$loader->id], 'period_start' => '2026-10-09', 'period_end' => '2026-10-09', 'amount_idr' => 10000, 'expected_remaining_idr' => $loader->share_amount_idr]);
        $expense = $this->finance(FinanceKind::Expense, ['expense_category_id' => $this->category->id, 'description' => 'BBM mobil', 'amount_idr' => 50000]);

        return compact('weighing', 'factory', 'sort', 'customerPayment', 'payrollPayment', 'expense');
    }

    private function page(string $view = 'summary', array $extra = []): array
    {
        $response = $this->get(route('reports.index', $extra + ['view' => $view, 'from' => '2026-10-09', 'to' => '2026-10-09']))->assertOk();
        $response->assertInertia(fn (Assert $p) => $p->component('Reports/Index'));

        return $response->inertiaProps();
    }

    private function csv(string $view, array $extra = []): array
    {
        $response = $this->get(route('reports.export', $extra + ['view' => $view, 'from' => '2026-10-09', 'to' => '2026-10-09']))->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, substr($content, 3));
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, null, ';', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    public function test_dashboard_summary_reconciles_entitlements_receivables_cash_and_period_difference(): void
    {
        $this->fixture();
        $this->actingAs($this->manager);
        $summary = $this->page()['summary'];
        $this->assertSame(-10000, $summary['totals']['period_difference_idr']);
        $this->assertSame(3350000, $summary['totals']['revenue_idr']);
        $this->assertSame(3200000, $summary['totals']['purchase_idr']);
        $this->assertSame(110000, $summary['totals']['loading_idr']);
        $this->assertSame(['customer_debt_idr' => 2200000, 'sale_receivable_idr' => 150000, 'cash_idr' => 2140000, 'payroll_debt_idr' => 100000], $summary['balances']);
        $this->assertSame(['opening_idr' => 0, 'incoming_idr' => 3200000, 'outgoing_idr' => 1060000, 'closing_idr' => 2140000], $summary['cashflow']['totals']);
        $this->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p->where('summary', $summary));
        $this->assertSame(3350000, $this->page('factory')['report']['totals']['revenue_idr'] + $this->page('sort')['report']['totals']['revenue_idr']);
        $this->assertSame(3200000, $this->page('receipts')['report']['totals']['amount_idr']);
        $this->assertSame(150000, $this->page('receivables')['report']['totals']['remaining_idr']);
        $this->assertSame(100000, $this->page('payroll')['report']['totals']['remaining_idr']);
    }

    public function test_all_report_csv_totals_and_source_links_match_the_screen(): void
    {
        $this->fixture();
        $this->actingAs($this->manager);
        foreach (array_keys(OperationalReport::TITLES) as $view) {
            $page = $this->page($view);
            $csv = $this->csv($view);
            $headers = array_shift($csv);
            $printed = $this->get(route('reports.print', ['view' => $view]))->assertOk();
            if ($view === 'summary') {
                $this->assertSame('TOTAL', $csv[array_key_last($csv)][0]);
                $this->assertSame('3350000', $csv[array_key_last($csv)][array_search('Pendapatan (Rp)', $headers)]);
                $this->assertSame('-10000', $csv[array_key_last($csv)][array_search('Keuntungan bersih periode (Rp)', $headers)]);
                $printed->assertSee('Keuntungan bersih periode (Rp)')->assertSee('Rp -10.000')
                    ->assertSee('Rp 3.350.000')->assertSee('Rp 100.000')->assertSee('Rp 2.140.000');

                continue;
            }
            $report = $page['report'];
            if ($report['totals'] !== []) {
                $total = array_pop($csv);
                $this->assertSame('TOTAL', $total[0]);
                foreach ($report['columns'] as $column) {
                    if ($column['sum']) {
                        $value = (float) $total[array_search($column['label'], $headers)];
                        $this->assertSame($report['totals'][$column['key']], (int) round($value * ($column['type'] === 'weight' ? 1000 : 1)));
                        $amount = $report['totals'][$column['key']];
                        $printed->assertSee($column['type'] === 'money' ? 'Rp '.number_format($amount, 0, ',', '.') : ($column['type'] === 'weight' ? number_format($amount / 1000, 3, ',', '.').' kg' : (string) $amount));
                    }
                }
            }
            $this->assertCount($report['rows']['total'], $csv);
            foreach ($report['rows']['data'] as $i => $row) {
                if ($row['source']) {
                    $this->assertSame(route($row['source']['route'], $row['source']['params']), $csv[$i][array_key_last($headers)]);
                }
            }
        }
    }

    public function test_csv_and_print_include_all_pages_with_exact_fractional_weights(): void
    {
        $first = null;
        foreach (range(1, 55) as $i) {
            $sale = $this->sale('sort', ['sold_kg' => '1.001', 'sell_price_snapshot_idr' => 1000, 'buyer_name' => 'Pembeli '.$i]);
            $first ??= $sale;
        }
        $this->actingAs($this->manager);
        $page = $this->page('sort')['report'];
        $this->assertCount(50, $page['rows']['data']);
        $this->assertSame(55, $page['rows']['total']);
        $this->assertSame(55055, $page['totals']['sold_grams']);
        $this->assertSame(55055, $page['totals']['revenue_idr']);
        $csv = $this->csv('sort');
        $this->assertCount(57, $csv);
        $this->assertSame('1.001', $csv[1][array_search('Berat terjual (kg)', $csv[0])]);
        $this->assertCount(5, $this->page('sort', ['page' => 2])['report']['rows']['data']);
        $this->get(route('reports.print', ['view' => 'sort']))->assertOk()->assertSee($first->number)->assertSee($sale->number)->assertSee('55.055')->assertSee('Cetak / simpan PDF');
    }

    public function test_filters_use_source_dates_and_current_payment_status_with_inactive_masters(): void
    {
        $rows = $this->fixture();
        $this->customer->update(['is_active' => false]);
        $this->factory->update(['is_active' => false]);
        $this->actingAs($this->cashier);
        $this->assertSame(2200000, $this->page('debts', ['customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id, 'status' => 'partial'])['report']['totals']['remaining_idr']);
        $this->assertSame(0, $this->page('debts', ['status' => 'paid'])['report']['rows']['total']);
        $this->assertSame(2, $this->page('receivables')['report']['rows']['total']);
        $this->assertSame(1, $this->page('receivables', ['status' => 'unpaid'])['report']['rows']['total']);
        $this->assertSame('sort-sales.show', $this->page('receivables', ['status' => 'partial'])['report']['rows']['data'][0]['source']['route']);
        $this->assertSame(1, $this->page('receivables', ['factory_id' => $this->factory->id])['report']['rows']['total']);
        $this->assertSame(26667, $this->page('payroll', ['worker_id' => $this->workers[0], 'status' => 'partial'])['report']['totals']['remaining_idr']);
        $this->assertSame(3, $this->page('payroll', ['status' => 'unpaid'])['report']['rows']['total']);
        $this->assertSame(1, $this->page('expenses', ['expense_category_id' => $this->category->id, 'cash_account_id' => $this->cash->id])['report']['rows']['total']);
        $this->assertSame(0, $this->page('factory', ['from' => '2026-10-10', 'to' => '2026-10-10'])['report']['rows']['total']);
        $this->assertSame(3000, $this->page('weighings')['report']['rows']['data'][0]['tbs_price_idr']);
        $this->assertSame(100, $this->page('payroll')['report']['rows']['data'][0]['rate_idr']);
        $ids = array_column($this->page('receivables')['report']['rows']['data'], 'id');
        $this->assertCount(2, array_unique($ids));
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        $this->assertSame(1, $this->page('debts')['report']['rows']['data'][0]['age_days']);
    }

    public function test_cash_reports_normalize_alternate_accounts_and_default_summaries_show_only_business_cash(): void
    {
        $this->fixture();
        $legacy = CashAccount::create(['code' => 'KAS-LAMA', 'name' => 'Kas lama tersembunyi', 'type' => 'cash', 'is_active' => true]);
        DB::table('cash_entries')->insert(['cash_account_id' => $legacy->id, 'number' => 'KAS-LAMA-001',
            'direction' => 'in', 'kind' => 'capital', 'amount_idr' => 9999, 'occurred_at' => now(),
            'business_date' => '2026-10-09', 'reason' => 'Modal historis', 'created_by' => $this->manager->id]);
        foreach ([$this->manager, $this->cashier] as $actor) {
            $this->actingAs($actor);
            foreach (['cash', 'receipts', 'expenses'] as $view) {
                $normal = $this->page($view);
                $forged = $this->page($view, ['cash_account_id' => $legacy->id]);
                $this->assertSame($this->cash->id, $forged['filters']['cash_account_id']);
                $this->assertSame([['id' => $this->cash->id, 'label' => $this->cash->name]], $forged['options']['cash_account_id']);
                $this->assertSame($normal['report']['rows']['total'], $forged['report']['rows']['total']);
                $this->assertSame($normal['report']['totals'], $forged['report']['totals']);
                $this->assertSame($this->csv($view), $this->csv($view, ['cash_account_id' => $legacy->id]));
                $this->get(route('reports.print', ['view' => $view, 'cash_account_id' => $legacy->id]))
                    ->assertOk()->assertDontSee('Kas lama tersembunyi');
            }
            $summary = $this->page()['summary'];
            $this->assertSame(2140000, $summary['balances']['cash_idr']);
            $this->assertSame([$this->cash->id], array_column($summary['cashflow']['accounts'], 'id'));
            $this->assertSame(2140000, $summary['cashflow']['totals']['closing_idr']);
        }
        $this->assertDatabaseHas('cash_entries', ['cash_account_id' => $legacy->id, 'amount_idr' => 9999]);
        $this->assertDatabaseCount('cash_accounts', 2);
    }

    public function test_role_scope_applies_to_rows_exports_print_and_financial_fields(): void
    {
        $this->fixture();
        $other = User::factory()->create(['assigned_vehicle_id' => $this->vehicle->id]);
        $hidden = $this->sale('sort', ['buyer_name' => 'RAHASIA SUPIR LAIN'], $other);
        $this->actingAs($this->driver);
        $this->get(route('sort-sales.index'))->assertOk()->assertDontSee('RAHASIA SUPIR LAIN');
        $this->get(route('sort-sales.show', $hidden->id))->assertForbidden();
        $this->assertSame([], app(OperationalReport::class)->views($this->driver));
        foreach (array_keys(OperationalReport::TITLES) as $view) {
            foreach (['reports.index', 'reports.export', 'reports.print'] as $route) {
                $this->get(route($route, ['view' => $view]))->assertForbidden();
            }
        }
        $this->actingAs($this->cashier);
        $this->assertArrayNotHasKey('period_difference_idr', $this->page()['summary']['totals']);
        foreach (['reports.index', 'reports.export', 'reports.print'] as $route) {
            $this->get(route($route, ['view' => 'audit']))->assertForbidden();
        }
        $this->cashier->update(['is_active' => false]);
        $this->get(route('reports.export'))->assertRedirect(route('login'));
    }

    public function test_csv_neutralizes_formulas_and_print_escapes_user_text(): void
    {
        $buyer = '=HYPERLINK("https://example.test","<script>alert(1)</script>")';
        $this->sale('sort', ['buyer_name' => $buyer]);
        $this->actingAs($this->manager);
        $csv = $this->csv('sort');
        $this->assertSame("'".$buyer, $csv[1][array_search('Pihak terkait', $csv[0])]);
        $this->get(route('reports.print', ['view' => 'sort']))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_audit_reports_show_changes_without_exporting_sensitive_or_unknown_attributes(): void
    {
        DB::table('audit_logs')->insert(['actor_id' => $this->manager->id, 'action' => 'updated_with_password_reset', 'entity_type' => 'users', 'entity_id' => (string) $this->manager->id,
            'before_json' => json_encode(['name' => 'Petugas lama', 'password' => 'HASH-LAMA', 'storage_path' => 'PRIVATE-PATH']),
            'after_json' => json_encode(['name' => 'Petugas baru', 'password' => 'HASH-BARU', 'remember_token' => 'SECRET-TOKEN', 'future_secret' => 'SECRET-FUTURE']),
            'occurred_at' => app(BusinessClock::class)->now()]);
        $this->actingAs($this->manager);
        $report = $this->page('audit')['report'];
        $this->assertSame('Nama: Petugas lama → Petugas baru', $report['rows']['data'][0]['changes']);
        $page = json_encode($report);
        $csv = json_encode($this->csv('audit'));
        $print = $this->get(route('reports.print', ['view' => 'audit']))->assertOk()->assertSee('Petugas lama')->assertSee('Petugas baru')->getContent();
        foreach (['HASH-LAMA', 'HASH-BARU', 'PRIVATE-PATH', 'SECRET-TOKEN', 'SECRET-FUTURE', 'before_json', 'after_json'] as $secret) {
            $this->assertStringNotContainsString($secret, $page.$csv.$print);
        }
    }

    public function test_reversals_at_midnight_preserve_original_period_and_reconcile_negative_reports(): void
    {
        $rows = $this->fixture();
        $this->travelTo(Carbon::parse('2026-10-09 17:00:00', 'UTC'));
        foreach (['customerPayment' => FinanceKind::Customer, 'payrollPayment' => FinanceKind::Payroll, 'expense' => FinanceKind::Expense] as $key => $kind) {
            app(ReverseFinance::class)->handle($this->manager, $kind, $rows[$key], $this->key(['version' => 1, 'reason' => 'Koreksi', 'return_reference' => 'Dana kembali']));
        }
        foreach (['factory', 'sort'] as $key) {
            app(ReverseSale::class)->handle($this->manager, $rows[$key], $this->key(['version' => 2, 'reason' => 'Koreksi nota']));
        }
        app(VoidWeighing::class)->handle($this->manager, $rows['weighing'], $this->key(['version' => 2, 'reason' => 'Koreksi tiket']));
        $this->actingAs($this->manager);
        $this->assertSame(3350000, $this->page()['summary']['totals']['revenue_idr']);
        $next = ['from' => '2026-10-10', 'to' => '2026-10-10'];
        $this->assertSame(-3350000, $this->page('summary', $next)['summary']['totals']['revenue_idr']);
        foreach (['weighings' => ['purchase_idr', -3200000], 'factory' => ['revenue_idr', -3150000], 'sort' => ['revenue_idr', -200000], 'expenses' => ['amount_idr', -50000], 'receipts' => ['amount_idr', -3200000]] as $view => [$key, $value]) {
            $report = $this->page($view, $next)['report'];
            $this->assertSame($value, $report['totals'][$key]);
            $this->assertSame('reversal', $report['rows']['data'][0]['event']);
            $csv = $this->csv($view, $next);
            $this->assertStringStartsWith('-', $csv[1][array_search($report['columns'][array_search($key, array_column($report['columns'], 'key'))]['label'], $csv[0])]);
        }
        $flow = $this->page('cash', $next)['report']['cashflow']['totals'];
        $this->assertSame(2140000, $flow['opening_idr']);
        $this->assertSame(0, $flow['closing_idr']);
        $this->assertSame(0, $this->page('summary', ['to' => '2026-10-10'])['summary']['totals']['revenue_idr']);
        $this->assertSame(0, $this->page('summary', $next)['summary']['balances']['payroll_debt_idr']);
    }

    public function test_cash_running_balance_includes_history_before_the_period_without_changing_revenue(): void
    {
        app(ManualCash::class)->post($this->manager, $this->key(['cash_account_id' => $this->cash->id, 'kind' => 'capital', 'amount_idr' => 500000,
            'occurred_at' => '2026-10-08T10:00:00', 'backdate_reason' => 'Modal sebelum periode', 'reason' => 'Modal awal']));
        $this->fixture();
        $this->actingAs($this->manager);
        $cash = $this->page('cash')['report'];
        $this->assertSame(500000, $cash['cashflow']['totals']['opening_idr']);
        $this->assertSame(2640000, $cash['cashflow']['totals']['closing_idr']);
        $this->assertSame(3650000, $cash['rows']['data'][0]['balance_idr']);
        $this->assertSame(2640000, $cash['rows']['data'][4]['balance_idr']);
        $this->assertSame(3350000, $this->page()['summary']['totals']['revenue_idr']);
        $this->get(route('reports.print', ['view' => 'cash']))->assertOk()->assertSee('Rp 500.000')->assertSee('Rp 2.640.000');
    }

    public function test_proof_links_retain_private_download_authorization(): void
    {
        Storage::fake('local');
        $rows = $this->fixture();
        $file = UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $this->actingAs($this->cashier)->post(route('finance.proofs', ['kind' => 'expense', 'document' => $rows['expense']->id]), ['proof' => $file])->assertSessionHasNoErrors();
        $report = $this->page('expenses')['report'];
        $this->assertSame(1, $report['rows']['data'][0]['proof_count']);
        $this->assertSame(['route' => 'finance.show', 'params' => ['kind' => 'expense', 'document' => $rows['expense']->id]], $report['rows']['data'][0]['source']);
        $attachment = DB::table('attachments')->sole();
        $this->get(route('finance-proofs.download', $attachment->id))->assertOk();
        $this->actingAs($this->driver)->get(route('finance-proofs.download', $attachment->id))->assertForbidden();
    }

    public function test_invalid_or_irrelevant_filters_are_rejected_and_empty_reports_are_downloadable(): void
    {
        $this->actingAs($this->manager);
        foreach (['reports.index', 'reports.export', 'reports.print'] as $route) {
            $this->getJson(route($route, ['view' => 'unknown']))->assertUnprocessable();
            $this->getJson(route($route, ['view' => 'cash', 'vehicle_id' => $this->vehicle->id]))->assertUnprocessable();
            $this->getJson(route($route, ['view' => 'factory', 'factory_id' => 99999]))->assertUnprocessable();
            $this->getJson(route($route, ['from' => '2026-10-10', 'to' => '2026-10-09']))->assertUnprocessable();
        }
        $this->assertSame(0, $this->page('factory')['report']['rows']['total']);
        $this->assertCount(2, $this->csv('factory'));
        $this->get(route('reports.print', ['view' => 'factory']))->assertOk()->assertSee('Belum ada transaksi untuk filter ini.');
    }
}
