<?php

namespace Tests\Concerns\DirectSalesAndSummaryTest;

use App\Actions\Sales\ReverseSale;
use App\Enums\TransactionWorkflow;
use App\Models\FactorySale;
use App\Models\SaleReceipt;
use App\Models\SortSale;
use App\Services\AuditService;
use App\Services\CashLedger;
use App\Services\ProfitLedger;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

trait DirectSaleCorrectionsCases
{
    public function test_invoice_is_optional_unique_per_factory_and_can_be_reused_after_reversal(): void
    {
        $sale = $this->save(changes: ['invoice_number' => 'INV-1'], actor: $this->manager);
        $this->post(route('factory-sales.store'), $this->payload(changes: ['invoice_number' => 'INV-1']))->assertSessionHasErrors('invoice_number');
        app(ReverseSale::class)->handle($this->manager, $sale, ['version' => $sale->version, 'reason' => 'Salah catat', 'idempotency_key' => (string) Str::uuid()]);
        $new = $this->save(changes: ['invoice_number' => 'INV-1', 'replaces_sale_id' => $sale->id, 'correction_reason' => 'Perbaikan berat'], actor: $this->manager);
        $this->assertSame($sale->id, $new->replaces_sale_id);
        $this->post(route('factory-sales.store'), $this->payload(changes: ['replaces_sale_id' => $sale->id, 'correction_reason' => 'Ganda']))->assertSessionHasErrors('replaces_sale_id');
    }

