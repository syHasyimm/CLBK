<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TrustConfiguredProxies
{
    public function handle(Request $request, Closure $next): Response
    {
        // Read after configuration boot; never infer wildcard trust from the request host.
        $request::setTrustedProxies(
            config('security.trusted_proxies', []),
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PORT,
        );

        return $next($request);
    }
}
