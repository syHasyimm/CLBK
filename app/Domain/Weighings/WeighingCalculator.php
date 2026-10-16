<?php

namespace App\Domain\Weighings;

use App\Domain\Payroll\LoadingWageAllocator;
use App\Domain\Shared\Money;
use App\Models\PriceSchedule;

class WeighingCalculator
{
    public function calculate(array $weights, array $workers, PriceSchedule $price): array
    {
        $basis = array_sum($weights);
        $wage = Money::forWeight($basis, $price->loading_rate_idr);
        $allocator = new LoadingWageAllocator;
        $costs = $allocator->categories($wage, $weights);
        $items = [];
        foreach ($weights as $type => $grams) {
            $rate = $type === 'TBS' ? $price->tbs_buy_price_idr : $price->sort_buy_price_idr;
            $items[] = ['fruit_type' => $type, 'weight_grams' => $grams, 'buy_price_snapshot_idr' => $rate,
                'purchase_amount_idr' => Money::forWeight($grams, $rate), 'loading_basis_grams' => $grams,
                'loading_cost_allocated_idr' => $costs[$type]];
        }

        return ['price_schedule_id' => $price->id, 'loading_rate_snapshot_idr' => $price->loading_rate_idr,
            'loading_basis_grams' => $basis, 'total_purchase_idr' => array_sum(array_column($items, 'purchase_amount_idr')),
            'total_loading_wage_idr' => $wage, 'items' => $items, 'loaders' => $allocator->workers($wage, $workers)];
    }
}
