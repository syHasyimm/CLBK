<?php

return [
    // Legacy environment fallback; a verified encrypted token in settings takes precedence.
    'enabled' => (bool) env('WHATSAPP_ENABLED', false),
    'token' => env('FONNTE_TOKEN', ''),
    'webhook_secret' => env('FONNTE_WEBHOOK_SECRET', ''),
    'device' => env('FONNTE_DEVICE', ''),
];
