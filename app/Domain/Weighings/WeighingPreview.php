<?php

namespace App\Domain\Weighings;

use App\Domain\Pricing\PriceSnapshotResolver;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Weighing;
use App\Support\BusinessClock;
use Carbon\CarbonImmutable;

class WeighingPreview
{
    public function time(User $actor, Weighing $record): CarbonImmutable
    {
        return $actor->role === UserRole::Driver ? app(BusinessClock::class)->now() : $record->weighed_at;
    }

    public function calculate(User $actor, Weighing $record): array
    {
        $time = $this->time($actor, $record);
        $price = app(PriceSnapshotResolver::class)->resolve($time);

        return app(WeighingCalculator::class)->calculate($record->items->pluck('weight_grams', 'fruit_type')->all(),
            $record->loaders->pluck('worker_id')->all(), $price)
            + ['weighed_at' => $time->toIso8601String(), 'effective_from' => $price->effective_from->toIso8601String()];
    }
}
