<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

final class TenantOnboardingBootstrap
{
    /**
     * @return array<string, mixed>
     */
    public static function initialSettings(): array
    {
        return [
            'branding' => [
                'display_name' => null,
                'tagline' => '',
                'accent' => '#4f46e5',
            ],
            'onboarding' => [
                'wizard_completed_at' => null,
                'checklist' => [
                    'branding' => false,
                    'team' => false,
                    'permissions' => false,
                    'subscription' => false,
                ],
            ],
        ];
    }

    public static function finalizeNewTenant(Tenant $tenant, User $owner, ?Request $request = null): void
    {
        SubscriptionProvisioner::ensureForTenant($tenant);

        TenantRoleProvisioner::syncForTenant($tenant);

        app(TenantContext::class)->set($tenant);
        $owner->syncRoles([Role::findByName('admin', 'web')]);
        app(TenantContext::class)->set(null);

        if ($request?->hasSession()) {
            $request->session()->put('current_tenant_id', $tenant->id);
        }
    }
}
