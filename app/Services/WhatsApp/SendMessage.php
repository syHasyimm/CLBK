<?php

namespace App\Services\WhatsApp;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Weighing;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;

final class SendMessage
{
    public function send(int $id, string $uuid): void
    {
        if (app()->isDownForMaintenance()) {
            return;
        }
        $message = DB::transaction(function () use ($id, $uuid) {
            $message = WhatsAppMessage::where('uuid', $uuid)->lockForUpdate()->find($id);
            if (! $message || ! in_array($message->status, ['pending', 'retrying'], true) || $message->next_attempt_at?->isFuture()) {
                return null;
            }
            if ($reason = $this->ineligible($message)) {
                $message->update(['status' => 'skipped', 'reason' => $reason]);

                return null;
            }
            $message->update(['status' => 'processing', 'processing_at' => now(), 'attempts' => $message->attempts + 1]);
            DB::table('whatsapp_attempts')->insert(['whatsapp_message_id' => $message->id, 'number' => $message->attempts,
                'status' => 'processing', 'created_at' => now()]);

            return $message;
        }, 3);
        if (! $message) {
            return;
        }

        $result = app(FonnteClient::class)->send($message->target, $message->body);
        DB::transaction(function () use ($message, $result) {
            $current = WhatsAppMessage::lockForUpdate()->findOrFail($message->id);
            if ($current->uuid !== $message->uuid || $current->status !== 'processing') {
                return;
            }
            $status = $result['status'];
            if ($status === 'retrying' && $message->attempts >= 4) {
                $status = 'failed';
            }
            $current->update(['status' => $status, 'reason' => $result['reason'], 'provider_id' => $result['provider_id'] ?? null,
                'accepted_at' => $status === 'accepted' ? now() : null, 'queued_at' => null, 'processing_at' => null,
                'next_attempt_at' => $status === 'retrying' ? now()->addSeconds([60, 300, 900][$message->attempts - 1]) : null]);
            DB::table('whatsapp_attempts')->where('whatsapp_message_id', $message->id)->where('number', $message->attempts)
                ->update(['status' => $status, 'reason' => $result['reason'], 'finished_at' => now()]);
        }, 3);
        app(WebhookStatus::class)->reconcile();
    }

    public function ineligible(WhatsAppMessage $message): ?string
    {
        if (! app(WhatsAppSettings::class)->active($message->event)) {
            return 'Pengiriman atau jenis notifikasi tidak aktif.';
        }
        if ($message->event === 'test') {
            return PhoneNumber::normalize($message->target) === null ? 'Nomor tes tidak valid.' : null;
        }
        $customer = Customer::find($message->customer_id);
        if (! $customer?->is_active || ! $customer->whatsapp_enabled) {
            return 'Notifikasi konsumen tidak aktif.';
        }
        if (PhoneNumber::normalize($customer->phone) !== $message->target) {
            return 'Nomor konsumen berubah sejak pesan dibuat.';
        }
        $source = $message->source_type === 'weighing' ? Weighing::find($message->source_id) : CustomerPayment::find($message->source_id);
        $expected = match ($message->event) {
            'weighing_voided' => 'voided', 'payment_reversed' => 'reversed', default => 'posted',
        };

        return $source?->status === $expected ? null : 'Status transaksi asal sudah berubah.';
    }
}
