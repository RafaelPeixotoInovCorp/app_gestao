<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantContext;
use App\Services\TenantOnboardingState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Permission\PermissionRegistrar;

class TenantSetupWizardController extends Controller
{
    public function __construct(
        private readonly TenantOnboardingState $onboardingState,
    ) {}

    public function edit(Request $request): InertiaResponse|RedirectResponse
    {
        $tenant = $this->tenantOrAbort($request);

        abort_unless($request->user()->hasRole('admin'), 403);

        $summary = $this->onboardingState->summary($tenant, $request->user());
        if ($summary === null) {
            return redirect()->route('dashboard');
        }

        if (($summary['wizard_completed'] ?? false) && ! $request->boolean('reopen')) {
            return redirect()->route('dashboard');
        }

        $step = min(3, max(1, (int) $request->query('step', 1)));

        return Inertia::render('Tenants/SetupWizard', [
            'step' => $step,
            'branding' => Arr::get($tenant->settings, 'branding', []),
            'tenant_name' => $tenant->name,
            'links' => [
                'users' => route('modules.show', ['slug' => 'users']),
                'permissions' => route('modules.show', ['slug' => 'permissions']),
                'subscription' => route('subscription.dashboard'),
                'dashboard' => route('dashboard'),
            ],
        ]);
    }

    public function updateBranding(Request $request): RedirectResponse
    {
        $tenant = $this->tenantOrAbort($request);
        abort_unless($request->user()->hasRole('admin'), 403);
        abort_unless($this->onboardingState->summary($tenant, $request->user()) !== null, 403);

        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'accent' => ['nullable', 'string', 'max:9'],
        ]);

        $accent = $data['accent'] ?? '#4f46e5';
        if (! is_string($accent) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $accent)) {
            $accent = '#4f46e5';
        }

        $this->onboardingState->mergeBranding($tenant, [
            'display_name' => $data['display_name'] ?? '',
            'tagline' => $data['tagline'] ?? '',
            'accent' => $accent,
        ]);

        $this->onboardingState->mergeChecklist($tenant, ['branding' => true]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('tenants.setup.wizard', ['step' => 2]);
    }

    public function acknowledgeTeam(Request $request): RedirectResponse
    {
        $tenant = $this->tenantOrAbort($request);
        abort_unless($request->user()->hasRole('admin'), 403);
        abort_unless($this->onboardingState->summary($tenant, $request->user()) !== null, 403);

        $this->onboardingState->mergeChecklist($tenant, ['team' => true]);

        return redirect()->route('tenants.setup.wizard', ['step' => 3]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $tenant = $this->tenantOrAbort($request);
        abort_unless($request->user()->hasRole('admin'), 403);
        abort_unless($this->onboardingState->summary($tenant, $request->user()) !== null, 403);

        $this->onboardingState->mergeChecklist($tenant, ['permissions' => true]);
        $this->onboardingState->markWizardCompleted($tenant);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Configuração inicial concluída. Usa a checklist no painel para reveres subscrição e outros detalhes.');
    }

    public function skip(Request $request): RedirectResponse
    {
        $tenant = $this->tenantOrAbort($request);
        abort_unless($request->user()->hasRole('admin'), 403);
        abort_unless($this->onboardingState->summary($tenant, $request->user()) !== null, 403);

        $this->onboardingState->markWizardCompleted($tenant);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Podes retomar a configuração mais tarde a partir do painel.');
    }

    public function updateChecklist(Request $request): RedirectResponse
    {
        $tenant = $this->tenantOrAbort($request);
        abort_unless($request->user()->hasRole('admin'), 403);
        abort_unless($this->onboardingState->summary($tenant, $request->user()) !== null, 403);

        $data = $request->validate([
            'key' => ['required', 'string', Rule::in(['branding', 'team', 'permissions', 'subscription'])],
            'done' => ['required', 'boolean'],
        ]);

        $this->onboardingState->mergeChecklist($tenant, [$data['key'] => $data['done']]);

        return redirect()->back();
    }

    private function tenantOrAbort(Request $request): Tenant
    {
        app(TenantContext::class)->resolveFromRequest($request);
        $tenant = app(TenantContext::class)->tenant();
        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }
}
