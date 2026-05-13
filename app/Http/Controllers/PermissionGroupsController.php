<?php

namespace App\Http\Controllers;

use App\Support\Modules;
use App\Support\RoleLabels;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class PermissionGroupsController extends Controller
{
    public function index(Request $request): Response
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'permissions');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);

        $groups = Role::query()
            ->withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'display_name' => RoleLabels::display($role->name),
                'permissions_count' => $role->permissions_count,
            ])
            ->sortBy(fn (array $row): array => [
                RoleLabels::sortWeight($row['name']),
                $row['display_name'],
            ])
            ->values();

        return Inertia::render('Modules/Permissions/Index', [
            'module' => $module,
            'groups' => $groups,
        ]);
    }
}
