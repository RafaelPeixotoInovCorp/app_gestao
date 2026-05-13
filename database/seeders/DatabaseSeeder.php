<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use App\Services\TenantRoleProvisioner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('roles')->where('name', 'operador')->update(['name' => 'operacional']);

        $tenant = Tenant::query()->firstOrCreate(
            ['slug' => 'organizacao-principal'],
            ['name' => 'Organização principal', 'settings' => []],
        );

        TenantRoleProvisioner::syncForTenant($tenant);

        $adminUser = User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Administrador',
            'password' => 'password',
        ]);

        if (! $adminUser->tenants()->whereKey($tenant->id)->exists()) {
            $adminUser->tenants()->attach($tenant->id, ['role' => 'owner']);
        }

        app(TenantContext::class)->set($tenant);
        if (! $adminUser->hasRole('admin')) {
            $adminUser->assignRole(Role::findByName('admin', 'web'));
        }
        app(TenantContext::class)->set(null);

        $adminUser->forceFill(['last_tenant_id' => $tenant->id])->save();
    }
}
