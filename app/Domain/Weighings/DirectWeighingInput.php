<?php

namespace App\Domain\Weighings;

use App\Domain\Shared\Weight;
use App\Models\Customer;
use App\Models\LoadingWorker;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

class DirectWeighingInput
{
    public function weights(array $data): array
    {
        return array_filter([
            'TBS' => Weight::grams((string) $data['tbs_kg'], 'tbs_kg'),
            'SORT' => Weight::grams((string) $data['sort_kg'], 'sort_kg'),
        ], fn ($grams) => $grams > 0);
    }

    public function workers(array $data): array
    {
        $ids = array_map('intval', $data['worker_ids']);
        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['worker_ids' => 'Anggota muat tidak boleh dipilih dua kali.']);
        }
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    public function checkWorkers(array $ids): void
    {
        if (LoadingWorker::query()->whereKey($ids)->where('is_active', true)->count() !== count($ids)) {
            throw ValidationException::withMessages(['worker_ids' => 'Pilih anggota muat yang masih aktif.']);
        }
    }

    public function checkReferences(int $customer, int $vehicle, array $workers): void
    {
        if (! Customer::query()->whereKey($customer)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['customer_id' => 'Pilih konsumen yang masih aktif.']);
        }
        if (! Vehicle::query()->whereKey($vehicle)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['vehicle_id' => 'Pilih mobil yang masih aktif.']);
        }
        if (! $workers) {
            throw ValidationException::withMessages(['worker_ids' => 'Pilih minimal satu anggota muat.']);
        }
        $this->checkWorkers($workers);
    }
}
