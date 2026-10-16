<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DriverVehicleAssignment
{
    public function forForm(User $actor): ?array
    {
        if ($actor->role !== UserRole::Driver) {
            return null;
        }

        $vehicle = $actor->assignedVehicle;

        return [
            'vehicle' => $vehicle ? ['id' => $vehicle->id, 'label' => $vehicle->plate_number] : null,
            'version' => $actor->vehicle_assignment_version,
            'error' => ! $vehicle
                ? 'Mobil belum ditetapkan. Hubungi admin.'
                : (! $vehicle->is_active ? 'Mobil yang ditugaskan sudah nonaktif. Hubungi admin untuk mengganti mobil.' : null),
        ];
    }

    // Called inside the same write transaction as the document, using a fresh user.
    // Idempotent replays return the original document without creating a new transaction.
    public function forWrite(User $actor, int $submittedVehicle, ?int $version): int
    {
        $assignment = $this->forForm($actor);
        if ($assignment === null) {
            return $submittedVehicle;
        }
        if ($assignment['error']) {
            throw ValidationException::withMessages(['vehicle_id' => $assignment['error']]);
        }
        if ($version !== $assignment['version'] || $submittedVehicle !== $assignment['vehicle']['id']) {
            throw ValidationException::withMessages(['vehicle_id' => 'Penugasan mobil berubah atau mobil tidak sesuai. Muat ulang halaman sebelum menyimpan.']);
        }

        return $assignment['vehicle']['id'];
    }
}
