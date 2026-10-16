<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['code', 'plate_number', 'vehicle_type', 'capacity_grams', 'default_driver_id', 'is_active'])]
class Vehicle extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity_grams' => 'integer',
            'default_driver_id' => 'integer',
        ];
    }

    public function defaultDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_driver_id');
    }
}
