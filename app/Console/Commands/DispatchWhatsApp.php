<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\NotificationOutbox;
use Illuminate\Console\Command;

class DispatchWhatsApp extends Command
{
    protected $signature = 'whatsapp:dispatch';

    protected $description = 'Pulihkan outbox WhatsApp dan rekonsiliasi status Fonnte';

    public function handle(NotificationOutbox $outbox): int
    {
        $count = $outbox->recover();
        $this->info('Outbox WhatsApp diperiksa: '.$count.' pesan.');

        return self::SUCCESS;
    }
}
