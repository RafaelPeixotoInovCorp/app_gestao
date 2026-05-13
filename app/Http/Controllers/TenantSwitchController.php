<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class TenantSwitchController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
        ]);

        $tenant = Tenant::query()->findOrFail($data['tenant_id']);

        abort_unless($request->user()->can('switchTo', $tenant), 403);

        $request->session()->put('current_tenant_id', $tenant->id);
        $request->user()->forceFill(['last_tenant_id' => $tenant->id])->save();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->back();
    }
}
