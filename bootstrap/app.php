<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustConfiguredProxies;
use App\Support\Database\ReportDatabaseFailure;
use App\Support\Database\WriteConflict;
use Illuminate\Contracts\Session\Middleware\AuthenticatesSessions;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(
            at: fn (): array => [
                '^'.preg_quote((string) parse_url(config('app.url'), PHP_URL_HOST), '/').'$',
            ],
            subdomains: false,
        );
        $middleware->replace(TrustProxies::class, TrustConfiguredProxies::class);
        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias(['active' => EnsureActiveUser::class]);
        $middleware->prependToPriorityList(
            before: AuthenticatesSessions::class,
            prepend: EnsureActiveUser::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(fn (Throwable $exception) => app(ReportDatabaseFailure::class)->report($exception));
        $exceptions->respond(fn (Response $response) => app(SecurityHeaders::class)->apply(request(), $response)
        );

        $exceptions->render(function (WriteConflict $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            return back()->withErrors(['operation' => $exception->getMessage()]);
        });
    })->create();
