<?php

namespace Tests\Concerns\SalesAndInventoryTest;

use App\Actions\Sales\ReverseSale;
use App\Actions\Weighings\CreateDraftWeighing;
use App\Models\Customer;
use App\Models\SortLot;
use App\Models\Trip;
use App\Models\User;
use App\Models\Weighing;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

trait TripLifecycleCases
{
    public function test_dispatch_freezes_only_tbs_cost_and_keeps_factory_weights_empty(): void
    {
        $this->weigh('700', '100');
        $this->dispatch();
        $this->assertNull($this->trip->manual_load_grams);
        $this->assertSame(2100000, $this->trip->tbs_purchase_cost_snapshot_idr);
        $this->assertSame(70000, $this->trip->tbs_loading_cost_snapshot_idr);
        $this->assertSame(200000, SortLot::firstOrFail()->initial_purchase_cost_idr);
        $sale = $this->postSale($this->draft('factory'));
        $this->assertSame(1000000, $sale->initial_load_grams);
        $this->assertSame(1932000, $sale->purchase_cost_released_idr);
        foreach ([fn () => $this->trip->update(['tbs_purchase_cost_snapshot_idr' => 1]), fn () => $this->ticket->update(['status' => 'voided', 'voided_at' => now(), 'voided_by' => $this->manager->id, 'void_reason' => 'x']), fn () => $this->ticket->items()->update(['weight_grams' => 1])] as $attempt) {
            try {
                $attempt();
                $this->fail('Frozen source must reject edits');
            } catch (QueryException $e) {
                $this->assertStringContainsString('19', $e->getMessage());
            }
        }
    }

    public function test_new_trip_same_vehicle_isolated_and_old_correction_keeps_frozen_costs(): void
    {
        $original = $this->factoryPosted();
        $oldTrip = $this->trip->fresh();
        $this->post(route('trips.store'), ['vehicle_id' => $oldTrip->vehicle_id, 'driver_id' => $this->driver->id])->assertSessionHasNoErrors();
        $newTrip = Trip::latest('id')->firstOrFail();
        $this->post(route('factory-sales.reverse', ['sale' => $original->id]), $this->key(['version' => $original->version, 'reason' => 'Koreksi berat awal']))->assertSessionHasNoErrors();
        $this->assertSame('loading', $newTrip->fresh()->status);
        $this->assertSame('correction_pending', $oldTrip->fresh()->status);
        $this->assertSame(3000000, $oldTrip->fresh()->tbs_purchase_cost_snapshot_idr);
        $this->assertSame(1, Trip::where('vehicle_id', $oldTrip->vehicle_id)->whereIn('status', ['loading', 'dispatched'])->count());
        $replacement = $this->draft('factory', ['replaces_sale_id' => $original->id, 'correction_reason' => 'Timbang awal diperbaiki', 'invoice_number' => $original->invoice_number, 'initial_kg' => '1200', 'sort_kg' => '100', 'accepted_kg' => '1100']);
        $replacement = $this->postSale($replacement);
        $this->assertNotSame($original->number, $replacement->number);
        $this->assertSame(2750000, $replacement->purchase_cost_released_idr);
        $this->assertSame(250000, $replacement->factoryReturn->purchase_cost_carried_idr);
        $this->assertSame('loading', $newTrip->fresh()->status);
    }

    public function test_sort_only_trip_closes_without_factory_invoice_and_reversal_does_not_reserve_vehicle(): void
    {
        $this->weigh('0', '30');
        $sale = $this->postSale($this->draft('sort'));
        $this->dispatch();
        $this->post(route('trips.close', $this->trip), $this->key(['version' => $this->trip->version]))->assertSessionHasNoErrors();
        $this->assertSame('closed', $this->trip->fresh()->status);
        $this->post(route('trips.store'), ['vehicle_id' => $this->trip->vehicle_id, 'driver_id' => $this->driver->id])->assertSessionHasNoErrors();
        $this->post(route('sort-sales.reverse', ['sale' => $sale->id]), $this->key(['version' => $sale->version, 'reason' => 'Koreksi']))->assertSessionHasNoErrors();
        $this->assertSame('correction_pending', $this->trip->fresh()->status);
        $this->assertSame(1, Trip::whereIn('status', ['loading', 'dispatched'])->count());
    }

