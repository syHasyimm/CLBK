<?php

namespace Tests\Feature;

use App\Actions\Weighings\SaveDirectWeighing;
use App\Enums\FinanceKind;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\LoadingWorker;
use App\Models\PayrollPayment;
use App\Models\PriceSchedule;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Weighing;
use App\Services\CashLedger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\DatabaseMigrations;
use Tests\TestCase;

class PayrollHistoryTest extends TestCase
{
    use DatabaseMigrations;

    private User $manager;

    private User $cashier;

    private User $driver;

    private CashAccount $cash;

    private Customer $customer;

    private Vehicle $vehicle;

    private LoadingWorker $worker;

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
        $this->seed();
        $this->manager = User::factory()->manager()->create();
        $this->cashier = User::factory()->cashier()->create();
        $this->driver = User::factory()->create();
        $this->cash = CashAccount::firstOrFail();
        $this->customer = Customer::create(['code' => 'K1', 'name' => 'Konsumen', 'is_active' => true]);
        $this->vehicle = Vehicle::create(['code' => 'M1', 'plate_number' => 'BK PAYROLL', 'is_active' => true]);
        $this->worker = LoadingWorker::create(['code' => 'A1', 'name' => 'Budi Muat', 'is_active' => true,
            'phone' => '08123456789', 'address' => 'Alamat pribadi']);
        $this->actingAs($this->manager)->post(route('cash.store'), [
            'cash_account_id' => $this->cash->id, 'kind' => 'opening', 'amount_idr' => 10000000,
            'occurred_at' => '2026-09-01T09:00:00', 'reason' => 'Modal pembayaran upah',
            'backdate_reason' => 'Saldo awal usaha', 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
    }

    private function weigh(string $kg = '1000', string $date = '2026-10-09', ?LoadingWorker $worker = null, int $rate = 100): Weighing
    {
        $this->travelTo(Carbon::parse($date.' 03:00:00', 'UTC'));
        $price = PriceSchedule::firstOrCreate(['business_date' => $date], [
            'effective_from' => Carbon::parse($date.' 00:00:00', 'Asia/Jakarta')->utc(),
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => $rate,
            'published_by' => $this->manager->id,
        ]);

        return app(SaveDirectWeighing::class)->handle($this->manager, [
            'customer_id' => $this->customer->id, 'vehicle_id' => $this->vehicle->id,
            'tbs_kg' => $kg, 'sort_kg' => '0', 'worker_ids' => [($worker ?? $this->worker)->id],
            'price_schedule_id' => $price->id, 'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    private function pay(array $tickets, int $amount, int $remaining, ?LoadingWorker $worker = null, string $date = '2026-10-09'): PayrollPayment
    {
        $this->travelTo(Carbon::parse($date.' 05:00:00', 'UTC'));
        $dates = array_map(fn (Weighing $ticket) => $ticket->business_date, $tickets);
        $ids = array_map(fn (Weighing $ticket) => $ticket->loaders()->sole()->id, $tickets);
        $this->actingAs($this->cashier)->post(route('payroll-payments.store'), [
            'recipient_id' => ($worker ?? $this->worker)->id, 'selected_ids' => $ids,
            'cash_account_id' => $this->cash->id, 'amount_idr' => $amount, 'expected_remaining_idr' => $remaining,
            'method' => 'cash', 'period_start' => min($dates), 'period_end' => max($dates),
            'occurred_at' => $date.'T12:00:00', 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();

        return PayrollPayment::latest('id')->firstOrFail();
    }

    public function test_partial_jobs_stay_outstanding_and_settled_jobs_move_to_history_then_reopen_after_reversal(): void
    {
        $first = $this->weigh();
        $second = $this->weigh('500');
        $firstLoader = $first->loaders()->sole()->id;
        $secondLoader = $second->loaders()->sole()->id;
        $partialPayment = $this->pay([$first], 40000, 100000);
        $this->get(route('payroll.payee', $this->worker))->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 2)->where('rows.data.0.id', $firstLoader)->where('rows.data.0.remaining_idr', 60000)
            ->where('totals.ticket_count', 2)->where('totals.outstanding_count', 2)->where('totals.remaining_idr', 110000));
        $this->get(route('payroll.history'))->assertInertia(fn (Assert $page) => $page
            ->component('Finance/PayrollHistory')->where('filters.view', 'settled')->has('rows.data', 0)
            ->where('documents', null)->where('summary.total_idr', 0));

        $finalPayment = $this->pay([$first], 60000, 60000);
        $this->worker->update(['is_active' => false]);
        foreach ([$this->cashier, $this->manager] as $actor) {
            $this->actingAs($actor)->get(route('payroll.payee', $this->worker))->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)->where('rows.total', 1)->where('rows.data.0.id', $secondLoader)
                ->where('totals.ticket_count', 2)->where('totals.total_idr', 150000)->where('totals.paid_idr', 100000)
                ->where('totals.outstanding_count', 1)->where('totals.remaining_idr', 50000)->has('history.data', 2));
            $this->get(route('payroll.index', ['status' => 'paid']))->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'unpaid')->has('cards.data', 1)->where('cards.data.0.ticket_count', 1)
                ->where('cards.data.0.total_idr', 50000)->where('cards.data.0.paid_idr', 0)->where('cards.data.0.remaining_idr', 50000)
                ->has('cards.data.0.tickets', 1)->where('cards.data.0.tickets.0.weighing_id', $second->id));
            $this->get(route('payroll.history', ['worker_id' => $this->worker->id]))->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)->where('rows.data.0.id', $firstLoader)->where('rows.data.0.weighing_id', $first->id)
                ->where('rows.data.0.total_idr', 100000)->where('rows.data.0.paid_idr', 100000)->where('rows.data.0.remaining_idr', 0)
                ->where('rows.data.0.worker', $this->worker->only(['id', 'code', 'name', 'is_active']))
                ->where('worker', $this->worker->only(['id', 'code', 'name', 'is_active']))
                ->where('summary.record_count', 1)->where('summary.worker_count', 1)->where('summary.total_idr', 100000)
                ->where('summary.reversed_idr', 0));
            $this->get(route('payroll.history', ['view' => 'payments']))->assertInertia(fn (Assert $page) => $page
                ->where('rows', null)->has('documents.data', 2)->where('summary.total_idr', 100000)
                ->where('summary.reversed_idr', 0)->where('documents.data.0.worker.name', 'Budi Muat')
                ->where('documents.data.0.cash_account.id', $this->cash->id)
                ->where('documents.data.0.creator.id', $this->cashier->id));
        }

        $this->actingAs($this->manager)->post(route('finance.reverse', ['kind' => FinanceKind::Payroll->value, 'document' => $finalPayment->id]), [
            'version' => $finalPayment->version, 'reason' => 'Koreksi pembayaran', 'return_reference' => 'Upah dikembalikan tunai',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $counts = collect(['payroll_payments', 'payroll_payment_allocations', 'cash_entries', 'audit_logs', 'idempotency_requests'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
        $this->actingAs($this->cashier)->get(route('payroll.payee', $this->worker))->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 2)->where('rows.data.0.id', $firstLoader)->where('rows.data.0.remaining_idr', 60000)
            ->where('totals.remaining_idr', 110000));
        $this->get(route('payroll.history'))->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 0)->where('summary.record_count', 0)->where('summary.total_idr', 0));
        $this->get(route('payroll.history', ['view' => 'payments']))->assertInertia(fn (Assert $page) => $page
            ->has('documents.data', 2)->where('documents.data.0.id', $finalPayment->id)->where('documents.data.0.status', 'reversed')
            ->where('documents.data.0.amount_idr', 60000)->where('documents.data.1.id', $partialPayment->id)
            ->where('summary.total_idr', 40000)->where('summary.reversed_idr', 60000));
        foreach ($counts as $table => $count) {
            $this->assertDatabaseCount($table, $count);
        }
        $this->assertDatabaseCount('payroll_payments', 2);
        $this->assertDatabaseCount('payroll_payment_allocations', 2);
        $this->assertDatabaseCount('cash_entries', 4);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'payroll_payments', 'entity_id' => (string) $finalPayment->id, 'action' => 'reverse-finance']);
        $this->assertSame(9960000, app(CashLedger::class)->balance($this->cash->id));
        $this->assertSame(100000, $first->fresh()->total_loading_wage_idr);
    }

    public function test_paid_jobs_are_removed_before_detail_pagination_and_history_summary_covers_every_page(): void
    {
        $paid = [];
        for ($i = 0; $i < 52; $i++) {
            $paid[] = $this->weigh('1');
        }
        $outstanding = $this->weigh('2');
        $this->pay($paid, 5200, 5200);
        foreach ([$this->cashier, $this->manager] as $actor) {
            $this->actingAs($actor)->get(route('payroll.payee', $this->worker))->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)->where('rows.data.0.weighing_id', $outstanding->id)->where('rows.total', 1)->where('rows.last_page', 1)
                ->where('totals.ticket_count', 53)->where('totals.paid_idr', 5200)->where('totals.remaining_idr', 200));
            $this->get(route('payroll.index'))->assertInertia(fn (Assert $page) => $page
                ->has('cards.data', 1)->where('cards.data.0.ticket_count', 1)->where('cards.data.0.total_idr', 200)
                ->has('cards.data.0.tickets', 1)->where('cards.data.0.tickets.0.weighing_id', $outstanding->id));
            $this->get(route('payroll.history', ['page' => 2]))->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 2)->where('rows.current_page', 2)->where('rows.total', 52)->where('summary.record_count', 52)
                ->where('summary.worker_count', 1)->where('summary.total_idr', 5200)->where('summary.reversed_idr', 0));
        }
    }

    public function test_payment_history_summary_includes_other_pages_and_separates_reversed_vouchers(): void
    {
        $ticket = $this->weigh();
        for ($i = 0; $i < 20; $i++) {
            $this->pay([$ticket], 1000, 100000 - $i * 1000);
        }
        $finalPayment = $this->pay([$ticket], 80000, 80000);
        $this->actingAs($this->manager)->post(route('finance.reverse', ['kind' => FinanceKind::Payroll->value, 'document' => $finalPayment->id]), [
            'version' => $finalPayment->version, 'reason' => 'Koreksi pembayaran', 'return_reference' => 'Upah dikembalikan tunai',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->cashier)->get(route('payroll.history', ['view' => 'payments', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('documents.current_page', 2)
                ->where('documents.total', 21)->where('summary.record_count', 21)->where('summary.worker_count', 1)
                ->where('summary.total_idr', 20000)->where('summary.reversed_idr', 80000));
        $this->get(route('payroll.history', ['view' => 'payments', 'status' => 'reversed']))
            ->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('documents.data.0.id', $finalPayment->id)
                ->where('summary.record_count', 1)->where('summary.total_idr', 0)->where('summary.reversed_idr', 80000));
        $this->get(route('payroll.history', ['view' => 'payments', 'status' => 'posted']))
            ->assertInertia(fn (Assert $page) => $page->has('documents.data', 20)->where('summary.record_count', 20)
                ->where('summary.total_idr', 20000)->where('summary.reversed_idr', 0));
        $this->assertDatabaseCount('payroll_payments', 21);
        $this->assertDatabaseCount('payroll_payment_allocations', 21);
    }

    public function test_history_filters_use_work_dates_for_settled_jobs_and_payment_dates_for_vouchers_without_a_default_month(): void
    {
        $otherWorker = LoadingWorker::create(['code' => 'A2', 'name' => 'Sari Muat', 'is_active' => true]);
        $this->weigh('1', '2026-09-29', rate: 0);
        $old = $this->weigh('100', '2026-09-30');
        $current = $this->weigh('200');
        $other = $this->weigh('300', worker: $otherWorker);
        $oldPayment = $this->pay([$old], 10000, 10000, date: '2026-10-10');
        $this->pay([$current], 20000, 20000, date: '2026-10-10');
        $this->pay([$other], 30000, 30000, $otherWorker, '2026-10-10');
        $this->travelTo(Carbon::parse('2026-11-01 03:00:00', 'UTC'));

        $this->get(route('payroll.history'))->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 3)->where('rows.total', 3)->where('summary.record_count', 3)
            ->where('summary.worker_count', 2)->where('summary.total_idr', 60000)->where('worker', null));
        foreach (['Budi', 'A1'] as $search) {
            $this->get(route('payroll.history', ['q' => $search]))->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 2)->where('summary.worker_count', 1)->where('summary.total_idr', 30000));
        }
        $this->get(route('payroll.history', ['q' => $old->number]))->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 1)->where('rows.data.0.weighing_id', $old->id)->where('summary.total_idr', 10000));
        $this->get(route('payroll.history', ['from' => '2026-09-30', 'to' => '2026-09-30']))->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 1)->where('rows.data.0.business_date', '2026-09-30')->where('summary.total_idr', 10000));
        $this->get(route('payroll.history', ['from' => '2026-10-09']))->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 2)->where('summary.total_idr', 50000));
        $this->get(route('payroll.history', ['to' => '2026-09-30']))->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 1)->where('summary.total_idr', 10000));
        $this->get(route('payroll.history', ['worker_id' => $otherWorker->id]))->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 1)->where('rows.data.0.weighing_id', $other->id)->where('worker.id', $otherWorker->id)
            ->where('summary.total_idr', 30000));

        $this->get(route('payroll.history', ['view' => 'payments', 'from' => '2026-10-10', 'to' => '2026-10-10']))
            ->assertInertia(fn (Assert $page) => $page->where('rows', null)->has('documents.data', 3)
                ->where('summary.record_count', 3)->where('summary.worker_count', 2)->where('summary.total_idr', 60000));
        $this->get(route('payroll.history', ['view' => 'payments', 'from' => '2026-09-30', 'to' => '2026-09-30']))
            ->assertInertia(fn (Assert $page) => $page->has('documents.data', 0)->where('summary.total_idr', 0));
        $this->get(route('payroll.history', ['view' => 'payments', 'q' => $oldPayment->number]))->assertInertia(fn (Assert $page) => $page
            ->has('documents.data', 1)->where('documents.data.0.id', $oldPayment->id)->where('summary.total_idr', 10000));
        foreach (['Budi', 'A1'] as $search) {
            $this->get(route('payroll.history', ['view' => 'payments', 'q' => $search]))->assertInertia(fn (Assert $page) => $page
                ->has('documents.data', 2)->where('summary.total_idr', 30000));
        }
        $this->get(route('payroll.history', ['view' => 'payments', 'worker_id' => $otherWorker->id, 'status' => 'posted']))
            ->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('worker.id', $otherWorker->id)
                ->where('summary.total_idr', 30000));
        $this->get(route('payroll.history', ['view' => 'payments', 'status' => 'reversed']))->assertInertia(fn (Assert $page) => $page
            ->has('documents.data', 0)->where('summary.total_idr', 0)->where('summary.reversed_idr', 0));
    }

    public function test_history_is_available_to_manager_and_cashier_and_rejects_driver_and_invalid_filters(): void
    {
        foreach ([$this->manager, $this->cashier] as $actor) {
            foreach (['settled', 'payments'] as $view) {
                $this->actingAs($actor)->get(route('payroll.history', ['view' => $view]))
                    ->assertInertia(fn (Assert $page) => $page->component('Finance/PayrollHistory')->where('filters.view', $view));
            }
        }
        $this->actingAs($this->driver)->get(route('payroll.history'))->assertForbidden();
        $this->get(route('payroll.history', ['view' => 'payments']))->assertForbidden();
        $this->actingAs($this->cashier)->get(route('payroll.history', ['from' => '2026-10-10', 'to' => '2026-10-09']))
            ->assertSessionHasErrors('to');
        $this->get(route('payroll.history', ['view' => 'invalid']))->assertSessionHasErrors('view');
        $this->get(route('payroll.history', ['worker_id' => 0]))->assertSessionHasErrors('worker_id');
    }
}
