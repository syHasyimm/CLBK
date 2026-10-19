<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <title inertia>{{ config('app.name', 'CLBK TOKE') }}</title>
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <meta name="theme-color" content="#164b37">
        <link rel="apple-touch-icon" href="/pwa/apple-touch-icon.png">
        @if (! app(\Illuminate\Foundation\Vite::class)->isRunningHot())
            <link rel="manifest" href="/build/manifest.webmanifest">
        @endif

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
