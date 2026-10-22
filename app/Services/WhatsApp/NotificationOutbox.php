<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppMessage;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Weighing;
use App\Models\WhatsAppConnection;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class NotificationOutbox
{
    public function weighing(Weighing $record, bool $voided = false): void
    {
        $event = $voided ? 'weighing_voided' : 'weighing_posted';
        if ($voided) {
            $this->cancelPending('weighing', $record->id);
        }
        $this->record($event, $record->customer, 'weighing', $record->id, $record->number, $record->version,
            fn () => app(MessageTemplate::class)->weighing($record, $voided));
    }

    public function payment(CustomerPayment $record, bool $reversed = false): void
    {
        if ($reversed) {
            $this->cancelPending('payment', $record->id);
        }
        $this->record($reversed ? 'payment_reversed' : 'payment_posted', $record->customer, 'payment', $record->id,
            $record->number, $record->version, fn () => app(MessageTemplate::class)->payment($record, $reversed));
    }

    private function record(string $event, Customer $customer, string $source, int $id, string $number, int $version, \Closure $body): void
    {
        if (! app(WhatsAppSettings::class)->values()['enabled']) {
            return;
        }
        $target = PhoneNumber::normalize($customer->phone);
        $reason = ! $customer->is_active || ! $customer->whatsapp_enabled ? 'Notifikasi konsumen tidak aktif.'
            : ($target === null ? 'Nomor WhatsApp tidak valid atau kosong.' : null);
        if (! app(WhatsAppSettings::class)->active($event)) {
            $reason = 'Jenis notifikasi atau pengiriman server tidak aktif.';
        }
        $message = WhatsAppMessage::firstOrCreate(['event_key' => implode(':', [$event, $id, $version, $customer->id])], [
            'uuid' => (string) Str::uuid(), 'event' => $event, 'customer_id' => $customer->id, 'customer_name' => $customer->name,
            'source_type' => $source, 'source_id' => $id, 'source_number' => $number, 'target' => $target,
            'body' => $body(), 'status' => $reason === null ? 'pending' : 'skipped', 'reason' => $reason, 'next_attempt_at' => now(),
        ]);
        if ($message->wasRecentlyCreated && $message->status === 'pending') {
            DB::afterCommit(fn () => $this->dispatchSafely($message->id));
        }
    }

    public function test(string $target, int $actorId, string $key): WhatsAppMessage
    {
        $message = WhatsAppMessage::firstOrCreate(['event_key' => 'test:'.$actorId.':'.$key], [
            'uuid' => (string) Str::uuid(), 'event' => 'test', 'customer_name' => 'Tes oleh manajer',
            'source_type' => 'test', 'source_number' => 'TES', 'target' => $target,
            'body' => app(MessageTemplate::class)->test(), 'status' => 'pending', 'next_attempt_at' => now(),
        ]);
        if ($message->wasRecentlyCreated) {
            DB::afterCommit(fn () => $this->dispatchSafely($message->id));
        }
        if ($message->target !== $target) {
            throw ValidationException::withMessages(['target' => 'Nomor tes berubah. Muat ulang formulir sebelum mencoba lagi.']);
        }

        return $message;
    }

    public function dispatchSafely(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $message = WhatsAppMessage::lockForUpdate()->find($id);
                if (! $message || ! in_array($message->status, ['pending', 'retrying'], true)
                    || $message->next_attempt_at?->isFuture() || ($message->queued_at && $message->queued_at->gt(now()->subMinutes(5)))
                    || ! app(WhatsAppSettings::class)->active($message->event)) {
                    return;
                }
                // Same MySQL connection: queue insert and queued_at are committed atomically.
                Queue::connection('whatsapp')->push(new SendWhatsAppMessage($message->id, $message->uuid), '', 'whatsapp');
                $message->update(['queued_at' => now()]);
            }, 3);
        } catch (Throwable) {
            // Outbox stays durable; the scheduler recovers it without failing business writes.
            Log::warning('WhatsApp queue dispatch deferred.', ['message_id' => $id]);
        }
    }

    public function recover(): int
    {
        if (app()->isDownForMaintenance() || ! Schema::hasTable('whatsapp_messages')) {
            return 0;
        }
        DB::transaction(function () {
            $stale = WhatsAppMessage::where('status', 'processing')->where('processing_at', '<', now()->subMinutes(3))->lockForUpdate()->get();
            foreach ($stale as $message) {
                $reason = 'Proses terhenti; periksa riwayat Fonnte sebelum mengirim ulang.';
                $message->update(['status' => 'uncertain', 'reason' => $reason]);
                DB::table('whatsapp_attempts')->where('whatsapp_message_id', $message->id)->whereNull('finished_at')
                    ->update(['status' => 'uncertain', 'reason' => $reason, 'finished_at' => now()]);
            }
        }, 3);
        app(WebhookStatus::class)->reconcile();
        $ids = WhatsAppMessage::whereIn('status', ['pending', 'retrying'])->where('next_attempt_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('queued_at')->orWhere('queued_at', '<', now()->subMinutes(5)))
            ->orderBy('id')->limit(200)->pluck('id');
        foreach ($ids as $id) {
            $this->dispatchSafely($id);
        }

        return $ids->count();
    }

    private function cancelPending(string $source, int $id): void
    {
        WhatsAppMessage::where('source_type', $source)->where('source_id', $id)->whereIn('status', ['pending', 'retrying'])
            ->update(['status' => 'cancelled', 'reason' => 'Transaksi asal dibatalkan atau dibalik.', 'updated_at' => now()]);
    }

    public function pauseAfterRestore(): void
    {
        $settings = app(WhatsAppSettings::class)->values();
        WhatsAppConnection::query()->update(['status' => 'unchecked', 'checked_at' => null,
            'revision' => (string) Str::uuid(), 'message' => 'Uji kembali koneksi setelah restore.', 'updated_at' => now()]);
        DB::table('whatsapp_attempts')->whereNull('finished_at')->update([
            'status' => 'held', 'reason' => 'Proses dikarantina setelah restore.', 'finished_at' => now(),
        ]);
        if (AppSetting::find('whatsapp')) {
            AppSetting::whereKey('whatsapp')->update(['value_json' => json_encode(array_replace($settings, ['enabled' => false]), JSON_THROW_ON_ERROR), 'updated_by' => null]);
        }
        WhatsAppMessage::whereIn('status', ['pending', 'retrying', 'processing', 'uncertain'])->update([
            'status' => 'held', 'reason' => 'Dikarantina setelah restore; tidak dikirim ulang otomatis.', 'queued_at' => null, 'updated_at' => now(),
        ]);
        DB::table('jobs')->where('queue', 'whatsapp')->delete();
    }
}
