<?php

namespace Tests\Concerns\FinanceTest;

use App\Enums\FinanceKind;
use App\Models\CustomerPayment;
use App\Models\LoadingWorker;
use App\Models\PayrollPayment;
use App\Models\PriceSchedule;
use App\Services\CashLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

trait PayrollPaymentsCases
{
    public function test_payroll_uses_snapshot_and_period_filters_with_inactive_payees(): void
    {
        $ticket = $this->weigh();
        $this->capital();
        $this->worker->update(['is_active' => false]);
        $this->customer->update(['is_active' => false]);
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        $payroll = $this->key(['recipient_id' => $this->worker->id, 'selected_ids' => [$ticket->loaders()->sole()->id], 'amount_idr' => 50000, 'expected_remaining_idr' => 100000,
            'cash_account_id' => $this->cash->id, 'method' => 'cash', 'period_start' => '2026-10-09', 'period_end' => '2026-10-09', 'occurred_at' => '2026-10-10T10:00:00']);
        $this->actingAs($this->cashier)->post(route('payroll-payments.store'), array_replace($payroll, ['period_start' => '2026-10-10', 'period_end' => '2026-10-10']))->assertSessionHasErrors('selected_ids');
        $this->post(route('payroll-payments.store'), $payroll)->assertSessionHasNoErrors();
        $this->assertSame(50000, (int) DB::table('payroll_payment_allocations')->sum('amount_idr'));
        $this->post(route('customer-payments.store'), $this->payment($ticket, ['occurred_at' => '2026-10-10T10:00:00']))->assertSessionHasNoErrors();
        $this->get(route('payroll.payee', ['recipient' => $this->worker->id, 'from' => '2026-10-09', 'to' => '2026-10-09']))->assertInertia(fn (Assert $p) => $p->where('totals.remaining_idr', 50000)->has('history.data', 1));
        $this->assertDatabaseCount('profit_entries', 0);
    }

    public function test_automatic_payroll_pays_oldest_remaining_wages_in_period_using_frozen_shares(): void
    {
        $first = $this->weigh();
        $firstLoader = $first->loaders()->sole()->id;
        $this->capital();
        $this->actingAs($this->cashier)->post(route('payroll-payments.store'),
            $this->key(['recipient_id' => $this->worker->id, 'selected_ids' => [$firstLoader], 'amount_idr' => 50000,
                'expected_remaining_idr' => 100000, 'cash_account_id' => $this->cash->id, 'method' => 'cash',
                'period_start' => '2026-10-09', 'period_end' => '2026-10-09', 'occurred_at' => '2026-10-09T10:00:00']))->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-10-09 04:00:00', 'UTC'));
        $second = $this->weigh('500');
        $secondLoader = $second->loaders()->sole()->id;
        $originalWorker = $this->worker;
        $this->worker = LoadingWorker::create(['code' => 'A2', 'name' => 'Karyawan Lain', 'is_active' => true]);
        $other = $this->weigh('400');
        $otherLoader = $other->loaders()->sole()->id;
        $this->worker = $originalWorker;
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        $this->price = PriceSchedule::create(['business_date' => '2026-10-10', 'effective_from' => '2026-10-09 17:00:00',
            'tbs_buy_price_idr' => 3000, 'sort_buy_price_idr' => 2000, 'loading_rate_idr' => 100, 'published_by' => $this->manager->id]);
        $outside = $this->weigh('200');
        $outsideLoader = $outside->loaders()->sole()->id;
        $this->worker->update(['is_active' => false]);
        PriceSchedule::create(['business_date' => '2026-10-10', 'effective_from' => '2026-10-10 03:00:00',
            'tbs_buy_price_idr' => 2900, 'sort_buy_price_idr' => 1900, 'loading_rate_idr' => 500, 'published_by' => $this->manager->id]);
        $this->actingAs($this->cashier)->get(route('payroll.payee', ['recipient' => $this->worker->id, 'from' => '2026-10-09', 'to' => '2026-10-09']))
            ->assertInertia(fn (Assert $p) => $p->where('totals.total_idr', 150000)->where('totals.paid_idr', 50000)
                ->where('totals.remaining_idr', 100000)->where('totals.ticket_count', 2)->where('totals.outstanding_count', 2));
        $this->get(route('payroll.index'))->assertInertia(fn (Assert $p) => $p->where('cards.data.0.first_work_date', '2026-10-09')
            ->where('cards.data.0.last_work_date', '2026-10-10')->where('cards.data.0.remaining_idr', 120000));
        $this->post(route('payroll-payments.store'), $this->automaticPayroll(['amount_idr' => 75000, 'occurred_at' => '2026-10-10T10:00:00']))->assertSessionHasNoErrors();
        $payment = PayrollPayment::latest('id')->firstOrFail();
        $this->assertDatabaseHas('payroll_payment_allocations', ['payroll_payment_id' => $payment->id, 'weighing_loader_id' => $firstLoader, 'amount_idr' => 50000]);
        $this->assertDatabaseHas('payroll_payment_allocations', ['payroll_payment_id' => $payment->id, 'weighing_loader_id' => $secondLoader, 'amount_idr' => 25000]);
        foreach ([$outsideLoader, $otherLoader] as $loader) {
            $this->assertDatabaseMissing('payroll_payment_allocations', ['weighing_loader_id' => $loader]);
        }
        $this->post(route('payroll-payments.store'), $this->automaticPayroll(['amount_idr' => 25000, 'expected_remaining_idr' => 25000, 'occurred_at' => '2026-10-10T10:00:00']))->assertSessionHasNoErrors();
        $last = PayrollPayment::latest('id')->firstOrFail();
        $this->assertDatabaseMissing('payroll_payment_allocations', ['payroll_payment_id' => $last->id, 'weighing_loader_id' => $firstLoader]);
        $this->reverse(FinanceKind::Payroll, $last);
        $this->actingAs($this->cashier)->get(route('payroll.payee', ['recipient' => $this->worker->id, 'from' => '2026-10-09', 'to' => '2026-10-09']))
            ->assertInertia(fn (Assert $p) => $p->where('totals.remaining_idr', 25000)->where('totals.outstanding_count', 1)
                ->has('rows.data', 1)->where('rows.data.0.id', $secondLoader)
                ->where('rows.data.0.rate_idr', 100)->where('rows.data.0.remaining_idr', 25000));
        $this->assertDatabaseCount('profit_entries', 0);
        $this->assertSame(9875000, app(CashLedger::class)->balance($this->cash->id));
    }

