<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['weighing_id', 'fruit_type', 'weight_grams', 'buy_price_snapshot_idr', 'purchase_amount_idr', 'loading_basis_grams', 'loading_cost_allocated_idr'])]
class WeighingItem extends Model
{
    public function weighing(): BelongsTo
    {
        return $this->belongsTo(Weighing::class);
    }

    protected function casts(): array
    {
        return ['weight_grams' => 'integer', 'buy_price_snapshot_idr' => 'integer', 'purchase_amount_idr' => 'integer', 'loading_basis_grams' => 'integer', 'loading_cost_allocated_idr' => 'integer'];
    }

    public function lot(): HasOne
    {
        return $this->hasOne(SortLot::class, 'source_weighing_item_id');
    }
}
