<?php

namespace App\Http\Controllers;

use App\Services\WhatsApp\WebhookStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FonnteWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, WebhookStatus $webhooks): JsonResponse
    {
        $configured = (string) config('whatsapp.webhook_secret');
        abort_unless(strlen($configured) >= 32 && hash_equals($configured, $secret), 404);
        abort_if(strlen($request->getContent()) > 8192, 413);
        foreach (['id', 'stateid', 'state', 'device'] as $field) {
            if (is_int($request->input($field))) {
                $request->merge([$field => (string) $request->input($field)]);
            }
        }
        $data = $request->validate(['id' => ['nullable', 'regex:/\A[\w.-]{1,100}\z/'],
            'stateid' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', 'string', 'max:40'],
            'state' => ['nullable', 'string', 'max:40'], 'device' => ['nullable', 'regex:/\A\+?[0-9]{5,20}\z/']]);
        abort_unless(isset($data['id']) || isset($data['stateid']), 422);
        $device = (string) config('whatsapp.device');
        abort_if($device !== '' && ltrim((string) ($data['device'] ?? ''), '+') !== ltrim($device, '+'), 403);
        $webhooks->receive($data);

        return response()->json(['ok' => true]);
    }
}