    public function test_automatic_payroll_covers_all_pages_and_more_than_two_hundred_tickets_with_exact_replay(): void
    {
        for ($i = 0; $i < 201; $i++) {
            $this->weigh('1');
        }
        $this->capital();
        $this->actingAs($this->cashier)->get(route('payroll.payee', ['recipient' => $this->worker->id, 'from' => '2026-10-09', 'to' => '2026-10-09', 'page' => 2]))
            ->assertInertia(fn (Assert $p) => $p->has('rows.data', 50)->where('rows.current_page', 2)->where('rows.total', 201)
                ->where('totals.ticket_count', 201)->where('totals.outstanding_count', 201)->where('totals.remaining_idr', 20100));
        $payload = $this->automaticPayroll(['amount_idr' => 20100, 'expected_remaining_idr' => 20100]);
        $this->post(route('payroll-payments.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('payroll-payments.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payroll_payments', 1);
        $this->assertDatabaseCount('payroll_payment_allocations', 201);
        $this->assertDatabaseCount('cash_entries', 2);
        $this->assertSame(20100, (int) DB::table('payroll_payment_allocations')->sum('amount_idr'));
        $this->assertSame(9979900, app(CashLedger::class)->balance($this->cash->id));
        $this->get(route('payroll.payee', ['recipient' => $this->worker->id, 'from' => '2026-10-09', 'to' => '2026-10-09', 'page' => 2]))
            ->assertInertia(fn (Assert $p) => $p->where('totals.remaining_idr', 0)->where('totals.outstanding_count', 0)
                ->has('rows.data', 0)->where('rows.total', 0)->has('history.data', 1));
        $this->post(route('payroll-payments.store'), array_replace($payload, ['amount_idr' => 1]))->assertSessionHasErrors('operation');
    }

    public function test_automatic_payroll_rejects_new_tickets_and_concurrent_balance_changes_without_partial_writes(): void
    {
        $this->weigh();
        $this->capital();
        $old = $this->automaticPayroll();
        $this->weigh('500');
        $this->actingAs($this->cashier)->post(route('payroll-payments.store'), $old)->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertDatabaseCount('payroll_payment_allocations', 0);
        $this->assertDatabaseCount('cash_entries', 1);
        $fresh = $this->automaticPayroll(['amount_idr' => 100000, 'expected_remaining_idr' => 150000]);
        $this->post(route('payroll-payments.store'), $fresh)->assertSessionHasNoErrors();
        $this->post(route('payroll-payments.store'), $this->automaticPayroll(['amount_idr' => 100000, 'expected_remaining_idr' => 150000]))->assertSessionHasErrors('operation');
        $this->post(route('payroll-payments.store'), $this->automaticPayroll(['amount_idr' => 50001, 'expected_remaining_idr' => 50000]))->assertSessionHasErrors('amount_idr');
        $this->assertDatabaseCount('payroll_payments', 1);
        $this->assertDatabaseCount('payroll_payment_allocations', 1);
        $this->assertDatabaseCount('cash_entries', 2);
        $this->assertSame(9900000, app(CashLedger::class)->balance($this->cash->id));
    }

    public function test_automatic_payroll_validates_mode_period_role_and_payment_time(): void
    {
        $ticket = $this->weigh();
        $this->capital();
        $payload = $this->automaticPayroll();
        $this->actingAs($this->driver)->get(route('payroll.index'))->assertForbidden();
        $this->get(route('payroll.payee', $this->worker->id))->assertForbidden();
        $this->post(route('payroll-payments.store'), $payload)->assertForbidden();
        $this->actingAs($this->cashier)->post(route('payroll-payments.store'), array_replace($payload, ['selected_ids' => [$ticket->loaders()->sole()->id]]))->assertSessionHasErrors('selected_ids');
        $this->post(route('payroll-payments.store'), array_replace($payload, ['all_in_period' => false]))->assertSessionHasErrors('selected_ids');
        $this->post(route('payroll-payments.store'), array_replace($payload, ['all_in_period' => 'yes']))->assertSessionHasErrors('all_in_period');
        $this->post(route('payroll-payments.store'), array_replace($payload, ['period_end' => '2026-10-08']))->assertSessionHasErrors('period_end');
        $this->post(route('payroll-payments.store'), array_replace($payload, ['occurred_at' => '2026-10-09T09:30:00']))->assertSessionHasErrors('operation');
        $this->post(route('customer-payments.store'), $this->payment($ticket, ['all_in_period' => true]))->assertSessionHasErrors('all_in_period');
        $this->post(route('payroll-payments.store'), array_replace($payload, ['period_start' => '2026-10-08', 'period_end' => '2026-10-08']))->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertDatabaseCount('cash_entries', 1);
    }

    public function test_paid_customer_and_payroll_dependencies_block_void_until_both_reversed(): void
    {
        $ticket = $this->weigh();
        $this->capital();
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment($ticket))->assertSessionHasNoErrors();
        $this->post(route('payroll-payments.store'), $this->key(['recipient_id' => $this->worker->id, 'selected_ids' => [$ticket->loaders()->sole()->id], 'amount_idr' => 100000, 'expected_remaining_idr' => 100000,
            'cash_account_id' => $this->cash->id, 'method' => 'cash', 'period_start' => '2026-10-09', 'period_end' => '2026-10-09', 'occurred_at' => '2026-10-09T10:00:00']))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('weighings.void', $ticket), $this->key(['version' => $ticket->version, 'reason' => 'Koreksi']))->assertSessionHasErrors('operation');
        $this->reverse(FinanceKind::Customer, CustomerPayment::firstOrFail());
        $this->post(route('weighings.void', $ticket), $this->key(['version' => $ticket->version, 'reason' => 'Koreksi']))->assertSessionHasErrors('operation');
        $this->reverse(FinanceKind::Payroll, PayrollPayment::firstOrFail());
        $this->post(route('weighings.void', $ticket), $this->key(['version' => $ticket->version, 'reason' => 'Koreksi']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('customer_payment_allocations', 1);
        $this->assertDatabaseCount('payroll_payment_allocations', 1);
    }

    public function test_payroll_default_period_follows_jakarta_month_at_utc_boundary(): void
    {
        $this->travelTo(Carbon::parse('2026-10-31 18:00:00', 'UTC'));
        $this->actingAs($this->cashier)->get(route('payroll.payee', $this->worker->id))->assertInertia(fn (Assert $p) => $p->where('filters.from', '2026-11-01')->where('filters.to', '2026-11-01'));
    }
}
