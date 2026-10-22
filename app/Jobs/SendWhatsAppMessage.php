<?php

namespace App\Jobs;

use App\Services\WhatsApp\SendMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 40;

    public function __construct(public int $messageId, public string $messageUuid) {}

    public function handle(SendMessage $sender): void
    {
        $sender->send($this->messageId, $this->messageUuid);
    }
}
