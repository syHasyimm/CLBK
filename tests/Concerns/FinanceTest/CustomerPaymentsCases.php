<?php

namespace Tests\Concerns\FinanceTest;

use App\Enums\FinanceKind;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PriceSchedule;
use App\Queries\PaymentBalances;
use App\Services\CashLedger;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

trait CustomerPaymentsCases
{
    public function test_legacy_partial_payment_is_fully_settled_using_old_snapshot_after_price_change(): void
    {
        $ticket = $this->weigh();
        $this->capital();
        $this->legacyCustomerPayment($this->cashier, $this->cash, [$ticket->id => 1000000]);
        $this->actingAs($this->cashier);
        $this->assertSame(1000000, app(PaymentBalances::class)->customerPaid($ticket->id));
        $this->get(route('weighings.show', $ticket))->assertInertia(fn (Assert $p) => $p->where('record.payment_status', 'partial')->where('record.remaining_idr', 2000000));
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        PriceSchedule::create(['business_date' => '2026-10-10', 'effective_from' => '2026-10-09 17:00:00', 'tbs_buy_price_idr' => 2900, 'sort_buy_price_idr' => 1900, 'loading_rate_idr' => 90, 'published_by' => $this->manager->id]);
        $this->post(route('customer-payments.store'), $this->payment($ticket, ['amount_idr' => 2000000, 'expected_remaining_idr' => 2000000, 'occurred_at' => '2026-10-10T10:00:00']))->assertSessionHasNoErrors();
        $this->assertSame(3000000, $ticket->fresh()->total_purchase_idr);
        $this->assertSame(3000000, app(PaymentBalances::class)->customerPaid($ticket->id));
        $this->assertSame(7000000, app(CashLedger::class)->balance($this->cash->id));
        $this->assertDatabaseCount('customer_payments', 2);
        $this->assertDatabaseCount('profit_entries', 0);
        $this->get(route('customer-payments.index', ['status' => 'paid']))->assertInertia(fn (Assert $p) => $p->has('cards.data', 0)->where('filters.status', 'unpaid'));
        $this->get(route('customer-payments.payee', $this->customer))->assertInertia(fn (Assert $p) => $p->has('rows.data', 0)->where('totals.remaining_idr', 0)->has('history.data', 2));
        $this->assertSame(3000, $ticket->items()->firstOrFail()->buy_price_snapshot_idr);
    }

    public function test_customer_cards_and_payment_review_show_only_outstanding_tickets_for_cashier_and_manager(): void
    {
        $partial = $this->weigh();
        $unpaid = $this->weigh('500');
        $paidIds = [];
        for ($i = 0; $i < 4; $i++) {
            $paidIds[] = $this->weigh('1')->id;
        }
        $this->capital();
        $this->legacyCustomerPayment($this->cashier, $this->cash, [$partial->id => 1000000]);
        $this->legacyCustomerPayment($this->cashier, $this->cash, array_fill_keys($paidIds, 3000));

        foreach ([$this->cashier, $this->manager] as $actor) {
            $this->actingAs($actor)->get(route('customer-payments.index'))->assertInertia(fn (Assert $p) => $p
                ->has('cards.data', 1)->where('cards.data.0.ticket_count', 2)
                ->where('cards.data.0.total_idr', 4500000)->where('cards.data.0.paid_idr', 1000000)
                ->where('cards.data.0.remaining_idr', 3500000)->has('cards.data.0.tickets', 2)
                ->where('cards.data.0.tickets', fn ($rows) => collect($rows)->pluck('weighing_id')->sort()->values()->all() === [$partial->id, $unpaid->id]));
            $this->get(route('customer-payments.payee', $this->customer))->assertInertia(fn (Assert $p) => $p
                ->has('rows.data', 2)->where('rows.total', 2)->where('rows.data.0.id', $partial->id)
                ->where('rows.data.0.remaining_idr', 2000000)->where('rows.data.1.id', $unpaid->id)
                ->where('totals.ticket_count', 6)->where('totals.outstanding_count', 2)
                ->where('totals.remaining_idr', 3500000)->has('history.data', 2));
            $this->get(route('customer-payments.index', ['from' => '2026-10-10']))->assertInertia(fn (Assert $p) => $p->has('cards.data', 0));
        }

        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment($partial, [
            'selected_ids' => [$partial->id, $unpaid->id], 'amount_idr' => 3500000, 'expected_remaining_idr' => 3500000,
        ]))->assertSessionHasNoErrors();
        $finalPayment = CustomerPayment::latest('id')->firstOrFail();
        foreach ([$this->cashier, $this->manager] as $actor) {
            $this->actingAs($actor)->get(route('customer-payments.index'))->assertInertia(fn (Assert $p) => $p->has('cards.data', 0));
            $this->get(route('customer-payments.payee', $this->customer))->assertInertia(fn (Assert $p) => $p
                ->has('rows.data', 0)->where('rows.total', 0)->where('totals.remaining_idr', 0)->has('history.data', 3));
        }

