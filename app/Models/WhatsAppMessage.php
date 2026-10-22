<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'next_attempt_at' => 'immutable_datetime',
            'queued_at' => 'immutable_datetime', 'processing_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime'];
    }
}
