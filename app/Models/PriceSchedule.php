<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['business_date', 'effective_from', 'tbs_buy_price_idr', 'sort_buy_price_idr', 'loading_rate_idr', 'notes', 'published_by'])]
class PriceSchedule extends Model
{
    protected function casts(): array
    {
        return ['effective_from' => 'immutable_datetime', 'tbs_buy_price_idr' => 'integer', 'sort_buy_price_idr' => 'integer', 'loading_rate_idr' => 'integer'];
    }
}
