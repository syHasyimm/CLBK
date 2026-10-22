<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class FonnteClient
{
    public function send(string $target, string $body): array
    {
        try {
            // A POST timeout may already have been accepted. Never retry it blindly.
            $response = Http::asForm()->acceptJson()->withHeaders(['Authorization' => app(WhatsAppSettings::class)->token()])
                ->connectTimeout(5)->timeout(20)->withoutRedirecting()->post('https://api.fonnte.com/send', [
                    'target' => $target, 'message' => $body, 'countryCode' => '0', 'connectOnly' => true,
                ]);
        } catch (ConnectionException) {
            return ['status' => 'uncertain', 'reason' => 'Koneksi terputus atau timeout; periksa Fonnte sebelum mengirim ulang.'];
        }
        $data = $response->json();
        $success = is_array($data) ? ($data['status'] ?? $data['Status'] ?? null) : null;
        $id = is_array($data['id'] ?? null) ? ($data['id'][0] ?? null) : null;
        if ($response->successful() && $success === true && is_scalar($id) && preg_match('/\A[\w.-]{1,100}\z/', (string) $id)) {
            return ['status' => 'accepted', 'reason' => null, 'provider_id' => (string) $id];
        }
        if ($success === false) {
            $reason = is_string($data['reason'] ?? null) ? strtolower($data['reason']) : '';
            if (str_contains($reason, 'disconnect') || str_contains($reason, 'not connect')) {
                return ['status' => 'retrying', 'reason' => 'Perangkat Fonnte tidak terhubung.'];
            }
            $description = match (true) {
                str_contains($reason, 'token') => 'Token Fonnte tidak valid.',
                str_contains($reason, 'quota') => 'Kuota Fonnte tidak mencukupi.',
                str_contains($reason, 'target') => 'Nomor tujuan ditolak Fonnte.',
                default => 'Permintaan ditolak Fonnte. Periksa konfigurasi dan nomor tujuan.',
            };

            return ['status' => 'failed', 'reason' => $description];
        }

        return ['status' => 'uncertain', 'reason' => 'Respons Fonnte belum dapat dipastikan (HTTP '.$response->status().'). Periksa riwayat Fonnte.'];
    }

    public function checkConnection(string $token): array
    {
        try {
            $response = Http::asForm()->acceptJson()->withHeaders(['Authorization' => $token])
                ->connectTimeout(5)->timeout(15)->withoutRedirecting()->post('https://api.fonnte.com/device');
        } catch (ConnectionException) {
            return ['status' => 'error', 'message' => 'Fonnte belum dapat dihubungi. Periksa koneksi lalu uji kembali.', 'device_number' => null];
        }

        $data = $response->json();
        if ($response->status() === 429) {
            return ['status' => 'error', 'message' => 'Batas pemeriksaan Fonnte tercapai. Tunggu sebentar lalu uji kembali.', 'device_number' => null];
        }
        if (is_array($data) && ($data['status'] ?? null) === false
            && is_string($data['reason'] ?? null) && str_contains(strtolower($data['reason']), 'token')) {
            return ['status' => 'invalid', 'message' => 'Token Fonnte tidak valid. Periksa token perangkat yang Anda masukkan.', 'device_number' => null];
        }
        if ($response->successful() && is_array($data) && ($data['status'] ?? null) === true) {
            $status = is_string($data['device_status'] ?? null) ? strtolower($data['device_status']) : '';
            $device = is_scalar($data['device'] ?? null) ? (string) $data['device'] : '';
            $device = preg_match('/\A\+?[0-9]{5,20}\z/', $device) ? $device : null;
            if ($status === 'connect') {
                return ['status' => 'connected', 'message' => 'Berhasil terhubung. Token valid dan perangkat WhatsApp terhubung.', 'device_number' => $device];
            }
            if ($status === 'disconnect') {
                return ['status' => 'disconnected', 'message' => 'Token valid, tetapi perangkat WhatsApp belum terhubung. Hubungkan perangkat di dashboard Fonnte lalu uji kembali.', 'device_number' => $device];
            }
        }

        return ['status' => 'error', 'message' => 'Status koneksi belum dapat dipastikan. Uji kembali beberapa saat lagi.', 'device_number' => null];
    }
}
