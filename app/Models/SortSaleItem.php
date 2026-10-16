<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SortSaleItem extends Model
{
    protected $fillable = ['sort_sale_id', 'lot_id', 'weight_grams', 'sell_price_snapshot_idr', 'gross_revenue_idr', 'purchase_cost_released_idr', 'loading_cost_released_idr'];

    public $timestamps = false;
}
