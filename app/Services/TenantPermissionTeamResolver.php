<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

final class TenantPermissionTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): int|string|null
    {
        return app(TenantContext::class)->id();
    }

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        if ($id === null) {
            app(TenantContext::class)->set(null);

            return;
        }

        $tenant = Tenant::query()->find($id);
        app(TenantContext::class)->set($tenant);
    }
}
