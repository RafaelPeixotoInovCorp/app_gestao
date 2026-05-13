<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alinha o UrlGenerator ao pedido actual (Host + esquema).
 *
 * Útil com Herd/Valet quando o domínio .test não coincide com APP_URL, ou com
 * proxies: evita scripts a carregar noutro host (404) e
 * deixam o Inertia/Vue com ecrã em branco.
 */
final class UseRequestUrlGenerator
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() !== '') {
            URL::forceRootUrl($request->getSchemeAndHttpHost());
        }

        return $next($request);
    }
}
