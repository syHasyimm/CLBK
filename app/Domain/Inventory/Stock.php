<?php

namespace App\Domain\Inventory;

use App\Domain\Shared\Money;
use App\Domain\Shared\Proportion;
use App\Models\SortLot;
use App\Models\SortSale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Stock
{
    public function balance(SortLot $lot): array
    {
        $totals = DB::table('sort_stock_movements')->where('lot_id', $lot->id)->selectRaw('COALESCE(SUM(quantity_delta_grams),0) AS grams, SUM(purchase_cost_delta_idr) AS purchase, SUM(loading_cost_delta_idr) AS loading, MAX(id) AS revision, MAX(occurred_at) AS last_at')->first();

        return ['grams' => (int) $totals->grams, 'purchase' => $lot->cost_status === 'available' ? (int) $totals->purchase : null,
            'loading' => $lot->cost_status === 'available' ? (int) $totals->loading : null, 'revision' => (int) $totals->revision, 'last_at' => $totals->last_at];
    }

    public function allocate(SortLot $lot, int $grams, CarbonImmutable $time): array
    {
        $balance = $this->balance($lot);
        if ($lot->status !== 'active' || $grams <= 0 || $grams > $balance['grams']) {
            throw ValidationException::withMessages(['operation' => 'Berat melebihi stok sortir yang tersedia.']);
        }
        if ($time->lt($lot->received_at) || ($balance['last_at'] && $time->lt(CarbonImmutable::parse($balance['last_at'], 'UTC')))) {
            throw ValidationException::withMessages(['operation' => 'Waktu transaksi tidak boleh mendahului mutasi stok terakhir.']);
        }

        return ['lot_id' => $lot->id, 'weight_grams' => $grams,
            'purchase_cost_released_idr' => $balance['purchase'] === null ? null : Proportion::round($balance['purchase'], $grams, $balance['grams']),
            'loading_cost_released_idr' => $balance['loading'] === null ? null : Proportion::round($balance['loading'], $grams, $balance['grams']),
            'revision' => $balance['revision']];
    }

    public function preview(SortSale $sale): array
    {
        $remaining = $sale->manual_sold_grams;
        $items = [];
        foreach (SortLot::where('trip_id', $sale->trip_id)->where('status', 'active')->orderBy('received_at')->orderBy('id')->get() as $lot) {
            $balance = $this->balance($lot);
            if ($balance['grams'] === 0) {
                continue;
            }
            $item = $this->allocate($lot, min($remaining, $balance['grams']), $sale->sold_at);
            $item['sell_price_snapshot_idr'] = $sale->sell_price_snapshot_idr;
            $item['gross_revenue_idr'] = Money::forWeight($item['weight_grams'], $sale->sell_price_snapshot_idr);
            $items[] = $item;
            $remaining -= $item['weight_grams'];
            if ($remaining === 0) {
                break;
            }
        }
        if ($remaining > 0) {
            throw ValidationException::withMessages(['operation' => 'Berat melebihi stok sortir yang tersedia pada perjalanan ini.']);
        }
        $revenue = array_sum(array_column($items, 'gross_revenue_idr')) - $sale->deduction_idr;
        if ($revenue < 0) {
            throw ValidationException::withMessages(['deduction_idr' => 'Potongan melebihi nilai penjualan.']);
        }
        $available = ! in_array(null, array_column($items, 'purchase_cost_released_idr'), true);

        return ['items' => $items, 'revenue_idr' => $revenue, 'cost_status' => $available ? 'available' : 'unavailable',
            'purchase_cost_released_idr' => $available ? array_sum(array_column($items, 'purchase_cost_released_idr')) : null,
            'loading_cost_released_idr' => $available ? array_sum(array_column($items, 'loading_cost_released_idr')) : null,
            'preview_hash' => hash('sha256', json_encode([$sale->id, $sale->version, $items], JSON_THROW_ON_ERROR))];
    }

    public function untouched(SortLot $lot): bool
    {
        $balance = $this->balance($lot);

        return $balance['grams'] === $lot->initial_grams && $balance['purchase'] === $lot->initial_purchase_cost_idr && $balance['loading'] === $lot->initial_loading_cost_idr;
    }
}
