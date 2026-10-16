<?php

namespace App\Domain\Sales;

use App\Domain\Shared\Money;
use App\Domain\Shared\Proportion;
use App\Models\AppSetting;
use App\Models\FactorySale;
use App\Models\Trip;
use Illuminate\Validation\ValidationException;

class FactoryCalculation
{
    public function calculate(FactorySale $sale, Trip $trip): array
    {
        $difference = $sale->initial_load_grams - $sale->sorted_out_grams - $sale->accepted_grams;
        $revenue = Money::forWeight($sale->accepted_grams, $sale->sell_price_snapshot_idr) - $sale->deduction_idr;
        if ($revenue < 0) {
            throw ValidationException::withMessages(['deduction_idr' => 'Potongan melebihi nilai penjualan.']);
        }
        $available = $trip->cost_status === 'available';
        $purchase = $available ? Proportion::round($trip->tbs_purchase_cost_snapshot_idr, $sale->sorted_out_grams, $sale->initial_load_grams) : null;
        $loading = $available ? Proportion::round($trip->tbs_loading_cost_snapshot_idr, $sale->sorted_out_grams, $sale->initial_load_grams) : null;

        return ['weight_difference_grams' => $difference, 'revenue_idr' => $revenue,
            'requires_approval' => $difference < 0 || $difference * 10000 > $sale->initial_load_grams * (int) AppSetting::values()['weight_tolerance_basis_points'],
            'cost_status' => $trip->cost_status, 'purchase_cost_carried_idr' => $purchase, 'loading_cost_carried_idr' => $loading,
            'purchase_cost_released_idr' => $available ? $trip->tbs_purchase_cost_snapshot_idr - $purchase : null,
            'loading_cost_released_idr' => $available ? $trip->tbs_loading_cost_snapshot_idr - $loading : null];
    }
}
