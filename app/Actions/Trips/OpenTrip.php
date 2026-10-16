<?php

namespace App\Actions\Trips;

use App\Enums\UserRole;
use App\Models\Factory;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditService;
use App\Support\BusinessClock;
use App\Support\Database\WriteTransaction;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OpenTrip
{
    public function handle(User $actor, array $data): Trip
    {
        return app(WriteTransaction::class)->run(function () use ($actor, $data) {
            $actor = $actor->fresh();
            Gate::forUser($actor)->authorize('create', Trip::class);
            $driverId = $actor->role === UserRole::Driver ? $actor->id : $data['driver_id'];
            if (! User::query()->whereKey($driverId)->where('role', UserRole::Driver)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['driver_id' => 'Pilih supir aktif.']);
            }
            $vehicle = Vehicle::query()->whereKey($data['vehicle_id'])->where('is_active', true)->first();
            if (! $vehicle) {
                throw ValidationException::withMessages(['vehicle_id' => 'Pilih mobil aktif.']);
            }
            if (Trip::query()->where('vehicle_id', $vehicle->id)->whereIn('status', ['loading', 'dispatched'])->exists()) {
                throw ValidationException::withMessages(['vehicle_id' => 'Mobil masih mempunyai perjalanan aktif.']);
            }
            if (! empty($data['planned_factory_id']) && ! Factory::query()->whereKey($data['planned_factory_id'])->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['planned_factory_id' => 'Pilih pabrik aktif.']);
            }
            $date = app(BusinessClock::class)->date(app(BusinessClock::class)->now());
            $trip = Trip::create(['number' => app(DocumentNumber::class)->next('TRIP', $date),
                'vehicle_id' => $vehicle->id, 'driver_id' => $driverId, 'planned_factory_id' => $data['planned_factory_id'] ?? null,
                'business_date' => $date, 'status' => 'loading', 'created_by' => $actor->id]);
            $audit = app(AuditService::class);
            $audit->record($actor, 'open', $trip, null, $audit->snapshot($trip));

            return $trip;
        });
    }
}
