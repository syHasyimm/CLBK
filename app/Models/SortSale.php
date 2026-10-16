<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SortSale extends Sale
{
    public function kind(): string
    {
        return 'sort';
    }

    public function items(): HasMany
    {
        return $this->hasMany(SortSaleItem::class);
    }
}
