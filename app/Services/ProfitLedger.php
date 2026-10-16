<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;

class ProfitLedger
{
    public function sale(User $actor, Sale $sale): void
    {
        $values = ['revenue' => $sale->revenue_idr];
        if ($sale->cost_status === 'available') {
            $values += ['purchase_cost' => $sale->purchase_cost_released_idr, 'loading_cost' => $sale->loading_cost_released_idr];
        }
        foreach ($values as $category => $amount) {
            DB::table('profit_entries')->insert(['category' => $category, 'amount_delta_idr' => $amount, 'occurred_at' => $sale->sold_at,
                'business_date' => $sale->business_date, 'trip_id' => $sale->trip_id, $sale->kind().'_sale_id' => $sale->id, 'created_by' => $actor->id]);
        }
    }

    public function reverse(User $actor, string $column, int $id): void
    {
        $clock = app(BusinessClock::class);
        foreach (DB::table('profit_entries')->where($column, $id)->whereNull('reversal_of_id')->get() as $entry) {
            DB::table('profit_entries')->insert(['category' => $entry->category, 'amount_delta_idr' => -$entry->amount_delta_idr,
                'occurred_at' => $clock->now(), 'business_date' => $clock->date($clock->now()), 'trip_id' => $entry->trip_id,
                'reversal_of_id' => $entry->id, 'created_by' => $actor->id]);
        }
    }
}
