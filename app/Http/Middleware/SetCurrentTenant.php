<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $this->tenantContext->resolveFromRequest($request);

        if ($this->tenantContext->tenant() === null) {
            if ($request->routeIs(
                'tenants.create',
                'tenants.onboarding.store',
                'logout',
                'verification.*',
                'password.*',
                'profile.*',
            )) {
                return $next($request);
            }

            return redirect()->route('tenants.create');
        }

        return $next($request);
    }
}
