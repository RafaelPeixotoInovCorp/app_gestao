<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment(['local', 'testing'])) {
            return $next($request);
        }

        if (! $request->isSecure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
