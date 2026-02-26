<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 1. TrustProxies: Hanya aktifkan di production (behind reverse proxy/Nginx)
        // Di localhost, trust proxies '*' menyebabkan redirect HTTPS yang salah
        if (env('APP_ENV') !== 'local') {
            $middleware->trustProxies(at: '*');
        }

        // 2. Middleware SecurityCheck yang Anda miliki
        $middleware->append(\App\Http\Middleware\SecurityCheck::class);

        // 3. Middleware untuk Inertia dan Assets
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
