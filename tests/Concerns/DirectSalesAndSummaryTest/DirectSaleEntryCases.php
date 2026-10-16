<?php

namespace Tests\Concerns\DirectSalesAndSummaryTest;

use App\Actions\Sales\SaveDirectSale;
use App\Enums\TransactionWorkflow;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use App\Models\FactorySale;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CashLedger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

trait DirectSaleEntryCases
{
    public function test_factory_one_save_posts_net_sale_and_receivable_without_receiving_cash(): void
    {
        $sale = $this->save(changes: ['revenue_idr' => 1, 'created_by' => $this->manager->id]);
        $this->assertSame('posted', $sale->status);
        $this->assertSame(TransactionWorkflow::Direct, $sale->workflow);
        $this->assertSame($this->vehicle->id, $sale->vehicle_id);
        $this->assertSame($this->driver->id, $sale->created_by);
        $this->assertSame(3149000, $sale->revenue_idr);
        $this->assertSame(3500, $sale->sell_price_snapshot_idr);
        foreach (['trip_id', 'initial_load_grams', 'sorted_out_grams', 'weight_difference_grams', 'variance_approved_by', 'variance_reason', 'invoice_number', 'purchase_cost_released_idr', 'loading_cost_released_idr'] as $key) {
            $this->assertNull($sale->$key);
        }
        foreach (['trips', 'sort_lots', 'sort_stock_movements', 'factory_returns'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertDatabaseCount('profit_entries', 1);
        $this->assertDatabaseCount('sale_receipts', 0);
        $this->assertDatabaseCount('cash_entries', 0);
        $this->assertSame(0, app(CashLedger::class)->balance($this->cash->id));
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        $this->get(route('factory-sales.show', $sale->id))->assertInertia(fn (Assert $p) => $p->where('record.trip', null)->where('record.vehicle.plate_number', 'BK LANGSUNG')->where('record.receivable_idr', 3149000)->where('record.received_idr', 0)->where('preview', null));
        $this->post(route('factory-sales.post', $sale->id), ['version' => 2, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasErrors('operation');
        $this->get(route('factory-sales.edit', $sale->id))->assertForbidden();
        $this->post(route('factory-sales.proofs', $sale->id), [])->assertForbidden();
    }

    public function test_sort_sale_is_manual_and_can_be_saved_without_factory_sale_or_consumer_stock(): void
    {
        $sale = $this->save('sort');
        $this->assertSame(30005, $sale->manual_sold_grams);
        $this->assertSame(60010, $sale->revenue_idr);
        $this->assertNull($sale->buyer_name);
        $this->assertNull($sale->trip_id);
        $this->assertSame('unavailable', $sale->cost_status);
        foreach (['weighings', 'factory_sales', 'sort_sale_items', 'sort_lots', 'sort_stock_movements'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(0, app(CashLedger::class)->balance($this->cash->id));
        $this->assertDatabaseCount('sale_receipts', 0);
        $this->get(route('sort-sales.show', $sale->id))->assertInertia(fn (Assert $p) => $p->where('record.receivable_idr', 60010)->where('record.payment_status', 'unpaid'));
        $this->save('sort', ['sold_kg' => '999.999', 'sell_price_snapshot_idr' => 1, 'buyer_name' => 'Tempat sortir']);
        $this->assertDatabaseCount('sort_sales', 2);
        $this->save(changes: ['deduction_idr' => 0]);
        $this->save(changes: ['deduction_idr' => 0]);
        $this->assertDatabaseCount('factory_sales', 2);
    }

    public function test_preview_is_read_only_and_factory_price_can_be_seen_before_weight_is_entered(): void
    {
        $this->actingAs($this->driver)->postJson(route('factory-sales.preview'), ['factory_id' => $this->factory->id, 'accepted_kg' => '0', 'deduction_idr' => 0])
            ->assertOk()->assertJsonPath('price.id', $this->price->id)->assertJsonPath('preview', null);
        $this->postJson(route('factory-sales.preview'), ['factory_id' => $this->factory->id, 'accepted_kg' => '1.005', 'deduction_idr' => 1])
            ->assertOk()->assertJsonPath('preview.gross_idr', 3518)->assertJsonPath('preview.revenue_idr', 3517);
        $this->postJson(route('sort-sales.preview'), ['sold_kg' => '1,005', 'sell_price_snapshot_idr' => 2000])->assertJsonPath('preview.revenue_idr', 2010);
        foreach (['factory_sales', 'sort_sales', 'idempotency_requests', 'audit_logs', 'document_sequences', 'profit_entries'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_price_change_requires_confirmation_and_past_sale_price_is_frozen(): void
    {
        $payload = $this->payload();
        $next = FactoryPriceSchedule::create(['factory_id' => $this->factory->id, 'effective_from' => '2026-10-09 03:01:00', 'tbs_sell_price_idr' => 3400, 'published_by' => $this->manager->id]);
        $this->travelTo(Carbon::parse('2026-10-09 03:02:00', 'UTC'));
        $this->actingAs($this->driver)->post(route('factory-sales.store'), $payload)->assertSessionHasErrors('factory_price_schedule_id');
        $this->assertDatabaseCount('factory_sales', 0);
        $this->assertDatabaseCount('document_sequences', 0);
        $this->post(route('factory-sales.store'), array_replace($payload, ['factory_price_schedule_id' => $next->id]))->assertSessionHasNoErrors();
        $sale = FactorySale::firstOrFail();
        $this->assertSame(3059000, $sale->revenue_idr);
        $this->travelTo(Carbon::parse('2026-10-10 03:00:00', 'UTC'));
        FactoryPriceSchedule::create(['factory_id' => $this->factory->id, 'effective_from' => '2026-10-09 17:00:00', 'tbs_sell_price_idr' => 3200, 'published_by' => $this->manager->id]);
        $this->assertSame(3400, $sale->fresh()->sell_price_snapshot_idr);
    }

    public function test_missing_price_inactive_masters_and_invalid_weights_do_not_leave_partial_records(): void
    {
        $empty = Factory::create(['code' => 'P0', 'name' => 'Tanpa harga', 'is_active' => true]);
        $this->actingAs($this->driver)->post(route('factory-sales.store'), $this->payload(changes: ['factory_id' => $empty->id]))->assertSessionHasErrors('factory_price_schedule_id');
        foreach (['0', '-1', '1.0001', '1000001', 'abc'] as $weight) {
            $this->post(route('factory-sales.store'), $this->payload(changes: ['accepted_kg' => $weight]))->assertSessionHasErrors('accepted_kg');
            $this->post(route('sort-sales.store'), $this->payload('sort', ['sold_kg' => $weight]))->assertSessionHasErrors('sold_kg');
        }
        $this->post(route('factory-sales.store'), $this->payload(changes: ['deduction_idr' => 99999999]))->assertSessionHasErrors('deduction_idr');
        $this->post(route('sort-sales.store'), $this->payload('sort', ['sell_price_snapshot_idr' => 0]))->assertSessionHasErrors('sell_price_snapshot_idr');
        $this->vehicle->update(['is_active' => false]);
        $this->post(route('sort-sales.store'), $this->payload('sort'))->assertSessionHasErrors('vehicle_id');
        $this->vehicle->update(['is_active' => true]);
        $this->factory->update(['is_active' => false]);
        $this->post(route('factory-sales.store'), $this->payload())->assertSessionHasErrors('factory_id');
        foreach (['factory_sales', 'sort_sales', 'profit_entries', 'document_sequences', 'idempotency_requests'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_old_input_fields_are_rejected_for_new_sales_for_both_entry_roles(): void
    {
        foreach ([$this->driver, $this->manager] as $actor) {
            $this->actingAs($actor)->post(route('factory-sales.store'), $this->payload(changes: ['trip_id' => 1, 'sold_at' => '2026-10-09T10:00:00', 'initial_kg' => '1000', 'sort_kg' => '100', 'sell_price_snapshot_idr' => 1, 'deduction_reason' => 'Alasan']))
                ->assertSessionHasErrors(['trip_id', 'sold_at', 'initial_kg', 'sort_kg', 'sell_price_snapshot_idr', 'deduction_reason']);
        }
        $this->post(route('sort-sales.store'), $this->payload('sort', ['deduction_idr' => 1000]))->assertSessionHasErrors('deduction_idr');
        $this->assertDatabaseCount('factory_sales', 0);
    }

    public function test_idempotency_replay_survives_price_and_master_changes_and_rejects_changed_payload(): void
    {
        foreach (['factory', 'sort'] as $kind) {
            $data = $this->payload($kind);
            $sale = app(SaveDirectSale::class)->handle($this->driver, $kind, $data);
            $this->vehicle->update(['is_active' => false]);
            $this->factory->update(['is_active' => false]);
            $this->cash->update(['is_active' => false]);
            $again = app(SaveDirectSale::class)->handle($this->driver, $kind, $data);
            $this->assertSame($sale->id, $again->id);
            try {
                app(SaveDirectSale::class)->handle($this->driver, $kind, array_replace($data, ['notes' => 'Berubah']));
                $this->fail('Changed payload accepted');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('operation', $e->errors());
            }
            $this->vehicle->update(['is_active' => true]);
            $this->factory->update(['is_active' => true]);
            $this->cash->update(['is_active' => true]);
        }
        $this->assertDatabaseCount('factory_sales', 1);
        $this->assertDatabaseCount('sort_sales', 1);
        $this->assertDatabaseCount('profit_entries', 2);
        $this->assertDatabaseCount('sale_receipts', 0);
        $this->assertDatabaseCount('cash_entries', 0);
    }

    public function test_audit_failure_rolls_back_sale_revenue_and_number(): void
    {
        $this->mock(AuditService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn([]);
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('Audit unavailable'));
        });
        foreach (['factory', 'sort'] as $kind) {
            try {
                app(SaveDirectSale::class)->handle($this->driver, $kind, $this->payload($kind));
                $this->fail('Expected failure');
            } catch (\RuntimeException $e) {
                $this->assertSame('Audit unavailable', $e->getMessage());
            }
        }
        foreach (['factory_sales', 'sort_sales', 'sale_receipts', 'cash_entries', 'profit_entries', 'document_sequences', 'idempotency_requests'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_role_scope_uses_creator_and_cashier_can_receive_but_cannot_create(): void
    {
        $sale = $this->save();
        $sort = $this->save('sort');
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('factory-sales.show', $sale->id))->assertForbidden();
        $this->get(route('sort-sales.show', $sort->id))->assertForbidden();
        $this->get(route('factory-sales.index'))->assertInertia(fn (Assert $p) => $p->has('sales.data', 0));
        $this->actingAs($this->driver)->get(route('factory-sales.index'))->assertInertia(fn (Assert $p) => $p->has('sales.data', 1));
        $this->actingAs($this->cashier)->get(route('factory-sales.index'))->assertInertia(fn (Assert $p) => $p->has('sales.data', 1));
        $this->get(route('factory-sales.create'))->assertForbidden();
        $this->post(route('sort-sales.store'), $this->payload('sort'))->assertForbidden();
        $this->postJson(route('factory-sales.preview'), [])->assertForbidden();
        $this->actingAs($this->driver)->post(route('factory-sales.reverse', $sale->id), ['version' => 2, 'reason' => 'Koreksi', 'idempotency_key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_removed_pages_endpoints_and_trip_references_are_unavailable_for_every_role(): void
    {
        $this->assertFalse(Route::has('trips.index'));
        $this->assertFalse(Route::has('inventory.index'));
        $this->assertTrue(Route::has('sale-receipts.index'));
        $this->assertTrue(Route::has('sale-receipts.create'));
        $this->assertTrue(Route::has('sale-receipts.store'));
        $this->assertFalse(Route::has('factory-sales.settle'));
        $this->assertFalse(Route::has('sort-sales.settle'));
        foreach ([$this->manager, $this->driver, $this->cashier] as $actor) {
            $this->actingAs($actor);
            foreach (['/trips', '/trips/1', '/inventory', '/inventory/1', '/operational-references/trips', '/operational-references/factory-trips', '/finance-references/trips'] as $url) {
                $this->get($url)->assertNotFound();
            }
            foreach (['/trips', '/trips/1/dispatch', '/trips/1/close', '/inventory/1/loss', '/inventory/1/loss/1/reverse'] as $url) {
                $this->post($url)->assertNotFound();
            }
        }
        $this->actingAs($this->manager)->post(route('daily-expenses.store'), ['trip_id' => 1])->assertSessionHasErrors('trip_id');
        $this->get(route('finance.index', ['kind' => 'receipt']))->assertInertia(fn (Assert $p) => $p->component('Finance/SaleReceipts'));
    }
}
