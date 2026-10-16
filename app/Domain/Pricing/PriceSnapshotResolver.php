<?php

namespace App\Domain\Pricing;

use App\Models\PriceSchedule;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PriceSnapshotResolver
{
    public function resolve(CarbonImmutable $time): PriceSchedule
    {
        $price = PriceSchedule::query()->where('business_date', app(BusinessClock::class)->date($time))
            ->where('effective_from', '<=', $time)->orderByDesc('effective_from')->first();
        if (! $price) {
            throw ValidationException::withMessages(['operation' => 'Harga untuk waktu timbang ini belum diterbitkan. Draft dapat disimpan; pengesahan menunggu harga hari tersebut.']);
        }

        return $price;
    }
}
