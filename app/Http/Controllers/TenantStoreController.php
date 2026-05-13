<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantOnboardingBootstrap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantStoreController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('create', Tenant::class), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tenants', 'slug')],
        ]);

        $slug = $data['slug'] ?? Str::slug(Str::limit($data['name'], 48, ''));
        $slug = $slug !== '' ? $slug : 'org';
        $slug = $this->uniqueSlug($slug);

        $tenant = Tenant::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'settings' => TenantOnboardingBootstrap::initialSettings(),
        ]);

        $tenant->users()->attach($request->user()->id, ['role' => 'owner']);
        $request->user()->forceFill(['last_tenant_id' => $tenant->id])->save();

        TenantOnboardingBootstrap::finalizeNewTenant($tenant, $request->user(), $request);

        return redirect()
            ->route('tenants.setup.wizard')
            ->with('success', 'Nova organização criada. Configura a identidade e o acesso em poucos passos.');
    }

    private function uniqueSlug(string $base): string
    {
        $slug = Str::lower($base);
        $candidate = $slug;
        $i = 0;
        while (Tenant::query()->where('slug', $candidate)->exists()) {
            $i++;
            $candidate = $slug.'-'.$i;
        }

        return $candidate;
    }
}
