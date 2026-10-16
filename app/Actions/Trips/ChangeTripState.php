<?php

namespace App\Actions\Trips;

use App\Domain\Inventory\Stock;
use App\Enums\UserRole;
use App\Models\FactorySale;
use App\Models\SortLot;
use App\Models\SortSale;
use App\Models\Trip;
use App\Models\User;
use App\Models\WeighingItem;
use App\Services\AuditService;
use App\Services\IdempotencyService;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeTripState
{
    public function handle(User $actor, Trip $trip, string $operation, array $data): Trip
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $trip, $operation, $data) {
            $actor = $actor->fresh();
            $trip = $trip->fresh();
            Gate::forUser($actor)->authorize('create', Trip::class);
            Gate::forUser($actor)->authorize('view', $trip);
            $id = app(IdempotencyService::class)->run($actor, $operation.'-trip', $data['idempotency_key'], ['trip_id' => $trip->id, 'version' => (int) $data['version']], function () use ($actor, $trip, $operation, $data) {
                if ($trip->version !== (int) $data['version']) {
                    throw ValidationException::withMessages(['operation' => 'Perjalanan berubah. Muat ulang halaman.']);
                }
                $audit = app(AuditService::class);
                $before = $audit->snapshot($trip);
                if ($operation === 'dispatch') {
                    if ($trip->status !== 'loading' || $trip->weighings()->where('status', 'draft')->exists()) {
                        throw ValidationException::withMessages(['operation' => 'Sahkan atau hapus draft tiket sebelum keberangkatan.']);
                    }
                    if (! $trip->vehicle->is_active || ! $trip->driver->is_active || $trip->driver->role !== UserRole::Driver) {
                        throw ValidationException::withMessages(['operation' => 'Mobil dan supir harus aktif saat berangkat.']);
                    }
                    $items = WeighingItem::whereHas('weighing', fn ($q) => $q->where('trip_id', $trip->id)->where('status', 'posted'))->where('fruit_type', 'TBS')->get();
                    $trip->fill(['status' => 'dispatched', 'departed_at' => app(BusinessClock::class)->now(),
                        'cost_status' => $items->isEmpty() ? 'unavailable' : 'available',
                        'tbs_purchase_cost_snapshot_idr' => $items->isEmpty() ? null : $items->sum('purchase_amount_idr'),
                        'tbs_loading_cost_snapshot_idr' => $items->isEmpty() ? null : $items->sum('loading_cost_allocated_idr')]);
                } else {
                    if (! in_array($trip->status, ['dispatched', 'factory_recorded', 'correction_pending'], true) ||
                        FactorySale::where('trip_id', $trip->id)->where('status', 'draft')->exists() || SortSale::where('trip_id', $trip->id)->where('status', 'draft')->exists()) {
                        throw ValidationException::withMessages(['operation' => 'Perjalanan belum dapat ditutup. Periksa draft penjualan.']);
                    }
                    $hasFactory = FactorySale::where('trip_id', $trip->id)->where('status', 'posted')->exists();
                    $hasTbs = $trip->weighings()->where('status', 'posted')->whereHas('items', fn ($q) => $q->where('fruit_type', 'TBS'))->exists();
                    $hasSortSource = SortLot::where('trip_id', $trip->id)->where('status', 'active')->exists();
                    if (! $hasFactory && ($hasTbs || ! $hasSortSource || FactorySale::where('trip_id', $trip->id)->where('status', 'reversed')->exists())) {
                        throw ValidationException::withMessages(['operation' => 'Catat nota pabrik atau nota penggantinya sebelum menutup perjalanan.']);
                    }
                    foreach (SortLot::where('trip_id', $trip->id)->where('status', 'active')->get() as $lot) {
                        if (app(Stock::class)->balance($lot)['grams'] !== 0) {
                            throw ValidationException::withMessages(['operation' => 'Stok sortir masih tersisa. Jual atau catat kehilangan sebelum menutup perjalanan.']);
                        }
                    }
                    $trip->fill(['status' => 'closed', 'closed_at' => app(BusinessClock::class)->now()]);
                }
                $trip->version++;
                $trip->save();
                $audit->record($actor, $operation, $trip, $before, $audit->snapshot($trip));

                return $trip->id;
            });

            return Trip::findOrFail($id);
        });
    }
}
