<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FactorySale extends Sale
{
    public function kind(): string
    {
        return 'factory';
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function factoryReturn(): HasOne
    {
        return $this->hasOne(FactoryReturn::class);
    }
}
