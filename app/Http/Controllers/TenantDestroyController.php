<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class TenantDestroyController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless($request->user()->can('delete', $tenant), 403);

        $user = $request->user();
        $wasCurrent = (int) $request->session()->get('current_tenant_id') === (int) $tenant->getKey();

        $nextTenant = null;
        if ($wasCurrent) {
            $nextTenant = $user->tenants()
                ->where('tenants.id', '!=', $tenant->getKey())
                ->orderBy('tenants.name')
                ->first();
        }

        DB::transaction(static fn () => $tenant->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($wasCurrent) {
            if ($nextTenant) {
                $request->session()->put('current_tenant_id', $nextTenant->id);
                $user->forceFill(['last_tenant_id' => $nextTenant->id])->save();
            } else {
                $request->session()->forget('current_tenant_id');
                $user->forceFill(['last_tenant_id' => null])->save();
            }
        }

        app(TenantContext::class)->set(null);

        $user->unsetRelation('tenants');

        if ($user->tenants()->count() === 0) {
            return redirect()
                ->route('tenants.create')
                ->with('success', 'Organização eliminada. Cria uma nova organização para continuar.');
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Organização eliminada.');
    }
}
