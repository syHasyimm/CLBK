<?php

namespace Tests\Concerns\DirectSalesAndSummaryTest;

use App\Actions\Finance\PostFinance;
use App\Enums\FinanceKind;
use App\Models\CashAccount;
use App\Models\SaleReceipt;
use App\Queries\PaymentBalances;
use App\Services\CashLedger;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

trait DirectSaleReceiptsCases
{
    public function test_transfer_receipts_require_reference_and_credit_only_business_cash(): void
    {
        $bank = CashAccount::create(['code' => 'BANK', 'name' => 'Bank Usaha', 'type' => 'bank', 'is_active' => true]);
        foreach (['factory', 'sort'] as $kind) {
            $sale = $this->save($kind);
            $data = ['sale_kind' => $kind, 'sale_id' => $sale->id, 'cash_account_id' => $this->cash->id,
                'amount_idr' => $sale->revenue_idr, 'expected_remaining_idr' => $sale->revenue_idr, 'method' => 'bank_transfer',
                'reference' => '   ', 'occurred_at' => '2026-10-09T10:00:00', 'idempotency_key' => (string) Str::uuid()];
            $this->actingAs($this->cashier)->post(route('sale-receipts.store'), $data)->assertSessionHasErrors('reference');
            $data['reference'] = '0';
            $this->post(route('sale-receipts.store'), $data)->assertSessionHasNoErrors();
            $this->post(route('sale-receipts.store'), $data)->assertSessionHasNoErrors();
            $this->post(route('sale-receipts.store'), array_replace($data, ['cash_account_id' => $bank->id]))->assertSessionHasErrors('operation');
            $this->post(route('sale-receipts.store'), array_replace($data, ['cash_account_id' => $bank->id, 'idempotency_key' => (string) Str::uuid()]))->assertSessionHasErrors('cash_account_id');
            $this->post(route('sale-receipts.store'), array_replace($data, ['reference' => 'BERUBAH']))->assertSessionHasErrors('operation');
        }
        $this->assertSame(0, app(CashLedger::class)->balance($bank->id));
        $this->assertSame(3209010, app(CashLedger::class)->balance($this->cash->id));
        $this->assertDatabaseCount('cash_entries', 2);
        $this->assertDatabaseCount('sale_receipts', 2);
        foreach (SaleReceipt::all() as $receipt) {
            $this->assertFalse($receipt->is_automatic);
            $this->assertSame('bank_transfer', $receipt->method);
            $this->assertSame('0', $receipt->reference);
            $this->assertSame($this->cash->id, $receipt->cash_account_id);
        }
    }

