<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function create(User $user): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->tenants()->wherePivot('role', 'owner')->exists();
    }

    public function switchTo(User $user, Tenant $tenant): bool
    {
        return $user->tenants()->whereKey($tenant->getKey())->exists();
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->tenants()
            ->whereKey($tenant->getKey())
            ->wherePivot('role', 'owner')
            ->exists();
    }
}
