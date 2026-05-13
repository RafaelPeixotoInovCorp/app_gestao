<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se o pedido não for HTTPS, cookies com flag Secure nunca são aceites pelo browser.
 * Isto alinha a config à realidade do transporte (evita 419 por sessão que não “gruda” em http://*.test).
 */
final class AlignSessionCookieSecureForRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure() && config('session.secure')) {
            config(['session.secure' => false]);
        }

        return $next($request);
    }
}
