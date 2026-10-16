<?php

namespace Tests\Concerns\FinanceTest;

use App\Domain\Inventory\Stock;
use App\Enums\FinanceKind;
use App\Models\CashAccount;
use App\Models\CustomerPayment;
use App\Models\SaleReceipt;
use App\Models\SortSale;
use App\Queries\PaymentBalances;
use App\Services\CashLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

trait SaleReceiptsCases
{
    public function test_receipt_partial_full_and_reversal_changes_cash_without_double_revenue(): void
    {
        $this->weigh();
        $sale = $this->factorySale();
        $profit = DB::table('profit_entries')->get()->toArray();
        $data = $this->key(['sale_kind' => 'factory', 'sale_id' => $sale->id, 'cash_account_id' => $this->cash->id, 'amount_idr' => 1000000,
            'expected_remaining_idr' => 3500000, 'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00']);
        $this->actingAs($this->cashier)->post(route('legacy-test.sale-receipts.store'), $data)->assertSessionHasNoErrors();
        $this->get(route('factory-sales.show', ['sale' => $sale->id]))->assertInertia(fn (Assert $p) => $p->where('record.receivable_idr', 2500000)->where('record.payment_status', 'partial')->missing('record.purchase_cost_released_idr'));
        $this->post(route('legacy-test.sale-receipts.store'), array_replace($data, ['amount_idr' => 2500000, 'expected_remaining_idr' => 2500000, 'idempotency_key' => (string) Str::uuid()]))->assertSessionHasNoErrors();
        $this->assertSame(3500000, app(CashLedger::class)->balance($this->cash->id));
        $this->actingAs($this->manager);
        $this->reverse(FinanceKind::Receipt, SaleReceipt::latest('id')->firstOrFail());
        $this->assertSame(1000000, app(PaymentBalances::class)->salePaid($sale));
        $this->assertEquals($profit, DB::table('profit_entries')->get()->toArray());
        $this->reverse(FinanceKind::Receipt, SaleReceipt::firstOrFail());
        $this->post(route('factory-sales.reverse', ['sale' => $sale->id]), $this->key(['version' => $sale->version, 'reason' => 'Koreksi']))->assertSessionHasNoErrors();
    }

    public function test_receipt_reversal_requires_enough_cash_and_inactive_account_still_allows_corrections(): void
    {
        $this->weigh();
        $sale = $this->factorySale();
        $this->actingAs($this->cashier)->post(route('legacy-test.sale-receipts.store'), $this->key(['sale_kind' => 'factory', 'sale_id' => $sale->id, 'cash_account_id' => $this->cash->id, 'amount_idr' => 1000000,
            'expected_remaining_idr' => 3500000, 'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00']))->assertSessionHasNoErrors();
        $receipt = SaleReceipt::firstOrFail();
        $this->actingAs($this->manager)->post(route('cash.store'), $this->key(['cash_account_id' => $this->cash->id, 'kind' => 'withdrawal', 'amount_idr' => 1000000, 'occurred_at' => '2026-10-09T10:00:00', 'reason' => 'Pemilik menarik']))->assertSessionHasNoErrors();
        $this->post(route('finance.reverse', ['kind' => 'receipt', 'document' => $receipt->id]), $this->key(['version' => 1, 'reason' => 'Koreksi', 'return_reference' => 'Transfer kembali']))->assertSessionHasErrors('amount_idr');
        $this->assertSame('posted', $receipt->fresh()->status);
        $withdrawal = DB::table('cash_entries')->where('kind', 'withdrawal')->sole();
        $this->post(route('cash.reverse', $withdrawal->id), $this->key(['reason' => 'Koreksi', 'return_reference' => 'Dana kembali']))->assertSessionHasNoErrors();
        $this->cash->update(['is_active' => false]);
        $this->reverse(FinanceKind::Receipt, $receipt);
        $this->assertSame(0, app(CashLedger::class)->balance($this->cash->id));
    }

    public function test_transfer_uses_business_cash_and_replays_without_duplicate_cash(): void
    {
        $ticket = $this->weigh();
        $bank = CashAccount::create(['code' => 'BANK', 'name' => 'Bank Usaha', 'type' => 'bank', 'is_active' => true]);
        $this->capital(3000000);
        $data = $this->payment($ticket, ['method' => 'bank_transfer', 'reference' => 'BANK-TF-001']);
        unset($data['cash_account_id']);
        $this->actingAs($this->cashier)->post(route('customer-payments.store'), $data)->assertSessionHasNoErrors();
        $this->post(route('customer-payments.store'), $data)->assertSessionHasNoErrors();
        $this->assertSame('BANK-TF-001', CustomerPayment::sole()->reference);
        $this->assertSame($this->cash->id, CustomerPayment::sole()->cash_account_id);
        $this->assertSame(0, app(CashLedger::class)->balance($bank->id));
        $this->assertSame(0, app(CashLedger::class)->balance($this->cash->id));
        $this->assertDatabaseCount('customer_payments', 1);
        $this->assertDatabaseCount('cash_entries', 2);
        $this->assertDatabaseCount('profit_entries', 0);
    }

    public function test_sort_sale_requires_separate_receipt_and_reverses_cash_without_affecting_factory_receivable(): void
    {
        $this->weigh('1000', null, '100');
        $factorySale = $this->factorySale();
        $this->post(route('legacy-test.sort-sales.store'), ['trip_id' => $this->trip->id, 'buyer_name' => 'Pembeli Sortir', 'sold_at' => '2026-10-09T10:00:00',
            'sold_kg' => '50', 'sell_price_snapshot_idr' => 2000, 'deduction_idr' => 0])->assertSessionHasNoErrors();
        $sortSale = SortSale::sole();
        $this->post(route('sort-sales.post', $sortSale->id), $this->key(['version' => $sortSale->version, 'preview_hash' => app(Stock::class)->preview($sortSale)['preview_hash']]))->assertSessionHasNoErrors();
        $profitCount = DB::table('profit_entries')->count();
        $this->assertSame(0, app(CashLedger::class)->balance($this->cash->id));
        $this->assertSame(0, app(PaymentBalances::class)->salePaid($sortSale));
        $this->actingAs($this->cashier)->post(route('sale-receipts.store'), $this->key(['sale_kind' => 'sort', 'sale_id' => $sortSale->id,
            'cash_account_id' => $this->cash->id, 'amount_idr' => 100000, 'expected_remaining_idr' => 100000,
            'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00']))->assertSessionHasNoErrors();
        $this->get(route('sort-sales.show', $sortSale->id))->assertInertia(fn (Assert $p) => $p->where('record.receivable_idr', 0)->where('record.payment_status', 'paid'));
        $this->assertSame(0, app(PaymentBalances::class)->salePaid($factorySale));
        $this->assertDatabaseCount('profit_entries', $profitCount);
        $this->assertSame(100000, app(CashLedger::class)->balance($this->cash->id));
        $this->actingAs($this->manager)->post(route('sort-sales.reverse', $sortSale->id), $this->key(['version' => $sortSale->fresh()->version, 'reason' => 'Koreksi nota']))->assertSessionHasNoErrors();
        $this->assertSame(0, app(CashLedger::class)->balance($this->cash->id));
        $this->assertSame(0, app(PaymentBalances::class)->salePaid($sortSale));
        $this->assertSame('reversed', SaleReceipt::sole()->status);
    }
}
