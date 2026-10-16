<?php

namespace Tests\Concerns\PricesAndWeighingsTest;

use App\Models\SortLot;
use App\Models\Trip;
use Illuminate\Support\Str;

trait LegacyWeighingLifecycleCases
{
    public function test_one_active_trip_per_vehicle_and_inactive_drivers_are_rejected(): void
    {
        $data = ['vehicle_id' => $this->trip->vehicle_id, 'driver_id' => $this->driver->id];
        $this->post(route('trips.store'), $data)->assertSessionHasErrors('vehicle_id');
        $this->trip->update(['status' => 'factory_recorded']);
        $this->post(route('trips.store'), $data)->assertSessionHasNoErrors();
        $this->assertNull(Trip::latest('id')->first()->manual_load_grams);
        $this->driver->update(['is_active' => false]);
        $this->post(route('trips.store'), $data)->assertSessionHasErrors('driver_id');
    }

    public function test_draft_edit_uses_version_and_does_not_create_stock_or_reuse_deleted_number(): void
    {
        $record = $this->draft();
        $payload = $this->payload(['version' => 1, 'tbs_kg' => '20.005']);
        $this->patch(route('weighings.update', $record), $payload)->assertSessionHasNoErrors();
        $this->patch(route('weighings.update', $record), $payload)->assertSessionHasErrors('operation');
        $this->assertSame(20005, $record->fresh()->items()->where('fruit_type', 'TBS')->first()->weight_grams);
        $this->assertDatabaseCount('sort_lots', 0);
        $this->delete(route('weighings.destroy', $record), ['version' => 2])->assertSessionHasNoErrors();
        $next = $this->draft();
        $this->assertNotSame($record->number, $next->number);
    }

    public function test_void_reverses_stock_once_preserves_snapshots_and_links_replacement(): void
    {
        $record = $this->postTicket($this->draft());
        $data = ['version' => 2, 'reason' => 'Berat salah', 'idempotency_key' => (string) Str::uuid()];
        $this->post(route('weighings.void', $record), $data)->assertSessionHasNoErrors();
        $this->post(route('weighings.void', $record), $data)->assertSessionHasNoErrors();
        $this->assertSame('voided', $record->fresh()->status);
        $this->assertSame(3200000, $record->fresh()->total_purchase_idr);
        $lot = SortLot::with('movements')->firstOrFail();
        $this->assertSame(0, $lot->movements->sum('quantity_delta_grams'));
        $this->assertSame(0, $lot->movements->sum('purchase_cost_delta_idr'));
        $this->assertSame(0, $lot->movements->sum('loading_cost_delta_idr'));
        $this->assertDatabaseCount('sort_stock_movements', 2);
        $replacement = $this->draft(['replaces_weighing_id' => $record->id]);
        $this->assertSame($record->id, $replacement->replaces_weighing_id);
        $this->assertLegacyDraftErrors($this->payload(['replaces_weighing_id' => $record->id]), 'replaces_weighing_id');
    }
}