    public function test_dispatch_blocks_unfinished_draft_and_duplicate_departure_is_idempotent(): void
    {
        $customer = Customer::create(['code' => 'K1', 'name' => 'Konsumen', 'is_active' => true]);
        app(CreateDraftWeighing::class)->handle($this->manager, ['customer_id' => $customer->id, 'trip_id' => $this->trip->id, 'tbs_kg' => '1', 'sort_kg' => '0', 'worker_ids' => [], 'weighed_at' => '2026-10-09T10:00:00']);
        $data = $this->key(['version' => $this->trip->version]);
        $this->post(route('trips.dispatch', $this->trip), $data)->assertSessionHasErrors('operation');
        $ticket = Weighing::firstOrFail();
        $this->delete(route('weighings.destroy', $ticket), ['version' => $ticket->version])->assertSessionHasNoErrors();
        $this->post(route('trips.dispatch', $this->trip), $data)->assertSessionHasNoErrors();
        $this->post(route('trips.dispatch', $this->trip), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('idempotency_requests')->where('operation', 'dispatch-trip')->count());
    }

    public function test_driver_and_cashier_payloads_omit_full_cost_and_other_driver_trip_is_denied(): void
    {
        $sale = $this->factoryPosted();
        foreach ([$this->driver, $this->cashier] as $user) {
            $this->actingAs($user)->get(route('factory-sales.show', ['sale' => $sale->id]))->assertInertia(fn (Assert $p) => $p->missing('record.purchase_cost_released_idr')->missing('record.loading_cost_released_idr')->missing('record.trip.tbs_purchase_cost_snapshot_idr')->missing('record.factory_return.purchase_cost_carried_idr')->where('record.revenue_idr', 3150000));
            $this->get(route('trips.show', $this->trip))->assertInertia(fn (Assert $p) => $p->missing('trip.tbs_purchase_cost_snapshot_idr'));
            $this->get(route('weighings.show', $this->ticket))->assertInertia(fn (Assert $p) => $p->missing('record.trip.tbs_purchase_cost_snapshot_idr'));
            $this->get(route('inventory.show', SortLot::firstOrFail()))->assertInertia(fn (Assert $p) => $p->missing('lot.initial_purchase_cost_idr')->missing('lot.balance.purchase')->missing('movements.data.0.purchase_cost_delta_idr')->where('lot.source.id', $sale->id)->where('lot.source.kind', 'factory'));
        }
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('factory-sales.show', ['sale' => $sale->id]))->assertForbidden();
        $this->get(route('inventory.show', SortLot::firstOrFail()))->assertForbidden();
        $this->get(route('factory-sales.index'))->assertInertia(fn (Assert $p) => $p->has('sales.data', 0));
        $this->actingAs($this->cashier)->get(route('factory-sales.create'))->assertForbidden();
        $this->post(route('trips.dispatch', $this->trip), $this->key(['version' => 1]))->assertForbidden();
    }

    public function test_factory_reversal_failure_rolls_back_receipt_profit_trip_and_note(): void
    {
        $sale = $this->factoryPosted();
        $keys = DB::table('idempotency_requests')->count();
        $this->mock(AuditService::class, function ($mock) {
            $mock->shouldReceive('snapshot')->andReturn([]);
            $mock->shouldReceive('record')->withArgs(fn ($actor, $action) => $action !== 'reverse')->andReturnNull();
            $mock->shouldReceive('record')->withArgs(fn ($actor, $action) => $action === 'reverse')->andThrow(new \RuntimeException('reversal audit failure'));
        });
        try {
            app(ReverseSale::class)->handle($this->manager, $sale, $this->key(['version' => $sale->version, 'reason' => 'Koreksi']));
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('reversal audit failure', $e->getMessage());
        }
        $this->assertSame('posted', $sale->fresh()->status);
        $this->assertSame('factory_recorded', $this->trip->fresh()->status);
        $this->assertSame('active', SortLot::firstOrFail()->status);
        $this->assertDatabaseCount('sort_stock_movements', 1);
        $this->assertDatabaseCount('profit_entries', 3);
        $this->assertDatabaseCount('idempotency_requests', $keys);
    }
}
