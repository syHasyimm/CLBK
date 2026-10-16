<?php

namespace App\Domain\Sales;

use App\Domain\Shared\Money;
use App\Domain\Shared\Weight;
use App\Models\Factory;
use App\Models\FactoryPriceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class DirectSaleInput
{
    public function price(int $factoryId, CarbonImmutable $time): FactoryPriceSchedule
    {
        if (! Factory::whereKey($factoryId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['factory_id' => 'Pilih pabrik aktif.']);
        }
        $price = FactoryPriceSchedule::where('factory_id', $factoryId)->where('effective_from', '<=', $time)->orderByDesc('effective_from')->first();
        if (! $price) {
            throw ValidationException::withMessages(['factory_price_schedule_id' => 'Harga pabrik belum tersedia. Atur harga pabrik terlebih dahulu.']);
        }

        return $price;
    }

    public function calculate(string $kind, array $data, int $price): array
    {
        $field = $kind === 'factory' ? 'accepted_kg' : 'sold_kg';
        $grams = Weight::grams($data[$field], $field);
        if ($grams === 0) {
            throw ValidationException::withMessages([$field => 'Berat terjual harus lebih dari nol.']);
        }
        $gross = Money::forWeight($grams, $price);
        $deduction = $kind === 'factory' ? (int) ($data['deduction_idr'] ?? 0) : 0;
        if ($deduction > $gross) {
            throw ValidationException::withMessages(['deduction_idr' => 'Potongan tidak boleh melebihi nilai penjualan.']);
        }

        return ['grams' => $grams, 'gross_idr' => $gross, 'deduction_idr' => $deduction, 'revenue_idr' => $gross - $deduction];
    }
}
