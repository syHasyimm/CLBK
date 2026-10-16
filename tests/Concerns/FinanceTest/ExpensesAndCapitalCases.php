<?php

namespace Tests\Concerns\FinanceTest;

use App\Actions\Finance\PostFinance;
use App\Enums\FinanceKind;
use App\Models\DailyExpense;
use App\Models\ExpenseCategory;
use App\Services\AuditService;
use App\Services\CashLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait ExpensesAndCapitalCases
{
    public function test_expense_records_cost_once_and_reverses_today_without_changing_yesterday(): void
    {
        $this->capital();
        $data = $this->key(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'cash_account_id' => $this->cash->id, 'amount_idr' => 100000,
            'method' => 'cash', 'description' => 'BBM operasional', 'occurred_at' => '2026-10-09T10:00:00']);
        $this->actingAs($this->cashier)->post(route('daily-expenses.store'), $data)->assertSessionHasNoErrors();
        $record = DailyExpense::firstOrFail();
        $this->post(route('daily-expenses.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('daily_expenses', 1);
        $this->assertDatabaseCount('profit_entries', 1);
        $this->assertSame(9900000, app(CashLedger::class)->balance($this->cash->id));
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        $this->reverse(FinanceKind::Expense, $record);
        $this->assertSame(100000, (int) DB::table('profit_entries')->where('business_date', '2026-10-09')->sum('amount_delta_idr'));
        $this->assertSame(-100000, (int) DB::table('profit_entries')->where('business_date', '2026-10-10')->sum('amount_delta_idr'));
        $this->assertSame(10000000, app(CashLedger::class)->balance($this->cash->id));
        $this->assertNull(DB::table('profit_entries')->whereNotNull('reversal_of_id')->value('daily_expense_id'));
    }

    public function test_manual_capital_withdrawal_and_reversal_never_change_profit_and_cannot_overdraw(): void
    {
        $this->capital(1000000);
        $this->post(route('cash.store'), $this->key(['cash_account_id' => $this->cash->id, 'kind' => 'opening', 'amount_idr' => 1, 'occurred_at' => '2026-10-09T10:00:00', 'reason' => 'Awal lagi']))->assertSessionHasErrors('kind');
        $this->post(route('cash.store'), $this->key(['cash_account_id' => $this->cash->id, 'kind' => 'withdrawal', 'amount_idr' => 1000001, 'occurred_at' => '2026-10-09T10:00:00', 'reason' => 'Tarik']))->assertSessionHasErrors('amount_idr');
        $this->post(route('cash.store'), $this->key(['cash_account_id' => $this->cash->id, 'kind' => 'withdrawal', 'amount_idr' => 200000, 'occurred_at' => '2026-10-09T10:00:00', 'reason' => 'Tarik']))->assertSessionHasNoErrors();
        $withdrawal = DB::table('cash_entries')->where('kind', 'withdrawal')->sole();
        $this->post(route('cash.reverse', $withdrawal->id), $this->key(['reason' => 'Koreksi', 'return_reference' => 'Pemilik mengembalikan tunai']))->assertSessionHasNoErrors();
        $this->assertSame(1000000, app(CashLedger::class)->balance($this->cash->id));
        $this->assertDatabaseCount('profit_entries', 0);
    }

    public function test_cash_chronology_future_dates_and_source_dates_are_validated(): void
    {
        $ticket = $this->weigh();
        $this->capital();
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $this->payment($ticket, ['occurred_at' => '2026-10-09T10:01:00']))->assertSessionHasErrors('occurred_at');
        $this->post(route('customer-payments.store'), $this->payment($ticket, ['occurred_at' => '2026-10-09T09:30:00']))->assertSessionHasErrors('selected_ids');
        $this->post(route('customer-payments.store'), $this->payment($ticket, ['selected_ids' => [$ticket->id, $ticket->id]]))->assertSessionHasErrors('selected_ids.0');
        $this->post(route('customer-payments.store'), $this->payment($ticket))->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        $this->post(route('daily-expenses.store'), $this->key(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'cash_account_id' => $this->cash->id, 'method' => 'cash', 'amount_idr' => 1, 'description' => 'Biaya lama', 'occurred_at' => '2026-10-09T09:30:00']))->assertSessionHasErrors('backdate_reason');
        $this->post(route('daily-expenses.store'), $this->key(['expense_category_id' => ExpenseCategory::firstOrFail()->id, 'cash_account_id' => $this->cash->id, 'method' => 'cash', 'amount_idr' => 1, 'description' => 'Biaya lama', 'occurred_at' => '2026-10-09T09:30:00', 'backdate_reason' => 'Nota terlambat']))->assertSessionHasErrors('occurred_at');
    }

    public function test_expense_failure_rolls_back_cost_cash_and_document_number(): void
    {
        $this->capital();
        $this->mock(AuditService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn([]);
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('expense audit failure'));
        });
        try {
            app(PostFinance::class)->handle($this->cashier, FinanceKind::Expense, $this->key(['cash_account_id' => $this->cash->id, 'amount_idr' => 100000, 'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00', 'expense_category_id' => ExpenseCategory::firstOrFail()->id, 'description' => 'BBM']));
            $this->fail('Expected expense rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('expense audit failure', $e->getMessage());
        }
        $this->assertDatabaseCount('daily_expenses', 0);
        $this->assertDatabaseCount('profit_entries', 0);
        $this->assertDatabaseCount('cash_entries', 1);
        $this->assertDatabaseMissing('document_sequences', ['name' => 'BIAYA:2026-10-09']);
    }
}