    public function test_inactive_or_wrong_account_rejects_receipts_without_changing_sales_or_cash(): void
    {
        $this->cash->update(['is_active' => false]);
        $bank = CashAccount::create(['code' => 'BANK', 'name' => 'Bank', 'type' => 'bank', 'is_active' => true]);
        foreach (['factory', 'sort'] as $kind) {
            $sale = $this->save($kind);
            foreach ([$this->cash->id, $bank->id] as $accountId) {
                $this->actingAs($this->cashier)->post(route('sale-receipts.store'), ['sale_kind' => $kind, 'sale_id' => $sale->id,
                    'cash_account_id' => $accountId, 'amount_idr' => 1, 'expected_remaining_idr' => $sale->revenue_idr,
                    'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('cash_account_id');
            }
            $this->assertSame('posted', $sale->fresh()->status);
            $this->assertSame(0, app(PaymentBalances::class)->salePaid($sale));
        }
        $this->assertDatabaseCount('sale_receipts', 0);
        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertDatabaseCount('idempotency_requests', 2);
    }

    public function test_a_zero_net_sale_is_paid_without_an_invalid_zero_cash_entry(): void
    {
        $sale = $this->save(changes: ['accepted_kg' => '1', 'deduction_idr' => 3500]);
        $this->assertSame(0, $sale->revenue_idr);
        $this->assertDatabaseCount('sale_receipts', 0);
        $this->assertDatabaseCount('cash_entries', 0);
        $this->get(route('factory-sales.show', $sale->id))->assertInertia(fn (Assert $p) => $p->where('record.payment_status', 'paid')->where('record.receivable_idr', 0));
    }

    public function test_both_sales_need_no_cash_account_and_reject_automatic_cash_fields(): void
    {
        $this->cash->update(['is_active' => false]);
        foreach (['factory', 'sort'] as $kind) {
            $sale = $this->save($kind);
            $this->post(route($kind.'-sales.store'), $this->payload($kind, ['cash_account_id' => $this->cash->id, 'payment_reference' => 'Tidak otomatis']))->assertSessionHasErrors(['cash_account_id', 'payment_reference']);
            $this->post('/'.$kind.'-sales/'.$sale->id.'/settle', [])->assertNotFound();
            $this->get(route($kind.'-sales.create'))->assertInertia(fn (Assert $p) => $p->missing('cashAccounts'));
        }
        $this->assertDatabaseCount('factory_sales', 1);
        $this->assertDatabaseCount('sort_sales', 1);
        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertDatabaseCount('sale_receipts', 0);
    }

    public function test_both_kinds_accept_partial_and_full_receipts_without_confusing_ids_or_duplicating_revenue(): void
    {
        $factory = $this->save();
        $sort = $this->save('sort', ['buyer_name' => 'Pembeli Sortir Baru', 'buyer_contact' => '081234']);
        $this->assertSame($factory->id, $sort->id);
        $profit = DB::table('profit_entries')->orderBy('id')->get()->toArray();
        $this->assertSame(0, app(CashLedger::class)->balance($this->cash->id));
        $this->actingAs($this->driver)->get(route('sale-receipts.index'))->assertForbidden();
        $this->get(route('sale-receipts.create', ['sale_kind' => 'sort', 'sale_id' => $sort->id]))->assertForbidden();
        $this->post(route('sale-receipts.store'), [])->assertForbidden();
        $this->actingAs($this->cashier)->get(route('sale-receipts.index'))->assertInertia(fn (Assert $p) => $p
            ->component('Finance/SaleReceipts')->has('factorySales.data', 1)->has('sortSales.data', 1)->has('documents.data', 0));
        $cash = 0;
        foreach ([$factory, $sort] as $sale) {
            $kind = $sale->kind();
            $this->actingAs($this->cashier)->get(route('sale-receipts.create', ['sale_kind' => $kind, 'sale_id' => $sale->id]))->assertInertia(fn (Assert $p) => $p
                ->component('Finance/Form')->where('sale.kind', $kind)->where('sale.number', $sale->number)->where('sale.remaining_idr', $sale->revenue_idr));
            $data = ['sale_kind' => $kind, 'sale_id' => $sale->id, 'cash_account_id' => $this->cash->id,
                'amount_idr' => 10000, 'expected_remaining_idr' => $sale->revenue_idr, 'method' => 'cash',
                'occurred_at' => '2026-10-09T10:00:00', 'idempotency_key' => (string) Str::uuid()];
            $this->post(route('sale-receipts.store'), $data)->assertSessionHasNoErrors();
            $this->post(route('sale-receipts.store'), $data)->assertSessionHasNoErrors();
            $receipt = SaleReceipt::where($kind.'_sale_id', $sale->id)->sole();
            $this->assertFalse($receipt->is_automatic);
            $this->assertNull($receipt->{($kind === 'factory' ? 'sort' : 'factory').'_sale_id'});
            $cash += 10000;
            $this->assertSame($cash, app(CashLedger::class)->balance($this->cash->id));
            $remaining = $sale->revenue_idr - 10000;
            $this->get(route('sale-receipts.index', ['status' => 'partial', 'q' => $sale->number]))->assertInertia(fn (Assert $p) => $p
                ->has($kind.'Sales.data', 1)->where($kind.'Sales.data.0.payment_status', 'partial')->where($kind.'Sales.data.0.receivable_idr', $remaining)->has('documents.data', 1));
            $this->post(route('sale-receipts.store'), array_replace($data, ['idempotency_key' => (string) Str::uuid()]))->assertSessionHasErrors('operation');
            $this->post(route('sale-receipts.store'), array_replace($data, ['amount_idr' => $remaining + 1, 'expected_remaining_idr' => $remaining, 'idempotency_key' => (string) Str::uuid()]))->assertSessionHasErrors('operation');
            $this->post(route('sale-receipts.store'), array_replace($data, ['amount_idr' => $remaining, 'expected_remaining_idr' => $remaining, 'idempotency_key' => (string) Str::uuid()]))->assertSessionHasNoErrors();
            $cash += $remaining;
            $this->get(route('sale-receipts.index', ['status' => 'paid', 'q' => $sale->number]))->assertInertia(fn (Assert $p) => $p
                ->has($kind.'Sales.data', 1)->where($kind.'Sales.data.0.receivable_idr', 0)->has('documents.data', 2));
            $this->get(route('sale-receipts.create', ['sale_kind' => $kind, 'sale_id' => $sale->id]))->assertUnprocessable();
            $this->actingAs($this->manager)->post(route('finance.reverse', ['kind' => 'receipt', 'document' => $receipt->id]),
                ['version' => 1, 'reason' => 'Koreksi penerimaan', 'return_reference' => 'Tunai kembali', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
            $cash -= 10000;
            $this->assertSame($cash, app(CashLedger::class)->balance($this->cash->id));
            $this->get(route($kind.'-sales.show', $sale->id))->assertInertia(fn (Assert $p) => $p->where('record.receivable_idr', 10000)->where('canReceive', true));
        }
        $this->assertEquals($profit, DB::table('profit_entries')->orderBy('id')->get()->toArray());
        $this->get(route('sale-receipts.index', ['q' => 'Pembeli Sortir Baru']))->assertInertia(fn (Assert $p) => $p
            ->has('factorySales.data', 0)->has('sortSales.data', 1)->has('documents.data', 2));
        $this->get(route('sale-receipts.index', ['q' => '081234']))->assertInertia(fn (Assert $p) => $p->has('sortSales.data', 1)->has('documents.data', 2));
        $this->get(route('sale-receipts.index', ['status' => '']))->assertInertia(fn (Assert $p) => $p
            ->has('factorySales.data', 1)->has('sortSales.data', 1)->has('documents.data', 4));
        $this->get(route('sale-receipts.index', ['from' => '2026-10-10']))->assertInertia(fn (Assert $p) => $p
            ->has('factorySales.data', 0)->has('sortSales.data', 0)->has('documents.data', 0));
        $this->assertDatabaseCount('cash_entries', 6);
        $this->assertForeignKeysAreValid();
    }

    public function test_receipt_action_rejects_invalid_kinds_and_nonpositive_amounts_atomically(): void
    {
        $sale = $this->save('sort');
        foreach ([['sale_kind' => 'unknown'], ['amount_idr' => 0], ['amount_idr' => -1]] as $changes) {
            try {
                app(PostFinance::class)->handle($this->cashier, FinanceKind::Receipt, array_replace(['sale_kind' => 'sort', 'sale_id' => $sale->id,
                    'cash_account_id' => $this->cash->id, 'amount_idr' => 1, 'expected_remaining_idr' => 60010,
                    'method' => 'cash', 'occurred_at' => '2026-10-09T10:00:00', 'idempotency_key' => (string) Str::uuid()], $changes));
                $this->fail('Invalid receipt accepted');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey(isset($changes['sale_kind']) ? 'sale_kind' : 'operation', $error->errors());
            }
        }
        $this->assertDatabaseCount('sale_receipts', 0);
        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertDatabaseCount('idempotency_requests', 1);
    }

    public function test_existing_automatic_receipts_of_both_kinds_remain_paid_and_can_be_corrected(): void
    {
        foreach (['factory', 'sort'] as $kind) {
            $sale = $this->save($kind);
            $time = app(BusinessClock::class)->now();
            $receipt = new SaleReceipt(['number' => 'TERIMA-LAMA-'.$kind, $kind.'_sale_id' => $sale->id,
                'cash_account_id' => $this->cash->id, 'amount_idr' => $sale->revenue_idr, 'method' => 'cash',
                'status' => 'posted', 'occurred_at' => $time, 'posted_at' => $time, 'business_date' => '2026-10-09', 'created_by' => $this->driver->id]);
            $receipt->is_automatic = true;
            $receipt->save();
            DB::table('cash_entries')->insert(['cash_account_id' => $this->cash->id, 'direction' => 'in', 'kind' => 'receipt',
                'sale_receipt_id' => $receipt->id, 'amount_idr' => $sale->revenue_idr, 'occurred_at' => $time,
                'business_date' => '2026-10-09', 'created_by' => $this->driver->id]);
            $this->actingAs($this->manager)->get(route('sale-receipts.index', ['status' => 'paid', 'q' => $sale->number]))->assertInertia(fn (Assert $p) => $p
                ->has($kind.'Sales.data', 1)->where($kind.'Sales.data.0.receivable_idr', 0)->has('documents.data', 1)->where('documents.data.0.is_automatic', true));
            $this->get(route('finance.show', ['kind' => 'receipt', 'document' => $receipt->id]))->assertInertia(fn (Assert $p) => $p->where('canReverse', true)->where('source.kind', $kind));
            $this->post(route('finance.reverse', ['kind' => 'receipt', 'document' => $receipt->id]),
                ['version' => 1, 'reason' => 'Koreksi penerimaan lama', 'return_reference' => 'Dana kembali', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
            $this->assertSame('posted', $sale->fresh()->status);
            $this->assertSame('reversed', $receipt->fresh()->status);
            $this->assertTrue($receipt->fresh()->is_automatic);
            $this->assertSame(0, app(CashLedger::class)->balance($this->cash->id));
            $this->get(route('sale-receipts.index', ['q' => $sale->number]))->assertInertia(fn (Assert $p) => $p
                ->has($kind.'Sales.data', 1)->where($kind.'Sales.data.0.receivable_idr', $sale->revenue_idr));
        }
        $this->assertDatabaseCount('cash_entries', 4);
    }
}
