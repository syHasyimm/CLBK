<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['factory_id', 'effective_from', 'tbs_sell_price_idr', 'published_by'])]
class FactoryPriceSchedule extends Model
{
    protected function casts(): array
    {
        return ['effective_from' => 'immutable_datetime', 'tbs_sell_price_idr' => 'integer'];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
