<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\WhatsAppConnection;
use App\Services\AuditService;
use App\Services\WhatsApp\FonnteClient;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Support\Database\WriteTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WhatsAppConnectionController extends Controller
{
    public function update(Request $request, WhatsAppSettings $settings): JsonResponse
    {
        Gate::authorize('manage-settings');
        $data = $request->validate(['token' => ['bail', 'required', 'string', 'max:2048', 'regex:/\A[!-~]+\z/']], [
            'token.required' => 'Masukkan token perangkat Fonnte.',
            'token.regex' => 'Token tidak boleh mengandung spasi atau baris baru.',
            'token.max' => 'Token terlalu panjang.',
        ]);
        app(WriteTransaction::class)->run(function () use ($request, $data, $settings) {
            Gate::forUser($request->user()->fresh())->authorize('manage-settings');
            $connection = WhatsAppConnection::find(1) ?? new WhatsAppConnection;
            $connection->id = 1;
            $connection->fill(['token' => $data['token'], 'revision' => (string) Str::uuid(), 'status' => 'unchecked',
                'message' => 'Token tersimpan. Uji koneksi sebelum mengaktifkan pengiriman.', 'device_number' => null, 'checked_at' => null])->save();
            AppSetting::updateOrCreate(['key' => 'whatsapp'], ['value_json' => array_replace($settings->values(), ['enabled' => false]), 'updated_by' => $request->user()->id]);
            app(AuditService::class)->record($request->user(), 'whatsapp-token-updated', $connection, null, ['token_changed' => true, 'status' => 'unchecked']);
        });

        return response()->json(['connection' => $settings->connection(), 'configured' => false])->header('Cache-Control', 'no-store');
    }

    public function check(Request $request, WhatsAppSettings $settings, FonnteClient $client): JsonResponse
    {
        Gate::authorize('manage-settings');
        $connection = WhatsAppConnection::find(1);
        $revision = $connection?->revision;
        $token = $settings->token();
        if ($token === '') {
            throw ValidationException::withMessages(['token' => 'Simpan token perangkat Fonnte terlebih dahulu.']);
        }
        // A network request must not hold the business write lock.
        $result = $client->checkConnection($token);
        app(WriteTransaction::class)->run(function () use ($request, $revision, $token, $result) {
            Gate::forUser($request->user()->fresh())->authorize('manage-settings');
            $current = WhatsAppConnection::find(1);
            abort_if($current?->revision !== $revision, 409, 'Token sudah berubah. Uji kembali token terbaru.');
            if (! $current) {
                $current = new WhatsAppConnection;
                $current->id = 1;
                $current->fill(['token' => $token, 'revision' => (string) Str::uuid()]);
            }
            $current->fill($result + ['checked_at' => now()])->save();
            app(AuditService::class)->record($request->user(), 'whatsapp-connection-checked', $current, null, ['status' => $result['status']]);
        });

        return response()->json(['connection' => $settings->connection(), 'configured' => $settings->configured()])->header('Cache-Control', 'no-store');
    }
}
