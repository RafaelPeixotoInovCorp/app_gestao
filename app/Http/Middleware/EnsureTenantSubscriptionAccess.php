<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\SubscriptionEntitlementService;
use App\Services\TenantContext;
use App\Services\TenantSubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionAccess
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SubscriptionEntitlementService $entitlements,
        private readonly TenantSubscriptionService $subscriptionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantContext->tenant();
        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        $this->subscriptionService->syncExpiredTrialIfNeeded($tenant);

        if ($this->entitlements->hasAccess($tenant)) {
            return $next($request);
        }

        if ($request->routeIs(
            'subscription.dashboard',
            'subscription.change-plan',
            'subscription.cancel',
            'tenants.switch',
            'tenants.append.create',
            'tenants.store',
            'tenants.destroy',
            'tenants.setup.wizard',
            'tenants.setup.branding',
            'tenants.setup.team',
            'tenants.setup.complete',
            'tenants.setup.skip',
            'tenants.setup.checklist',
            'profile.edit',
            'profile.update',
            'profile.destroy',
            'logout',
        )) {
            return $next($request);
        }

        if ($request->routeIs('dashboard')) {
            return $next($request);
        }

        return redirect()
            ->route('subscription.dashboard')
            ->with('error', 'A subscrição desta organização terminou. Escolha um plano para continuar.');
    }
}
