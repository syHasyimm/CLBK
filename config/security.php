<?php

return [
    // Keep empty for direct TLS. Only these proxy IPs may supply client IP/scheme headers.
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('TRUSTED_PROXIES', ''))
    ), fn (string $proxy): bool => $proxy !== '')),
];
