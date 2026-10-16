<?php

namespace Tests\Concerns\DirectSalesAndSummaryTest;

use App\Actions\Finance\PostFinance;
use App\Actions\Finance\ReverseFinance;
use App\Actions\Sales\ReverseSale;
use App\Actions\Weighings\SaveDirectWeighing;
use App\Actions\Weighings\VoidWeighing;
use App\Enums\FinanceKind;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\LoadingWorker;
use App\Models\PriceSchedule;
use App\Models\User;
use App\Queries\PeriodSummary;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

trait PeriodSummariesCases
{
    public function test_dashboard_and_report_do_not_double_count_payments_and_exclude_unassigned_expense_from_vehicle_totals(): void
    {
        $customer = Customer::create(['code' => 'K1', 'name' => 'Konsumen', 'is_active' => true]);
        $worker = LoadingWorker::create(['code' => 'A1', 'name' => 'Anggota', 'is_active' => true]);
        $price = PriceSchedule::create(['business_date' => '2026-10-09', 'effective_from' => '2026-10-08 17:00:00', 'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
        $ticket = app(SaveDirectWeighing::class)->handle($this->driver, ['customer_id' => $customer->id, 'vehicle_id' => $this->vehicle->id, 'vehicle_assignment_version' => 1, 'tbs_kg' => '1000', 'sort_kg' => '100', 'worker_ids' => [$worker->id], 'price_schedule_id' => $price->id, 'idempotency_key' => (string) Str::uuid()]);
        $factorySale = $this->save(changes: ['deduction_idr' => 0]);
        $sortSale = $this->save('sort', ['sold_kg' => '100']);
        $this->receive($sortSale);
        $account = $this->cash;
        $base = ['cash_account_id' => $account->id, 'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00'];
        app(PostFinance::class)->handle($this->cashier, FinanceKind::Receipt, $base + ['sale_kind' => 'factory', 'sale_id' => $factorySale->id,
            'expected_remaining_idr' => 3150000, 'amount_idr' => 3150000, 'idempotency_key' => (string) Str::uuid()]);
        $category = ExpenseCategory::create(['code' => 'BBM', 'name' => 'BBM', 'is_active' => true]);
        app(PostFinance::class)->handle($this->cashier, FinanceKind::Expense, $base + ['expense_category_id' => $category->id, 'description' => 'BBM', 'amount_idr' => 50000, 'idempotency_key' => (string) Str::uuid()]);
        $this->legacyCustomerPayment($this->cashier, $account, [$ticket->id => 100000]);
        app(PostFinance::class)->handle($this->cashier, FinanceKind::Payroll, $base + ['recipient_id' => $worker->id, 'period_start' => '2026-10-09', 'period_end' => '2026-10-09', 'selected_ids' => [$ticket->loaders()->first()->id], 'expected_remaining_idr' => 110000, 'amount_idr' => 50000, 'idempotency_key' => (string) Str::uuid()]);
        $this->actingAs($this->manager)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p
            ->where('summary.totals.purchase_idr', 3200000)->where('summary.totals.revenue_idr', 3350000)->where('summary.totals.loading_idr', 110000)->where('summary.totals.expense_idr', 50000)
            ->where('summary.totals.period_difference_idr', -10000)->where('summary.balances.customer_debt_idr', 3100000)->where('summary.balances.cash_idr', 3150000)->where('summary.balances.sale_receivable_idr', 0));
        $this->get(route('reports.index', ['vehicle_id' => $this->vehicle->id]))->assertInertia(fn (Assert $p) => $p->has('summary.vehicles', 1)->where('summary.totals.expense_idr', 0)->missing('summary.totals.period_difference_idr'));
        $this->actingAs($this->cashier)->get(route('reports.index'))->assertInertia(fn (Assert $p) => $p->missing('summary.totals.period_difference_idr')->has('summary.balances'));
        $this->actingAs($this->driver)->get(route('dashboard'))->assertInertia(fn (Assert $p) => $p->where('summary.balances', null)->missing('summary.totals.period_difference_idr')->missing('summary.totals.expense_idr'));
    }

    public function test_corrections_reduce_the_reversal_day_without_rewriting_original_period(): void
    {
        $sale = $this->save('sort', ['sold_kg' => '100']);
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        app(ReverseSale::class)->handle($this->manager, $sale, ['version' => $sale->version, 'reason' => 'Koreksi', 'idempotency_key' => (string) Str::uuid()]);
        $summary = app(PeriodSummary::class);
        $old = $summary->get($this->manager, ['from' => '2026-10-09', 'to' => '2026-10-09']);
        $today = $summary->get($this->manager, ['from' => '2026-10-10', 'to' => '2026-10-10']);
        $this->assertSame(200000, $old['totals']['revenue_idr']);
        $this->assertSame(-200000, $today['totals']['revenue_idr']);
        $this->assertSame(-100000, $today['totals']['sort_sold_grams']);
        $both = $summary->get($this->manager, ['from' => '2026-10-09', 'to' => '2026-10-10']);
        $this->assertSame(0, $both['totals']['revenue_idr']);
        $this->assertCount(2, $both['days']);
        $other = User::factory()->create();
        $this->assertSame(0, $summary->get($other, ['from' => '2026-10-09', 'to' => '2026-10-10'])['totals']['revenue_idr']);
        $this->actingAs($this->manager)->get(route('reports.index', ['from' => '2026-10-10', 'to' => '2026-10-09']))->assertSessionHasErrors('to');
    }

    public function test_weighing_and_expense_reversals_use_the_business_day_at_midnight(): void
    {
        $customer = Customer::create(['code' => 'KM', 'name' => 'Konsumen', 'is_active' => true]);
        $worker = LoadingWorker::create(['code' => 'AM', 'name' => 'Anggota', 'is_active' => true]);
        $price = PriceSchedule::create(['business_date' => '2026-10-09', 'effective_from' => '2026-10-08 17:00:00',
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
        $ticket = app(SaveDirectWeighing::class)->handle($this->driver, ['customer_id' => $customer->id, 'vehicle_id' => $this->vehicle->id,
            'vehicle_assignment_version' => 1,
            'tbs_kg' => '1', 'sort_kg' => '1', 'worker_ids' => [$worker->id], 'price_schedule_id' => $price->id, 'idempotency_key' => (string) Str::uuid()]);
        $sale = $this->save('sort', ['sold_kg' => '100']);
        $this->receive($sale);
        $account = $this->cash;
        $category = ExpenseCategory::create(['code' => 'BBM-M', 'name' => 'BBM', 'is_active' => true]);
        $base = ['cash_account_id' => $account->id, 'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00'];
        $expense = app(PostFinance::class)->handle($this->cashier, FinanceKind::Expense, $base + ['expense_category_id' => $category->id,
            'description' => 'BBM', 'amount_idr' => 50000, 'idempotency_key' => (string) Str::uuid()]);
        $this->travelTo(Carbon::parse('2026-10-09 17:00:00', 'UTC'));
        app(VoidWeighing::class)->handle($this->manager, $ticket, ['version' => $ticket->version,
            'reason' => 'Koreksi', 'idempotency_key' => (string) Str::uuid()]);
        app(ReverseFinance::class)->handle($this->manager, FinanceKind::Expense, $expense, ['version' => $expense->version,
            'reason' => 'Koreksi', 'return_reference' => 'BBM dikembalikan', 'idempotency_key' => (string) Str::uuid()]);
        $summary = app(PeriodSummary::class);
        $old = $summary->get($this->manager, ['from' => '2026-10-09', 'to' => '2026-10-09']);
        $today = $summary->get($this->manager, ['from' => '2026-10-10', 'to' => '2026-10-10']);
        $this->assertSame(5000, $old['totals']['purchase_idr']);
        $this->assertSame(-5000, $today['totals']['purchase_idr']);
        $this->assertSame(-200, $today['totals']['loading_idr']);
        $this->assertSame(-1, $today['totals']['weighing_count']);
        $this->assertSame(-50000, $today['totals']['expense_idr']);
        $this->assertSame(55200, $today['totals']['period_difference_idr']);
        $this->assertSame(0, $today['balances']['customer_debt_idr']);
        $both = $summary->get($this->manager, ['from' => '2026-10-09', 'to' => '2026-10-10']);
        $this->assertSame(0, $both['totals']['purchase_idr']);
        $this->assertSame(0, $both['totals']['expense_idr']);
    }
}
