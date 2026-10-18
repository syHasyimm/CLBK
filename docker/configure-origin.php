<?php

// Preserve nginx's validated effective host, together with the configured
// public port. Debian's security-hardened FastCGI defaults drop the Host port;
// forwarding the raw HTTP Host header would undo that security protection.
$origin = parse_url((string) getenv('APP_URL'));
if (! is_array($origin) || empty($origin['host'])
    || ! in_array($origin['scheme'] ?? null, ['http', 'https'], true)
    || isset($origin['user']) || isset($origin['pass'])
    || isset($origin['query']) || isset($origin['fragment'])
    || ! in_array($origin['path'] ?? '', ['', '/'], true)) {
    fwrite(STDERR, "APP_URL harus berupa origin HTTP/HTTPS tanpa kredensial, path, query, atau fragment.\n");
    exit(1);
}

$port = $origin['port'] ?? ($origin['scheme'] === 'https' ? 443 : 80);
if ($port < 1 || $port > 65535) {
    fwrite(STDERR, "Port APP_URL tidak valid.\n");
    exit(1);
}

if (file_put_contents('/etc/nginx/clbk-origin.conf', 'set $clbk_external_port '.$port.";\n") === false) {
    fwrite(STDERR, "Konfigurasi origin Nginx tidak dapat ditulis.\n");
    exit(1);
}
