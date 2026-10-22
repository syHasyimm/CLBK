<?php

namespace App\Services\WhatsApp;

use App\Models\AppSetting;
use App\Models\WhatsAppConnection;
use Illuminate\Contracts\Encryption\DecryptException;

final class WhatsAppSettings
{
    public function values(): array
    {
        $saved = AppSetting::find('whatsapp')?->value_json;

        return array_replace(['enabled' => false, 'weighing_enabled' => true, 'payment_enabled' => true], is_array($saved) ? $saved : []);
    }

    public function configured(): bool
    {
        $connection = WhatsAppConnection::find(1);

        return $connection
            ? $connection->status === 'connected' && $this->token() !== ''
            : config('whatsapp.enabled') && $this->token() !== '';
    }

    public function token(): string
    {
        $connection = WhatsAppConnection::find(1);
        if (! $connection) {
            return trim((string) config('whatsapp.token'));
        }
        try {
            return (string) $connection->token;
        } catch (DecryptException) {
            return '';
        }
    }

    public function connection(): array
    {
        $connection = WhatsAppConnection::find(1);
        $hasToken = $this->token() !== '';

        return [
            'has_token' => $hasToken,
            'status' => $connection && ! $hasToken ? 'error' : ($connection?->status ?? 'unchecked'),
            'message' => $connection && ! $hasToken ? 'Token tersimpan tidak dapat dibaca. Simpan ulang token perangkat.' : $connection?->message,
            'device_number' => $connection?->device_number,
            'checked_at' => $connection?->checked_at?->toIso8601String(),
        ];
    }

    public function active(string $event): bool
    {
        $settings = $this->values();
        $kind = str_starts_with($event, 'weighing_') ? 'weighing_enabled' : 'payment_enabled';

        return $this->configured() && $settings['enabled'] && ($event === 'test' || $settings[$kind]);
    }
}
