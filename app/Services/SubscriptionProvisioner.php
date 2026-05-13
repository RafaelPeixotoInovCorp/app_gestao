<?php

namespace App\Services;

use App\Models\Tenant;

final class SubscriptionProvisioner
{
    public static function ensureForTenant(Tenant $tenant): void
    {
        if ($tenant->subscription()->exists()) {
            return;
        }

        app(TenantSubscriptionService::class)->provisionNewTenantSubscription($tenant);
    }
}
