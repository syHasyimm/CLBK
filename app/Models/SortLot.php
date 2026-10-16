<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['source_type', 'source_weighing_item_id', 'source_factory_return_id', 'trip_id', 'received_at', 'initial_grams', 'initial_purchase_cost_idr', 'initial_loading_cost_idr', 'cost_status', 'status'])]
class SortLot extends Model
{
    protected function casts(): array
    {
        return ['received_at' => 'immutable_datetime', 'initial_grams' => 'integer', 'initial_purchase_cost_idr' => 'integer', 'initial_loading_cost_idr' => 'integer'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(SortStockMovement::class, 'lot_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
