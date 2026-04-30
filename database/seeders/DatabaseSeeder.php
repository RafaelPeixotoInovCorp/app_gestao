<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Modules;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        foreach (Modules::ITEMS as $module) {
            Permission::firstOrCreate(['name' => "module.{$module['key']}.view"]);
            Permission::firstOrCreate(['name' => "module.{$module['key']}.create"]);
            Permission::firstOrCreate(['name' => "module.{$module['key']}.update"]);
            Permission::firstOrCreate(['name' => "module.{$module['key']}.delete"]);
        }

        $admin = User::firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Administrador',
            'password' => 'password',
        ]);

        $admin->assignRole($adminRole);
    }
}
