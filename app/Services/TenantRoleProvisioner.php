<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\Modules;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class TenantRoleProvisioner
{
    public static function syncForTenant(Tenant $tenant): void
    {
        $previous = app(TenantContext::class)->tenant();
        app(TenantContext::class)->set($tenant);

        try {
            foreach (Modules::ITEMS as $module) {
                Permission::firstOrCreate(['name' => "module.{$module['key']}.view"]);
                Permission::firstOrCreate(['name' => "module.{$module['key']}.create"]);
                Permission::firstOrCreate(['name' => "module.{$module['key']}.update"]);
                Permission::firstOrCreate(['name' => "module.{$module['key']}.delete"]);
            }

            $allPermissions = Permission::query()->orderBy('name')->get();
            $viewOnly = Permission::query()->where('name', 'like', '%.view')->orderBy('name')->get();

            $operacionalPermissions = Permission::query()
                ->where(function ($q): void {
                    $q->where('name', 'like', '%.view')
                        ->orWhere(function ($q2): void {
                            $q2->where('name', 'not like', 'module.users.%')
                                ->where('name', 'not like', 'module.permissions.%')
                                ->where(function ($q3): void {
                                    $q3->where('name', 'like', '%.create')
                                        ->orWhere('name', 'like', '%.update')
                                        ->orWhere('name', 'like', '%.delete');
                                });
                        });
                })
                ->orderBy('name')
                ->get();

            $admin = Role::findOrCreate('admin', 'web');
            $basico = Role::findOrCreate('basico', 'web');
            $operacional = Role::findOrCreate('operacional', 'web');

            $admin->syncPermissions($allPermissions);
            $basico->syncPermissions($viewOnly);
            $operacional->syncPermissions($operacionalPermissions);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } finally {
            app(TenantContext::class)->set($previous);
        }
    }
}
