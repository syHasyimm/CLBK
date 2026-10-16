<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['lot_id', 'kind', 'quantity_delta_grams', 'purchase_cost_delta_idr', 'loading_cost_delta_idr', 'sort_sale_item_id', 'reversal_of_id', 'reason', 'occurred_at', 'created_by'])]
class SortStockMovement extends Model
{
    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'quantity_delta_grams' => 'integer', 'purchase_cost_delta_idr' => 'integer', 'loading_cost_delta_idr' => 'integer'];
    }

    public $timestamps = false;
}
