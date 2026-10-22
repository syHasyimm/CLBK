<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'phone', 'address', 'farm_location', 'notes', 'is_active', 'whatsapp_enabled'])]
class Customer extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'whatsapp_enabled' => 'boolean',
        ];
    }
}