    public function test_sale_reversal_automatically_reverses_cash_once_without_stock_or_trip(): void
    {
        foreach (['factory', 'sort'] as $kind) {
            $sale = $this->save($kind);
            $this->receive($sale);
            $receipt = SaleReceipt::where($kind.'_sale_id', $sale->id)->sole();
            $this->actingAs($this->cashier)->get(route($kind.'-sales.show', $sale->id))->assertInertia(fn (Assert $p) => $p->where('record.payment_status', 'paid')->where('record.receivable_idr', 0));
            $reversal = ['version' => $sale->version, 'reason' => 'Koreksi', 'idempotency_key' => (string) Str::uuid()];
            $this->actingAs($this->manager);
            $this->post(route($kind.'-sales.reverse', $sale->id), $reversal)->assertSessionHasNoErrors();
            $this->post(route($kind.'-sales.reverse', $sale->id), $reversal)->assertSessionHasNoErrors();
            $this->assertSame('reversed', $sale->fresh()->status);
            $this->assertSame('reversed', $receipt->fresh()->status);
            $this->assertSame(0, app(CashLedger::class)->balance($this->cash->id));
        }
        foreach (['trips', 'sort_lots', 'sort_stock_movements', 'factory_returns', 'sort_sale_items'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(0, (int) DB::table('profit_entries')->sum('amount_delta_idr'));
        $this->assertDatabaseCount('cash_entries', 4);
    }

    public function test_sale_reversal_requires_sufficient_balance_and_can_correct_an_inactive_account(): void
    {
        $sale = $this->save('sort');
        $this->receive($sale);
        $this->actingAs($this->manager)->post(route('cash.store'), ['cash_account_id' => $this->cash->id, 'kind' => 'withdrawal',
            'amount_idr' => $sale->revenue_idr, 'occurred_at' => '2026-10-09T10:00:00', 'reason' => 'Penarikan', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $data = ['version' => $sale->version, 'reason' => 'Koreksi', 'idempotency_key' => (string) Str::uuid()];
        $this->post(route('sort-sales.reverse', $sale->id), $data)->assertSessionHasErrors('operation');
        $this->assertSame('posted', $sale->fresh()->status);
        $this->assertSame('posted', SaleReceipt::sole()->status);
        $this->assertDatabaseCount('cash_entries', 2);
        $this->assertDatabaseCount('profit_entries', 1);
        $withdrawal = DB::table('cash_entries')->where('kind', 'withdrawal')->sole();
        $this->post(route('cash.reverse', $withdrawal->id), ['reason' => 'Kembali', 'return_reference' => 'Dana kembali', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->cash->update(['is_active' => false]);
        $this->post(route('sort-sales.reverse', $sale->id), $data)->assertSessionHasNoErrors();
        $this->assertSame(0, app(CashLedger::class)->balance($this->cash->id));
        $this->assertSame('reversed', SaleReceipt::sole()->status);
    }

    public function test_reversal_audit_failure_restores_sale_receipt_cash_and_profit(): void
    {
        $sale = $this->save('sort');
        $this->receive($sale);
        $keys = DB::table('idempotency_requests')->count();
        $this->mock(AuditService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn([]);
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('Reversal audit unavailable'));
        });
        try {
            app(ReverseSale::class)->handle($this->manager, $sale, ['version' => $sale->version, 'reason' => 'Koreksi', 'idempotency_key' => (string) Str::uuid()]);
            $this->fail('Expected reversal rollback');
        } catch (\RuntimeException $error) {
            $this->assertSame('Reversal audit unavailable', $error->getMessage());
        }
        $this->assertSame('posted', $sale->fresh()->status);
        $this->assertSame('posted', SaleReceipt::sole()->status);
        $this->assertSame(60010, app(CashLedger::class)->balance($this->cash->id));
        $this->assertDatabaseCount('cash_entries', 1);
        $this->assertDatabaseCount('profit_entries', 1);
        $this->assertDatabaseCount('idempotency_requests', $keys);
    }

    public function test_historical_partial_sales_can_be_received_once_through_the_module_and_reversed_together(): void
    {
        foreach (['factory', 'sort'] as $kind) {
            $time = app(BusinessClock::class)->now();
            $model = $kind === 'factory' ? FactorySale::class : SortSale::class;
            $revenue = $kind === 'factory' ? 350000 : 200000;
            $sale = $model::create(['number' => 'ARSIP-'.$kind, 'vehicle_id' => $this->vehicle->id, 'workflow' => TransactionWorkflow::Direct,
                'sold_at' => $time, 'business_date' => '2026-10-09', 'sell_price_snapshot_idr' => $kind === 'factory' ? 3500 : 2000,
                'revenue_idr' => $revenue, 'deduction_idr' => 0, 'status' => 'draft', 'cost_status' => 'unavailable', 'created_by' => $this->driver->id]
                + ($kind === 'factory' ? ['factory_id' => $this->factory->id, 'factory_price_schedule_id' => $this->price->id, 'accepted_grams' => 100000] : ['manual_sold_grams' => 100000]));
            $sale->update(['status' => 'posted', 'posted_at' => $time, 'posted_by' => $this->driver->id, 'version' => 2]);
            app(ProfitLedger::class)->sale($this->driver, $sale);
            // Historical manual receipt, created before sort sales became automatic.
            $oldReceipt = SaleReceipt::create(['number' => 'ARSIP-TERIMA-'.$kind, $kind.'_sale_id' => $sale->id,
                'cash_account_id' => $this->cash->id, 'amount_idr' => 10000, 'method' => 'cash', 'status' => 'posted',
                'occurred_at' => $time, 'posted_at' => $time, 'business_date' => '2026-10-09', 'created_by' => $this->cashier->id]);
            DB::table('cash_entries')->insert(['cash_account_id' => $this->cash->id, 'direction' => 'in', 'kind' => 'receipt',
                'sale_receipt_id' => $oldReceipt->id, 'amount_idr' => 10000, 'occurred_at' => $time,
                'business_date' => '2026-10-09', 'created_by' => $this->cashier->id]);
            $data = ['sale_kind' => $kind, 'sale_id' => $sale->id, 'amount_idr' => $revenue - 10000, 'method' => 'cash',
                'occurred_at' => '2026-10-09T10:00:00', 'expected_remaining_idr' => $revenue - 10000, 'cash_account_id' => $this->cash->id, 'idempotency_key' => (string) Str::uuid()];
            $this->actingAs($this->driver)->post(route('sale-receipts.store'), $data)->assertForbidden();
            $this->actingAs($this->cashier)->post(route('sale-receipts.store'), array_replace($data, ['expected_remaining_idr' => $revenue]))->assertSessionHasErrors('operation');
            $this->get(route($kind.'-sales.show', $sale->id))->assertInertia(fn (Assert $p) => $p->where('record.receivable_idr', $revenue - 10000)->has('cashReceipts', 1));
            $this->post(route('sale-receipts.store'), $data)->assertSessionHasNoErrors();
            $this->post(route('sale-receipts.store'), $data)->assertSessionHasNoErrors();
            $this->post(route('sale-receipts.store'), array_replace($data, ['idempotency_key' => (string) Str::uuid()]))->assertSessionHasErrors('operation');
            $this->assertSame($revenue, app(CashLedger::class)->balance($this->cash->id));
            $this->assertSame(2, SaleReceipt::where($kind.'_sale_id', $sale->id)->count());
            $this->actingAs($this->manager)->post(route($kind.'-sales.reverse', $sale->id), ['version' => 2, 'reason' => 'Koreksi arsip', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
            $this->assertSame(0, app(CashLedger::class)->balance($this->cash->id));
            $this->assertSame(0, SaleReceipt::where($kind.'_sale_id', $sale->id)->where('status', 'posted')->count());
        }
        $this->assertDatabaseCount('cash_entries', 8);
        $this->assertSame(0, (int) DB::table('profit_entries')->sum('amount_delta_idr'));
    }
}
