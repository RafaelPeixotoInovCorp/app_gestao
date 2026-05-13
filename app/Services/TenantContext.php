<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Http\Request;

final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function resolveFromRequest(Request $request): void
    {
        $user = $request->user();
        if (! $user) {
            $this->tenant = null;

            return;
        }

        $memberships = $user->tenants()->orderBy('tenants.name')->get();

        if ($memberships->isEmpty()) {
            $this->tenant = null;

            return;
        }

        $sessionId = $request->session()->get('current_tenant_id');
        $tenant = null;
        if ($sessionId) {
            $tenant = $memberships->firstWhere('id', (int) $sessionId);
        }
        if (! $tenant && $user->last_tenant_id) {
            $tenant = $memberships->firstWhere('id', (int) $user->last_tenant_id);
        }
        if (! $tenant) {
            $tenant = $memberships->first();
        }

        $this->tenant = $tenant;
        $request->session()->put('current_tenant_id', $tenant->id);
    }
}
