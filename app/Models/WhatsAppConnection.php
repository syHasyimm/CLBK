<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppConnection extends Model
{
    protected $table = 'whatsapp_connections';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected $hidden = ['token', 'revision'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'checked_at' => 'immutable_datetime'];
    }
}
