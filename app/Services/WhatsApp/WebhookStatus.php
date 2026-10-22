<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;

final class WebhookStatus
{
    public function receive(array $data): void
    {
        $event = ['provider_id' => isset($data['id']) ? (string) $data['id'] : null,
            'state_id' => isset($data['stateid']) ? (string) $data['stateid'] : null,
            'status' => isset($data['status']) ? strtolower($data['status']) : null,
            'state' => isset($data['state']) ? strtolower($data['state']) : null];
        DB::table('whatsapp_webhook_events')->insertOrIgnore($event + [
            'fingerprint' => hash('sha256', json_encode($event, JSON_THROW_ON_ERROR)), 'created_at' => now(),
        ]);
        $this->reconcile();
    }

    public function reconcile(): void
    {
        $ids = DB::table('whatsapp_webhook_events')->whereNull('processed_at')
            ->whereExists(function ($query) {
                $query->selectRaw('1')->from('whatsapp_messages')->where(function ($match) {
                    $match->whereColumn('whatsapp_messages.provider_id', 'whatsapp_webhook_events.provider_id')
                        ->orWhereColumn('whatsapp_messages.state_id', 'whatsapp_webhook_events.state_id');
                });
            })->orderBy('id')->limit(300)->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id) {
                $event = DB::table('whatsapp_webhook_events')->where('id', $id)->lockForUpdate()->first();
                if (! $event || $event->processed_at) {
                    return;
                }
                $query = WhatsAppMessage::query();
                if ($event->provider_id !== null) {
                    $query->where('provider_id', $event->provider_id);
                } elseif ($event->state_id !== null) {
                    $query->where('state_id', $event->state_id);
                } else {
                    return;
                }
                $message = $query->lockForUpdate()->first();
                if (! $message) {
                    return;
                }
                $status = match ($event->state) {
                    'read', 'played' => 'read', 'delivered', 'received' => 'delivered', default => null,
                };
                $status ??= match ($event->status) {
                    'sent' => 'sent', 'invalid', 'failed', 'expired', 'url unreachable' => 'failed', default => null,
                };
                $ranks = ['accepted' => 0, 'failed' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3];
                if ($status !== null && isset($ranks[$message->status]) && $ranks[$status] >= $ranks[$message->status]) {
                    $message->status = $status;
                    $message->reason = $status === 'failed' ? 'Fonnte melaporkan pesan gagal dikirim.' : null;
                }
                if ($event->state === '0' && in_array($message->status, ['accepted', 'sent'], true)) {
                    $message->reason = 'Fonnte melaporkan state 0; pesan belum diterima konsumen.';
                }
                if ($event->state_id && ! $message->state_id) {
                    $message->state_id = $event->state_id;
                }
                $message->save();
                DB::table('whatsapp_webhook_events')->where('id', $id)->update(['processed_at' => now()]);
            }, 3);
        }
    }
}