        $this->reverse(FinanceKind::Customer, $finalPayment);
        $this->actingAs($this->cashier)->get(route('customer-payments.payee', $this->customer))->assertInertia(fn (Assert $p) => $p
            ->has('rows.data', 2)->where('totals.remaining_idr', 3500000)->has('history.data', 3)->where('history.data.0.status', 'reversed'));
        $this->get(route('customer-payments.index'))->assertInertia(fn (Assert $p) => $p
            ->has('cards.data', 1)->where('cards.data.0.remaining_idr', 3500000)->has('cards.data.0.tickets', 2));
        $this->assertDatabaseCount('customer_payments', 3);
    }

    public function test_customer_review_filters_paid_tickets_before_pagination(): void
    {
        $paidIds = [];
        for ($i = 0; $i < 52; $i++) {
            $paidIds[] = $this->weigh('1')->id;
        }
        $outstanding = $this->weigh('2');
        $this->capital();
        $this->legacyCustomerPayment($this->cashier, $this->cash, array_fill_keys($paidIds, 3000));
        foreach ([$this->cashier, $this->manager] as $actor) {
            $this->actingAs($actor)->get(route('customer-payments.payee', $this->customer))->assertInertia(fn (Assert $p) => $p
                ->has('rows.data', 1)->where('rows.data.0.id', $outstanding->id)->where('rows.total', 1)->where('rows.last_page', 1)
                ->where('totals.ticket_count', 53)->where('totals.paid_idr', 156000)->where('totals.remaining_idr', 6000)->has('history.data', 1));
            $this->get(route('customer-payments.index'))->assertInertia(fn (Assert $p) => $p
                ->has('cards.data', 1)->where('cards.data.0.ticket_count', 1)->has('cards.data.0.tickets', 1)
                ->where('cards.data.0.tickets.0.weighing_id', $outstanding->id));
        }
    }

    public function test_payment_fully_settles_all_customer_tickets_and_rejects_foreign_references(): void
    {
        $first = $this->weigh();
        $this->travelTo(Carbon::parse('2026-10-09 04:00:00', 'UTC'));
        $second = $this->weigh('500');
        $other = $this->weigh('1000', Customer::create(['code' => 'K2', 'name' => 'Konsumen Lain', 'is_active' => true]));
        $this->capital();
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment($first, ['selected_ids' => [$other->id, $first->id], 'occurred_at' => '2026-10-09T11:00:00']))->assertSessionHasErrors('selected_ids');
        $this->post(route('customer-payments.store'), $this->payment($first, ['selected_ids' => [$first->id], 'expected_remaining_idr' => 4500000, 'amount_idr' => 4500000, 'occurred_at' => '2026-10-09T11:00:00']))->assertSessionHasNoErrors();
        $this->assertSame(3000000, app(PaymentBalances::class)->customerPaid($first->id));
        $this->assertSame(1500000, app(PaymentBalances::class)->customerPaid($second->id));
        $this->assertSame(0, app(PaymentBalances::class)->customerPaid($other->id));
    }

    public function test_overpayment_missing_cash_and_stale_balance_leave_no_partial_voucher(): void
    {
        $ticket = $this->weigh();
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment($ticket))->assertSessionHasErrors('amount_idr');
        $this->capital();
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment($ticket, ['amount_idr' => 3000001]))->assertSessionHasErrors('amount_idr');
        $this->post(route('customer-payments.store'), $this->payment($ticket))->assertSessionHasNoErrors();
        $this->post(route('customer-payments.store'), $this->payment($ticket, ['amount_idr' => 3000000, 'expected_remaining_idr' => 3000000]))->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('customer_payments', 1);
        $this->assertDatabaseCount('customer_payment_allocations', 1);
        $this->assertDatabaseCount('cash_entries', 2);
    }

    public function test_same_key_replay_returns_same_voucher_and_changed_payload_is_rejected(): void
    {
        $ticket = $this->weigh();
        $this->capital();
        $payload = $this->payment($ticket);
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $payload)->assertSessionHasNoErrors();
        $record = CustomerPayment::firstOrFail();
        $this->post(route('customer-payments.store'), $payload)->assertRedirect(route('finance.show', ['kind' => 'customer', 'document' => $record->id]));
        $this->post(route('customer-payments.store'), array_replace($payload, ['amount_idr' => 1]))->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('customer_payments', 1);
        $this->assertDatabaseCount('cash_entries', 2);
    }
}
