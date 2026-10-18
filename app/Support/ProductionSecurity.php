<?php

namespace App\Support;

use RuntimeException;

final class ProductionSecurity
{
    public function validate(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        // Even a boot-time configuration failure must render a generic production response.
        $debug = config('app.debug');
        config(['app.debug' => false]);

        foreach (config('security.trusted_proxies', []) as $proxy) {
            $parts = explode('/', $proxy, 2);
            $ip = $parts[0];
            $maxPrefix = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;
            if (! filter_var($ip, FILTER_VALIDATE_IP)
                || (isset($parts[1]) && (! ctype_digit($parts[1]) || (int) $parts[1] < 1 || (int) $parts[1] > $maxPrefix))) {
                throw new RuntimeException('TRUSTED_PROXIES hanya boleh berisi IP atau CIDR proxy eksplisit; wildcard dan seluruh internet tidak diizinkan.');
            }
        }

        $url = parse_url((string) config('app.url'));
        if (! is_array($url) || ($url['scheme'] ?? null) !== 'https' || empty($url['host'])
            || isset($url['user']) || isset($url['pass']) || isset($url['query'])
            || isset($url['fragment']) || ! in_array($url['path'] ?? '', ['', '/'], true)) {
            throw new RuntimeException('APP_URL produksi harus berupa origin HTTPS canonical tanpa kredensial, path, query, atau fragment.');
        }

        if ($debug) {
            throw new RuntimeException('APP_DEBUG harus false pada produksi.');
        }

        if (config('session.secure') !== true || config('session.http_only') !== true
            || ! in_array(config('session.same_site'), ['lax', 'strict'], true)) {
            throw new RuntimeException('Cookie sesi produksi wajib Secure, HttpOnly, dan SameSite lax atau strict.');
        }

        if (! config('mail.enabled')) {
            if (config('mail.default') !== 'array') {
                throw new RuntimeException('Email nonaktif harus menggunakan transport array tanpa pengiriman atau pencatatan isi email.');
            }

            return;
        }

        if (! $this->deliversMail((string) config('mail.default'))) {
            throw new RuntimeException('Mailer produksi wajib mengirim email; transport log/array dan fallback ke log tidak diizinkan.');
        }
    }

    private function deliversMail(string $name, array $visited = []): bool
    {
        if (in_array($name, $visited, true)) {
            return false;
        }

        $mailer = config('mail.mailers.'.$name);
        $transport = is_array($mailer) ? ($mailer['transport'] ?? null) : null;
        if (! is_string($transport) || $transport === '' || in_array($transport, ['log', 'array'], true)) {
            return false;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $mailer['mailers'] ?? [];
            if (! is_array($children) || $children === []) {
                return false;
            }
            foreach ($children as $child) {
                if (! is_string($child) || ! $this->deliversMail($child, [...$visited, $name])) {
                    return false;
                }
            }
        }

        return true;
    }
}
