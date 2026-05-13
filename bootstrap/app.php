<?php

use App\Http\Middleware\AlignSessionCookieSecureForRequest;
use App\Http\Middleware\EnsureTenantSubscriptionAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetCurrentTenant;
use App\Http\Middleware\UseRequestUrlGenerator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Herd / reverse proxies: correct scheme + host so Ziggy/Inertia POST URLs match
        // the browser (avoids CSRF 419 when HTTPS front-end talks to PHP over HTTP).
        $middleware->trustProxies(at: '*');

        $middleware->web(
            prepend: [
                AlignSessionCookieSecureForRequest::class,
                UseRequestUrlGenerator::class,
            ],
            append: [
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
                ForceHttps::class,
                SecurityHeaders::class,
            ],
        );

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'tenant' => SetCurrentTenant::class,
            'subscription' => EnsureTenantSubscriptionAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
