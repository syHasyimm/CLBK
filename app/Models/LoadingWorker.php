<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'phone', 'address', 'joined_on', 'is_active'])]
class LoadingWorker extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'joined_on' => 'date:Y-m-d',
        ];
    }
}
