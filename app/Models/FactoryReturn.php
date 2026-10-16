<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FactoryReturn extends Model
{
    protected $fillable = ['factory_sale_id', 'weight_grams', 'purchase_cost_carried_idr', 'loading_cost_carried_idr', 'cost_status', 'received_at', 'notes'];

    public function lot(): HasOne
    {
        return $this->hasOne(SortLot::class, 'source_factory_return_id');
    }
}
