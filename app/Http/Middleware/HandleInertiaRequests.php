<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use App\Services\TenantContext;
use App\Services\TenantOnboardingState;
use App\Support\Modules;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    private function moduleGroupLabel(string $key): string
    {
        return match (true) {
            in_array($key, ['clients', 'suppliers', 'contacts'], true) => 'Entidades e Contactos',
            in_array($key, ['proposals', 'calendar', 'customer-orders', 'supplier-orders', 'work-orders'], true) => 'Operações',
            str_starts_with($key, 'financial-') => 'Financeiro',
            in_array($key, ['users', 'permissions'], true) => 'Gestão de acessos',
            str_starts_with($key, 'settings-') => 'Configurações',
            default => 'Outros',
        };
    }

    private function moduleGroupKind(string $key): string
    {
        return match (true) {
            in_array($key, ['clients', 'suppliers', 'contacts'], true) => 'entities',
            in_array($key, ['proposals', 'calendar', 'customer-orders', 'supplier-orders', 'work-orders'], true) => 'operations',
            str_starts_with($key, 'financial-') => 'financial',
            in_array($key, ['users', 'permissions'], true) => 'access',
            str_starts_with($key, 'settings-') => 'settings',
            default => 'other',
        };
    }

    private function groupedModulesFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $tenant = app(TenantContext::class)->tenant();
        $entitlements = app(SubscriptionEntitlementService::class);

        $groups = [];
        $groupKinds = [];

        foreach (Modules::ITEMS as $module) {
            $canView = $user->hasRole('admin') || $user->can("module.{$module['key']}.view");
            if (! $canView) {
                continue;
            }

            if ($tenant && ! $entitlements->canAccessModule($tenant, $module['key'])) {
                continue;
            }

            $group = $this->moduleGroupLabel($module['key']);
            if (! array_key_exists($group, $groups)) {
                $groups[$group] = [];
                $groupKinds[$group] = $this->moduleGroupKind($module['key']);
            }

            $groups[$group][] = [
                'key' => $module['key'],
                'label' => $module['label'],
                'slug' => $module['slug'],
                'route' => $module['route'],
            ];
        }

        return array_map(
            fn (string $label, array $items) => [
                'label' => $label,
                'kind' => $groupKinds[$label] ?? 'other',
                'items' => $items,
            ],
            array_keys($groups),
            array_values($groups)
        );
    }

    /**
     * @return array{current: ?array{id: int, name: string, slug: string}, items: list<array{id: int, name: string, slug: string, role: string, can_delete: bool}>, canCreateAdditional: bool}
     */
    private function tenantNavigation(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return ['current' => null, 'items' => [], 'canCreateAdditional' => false];
        }

        app(TenantContext::class)->resolveFromRequest($request);

        $current = app(TenantContext::class)->tenant();
        $memberships = $user->tenants()->orderBy('tenants.name')->get();

        return [
            'current' => $current ? [
                'id' => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
            ] : null,
            'items' => $memberships->map(fn (Tenant $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'role' => (string) ($t->pivot->role ?? 'member'),
                'can_delete' => $user->can('delete', $t),
            ])->values()->all(),
            'canCreateAdditional' => $user->can('create', Tenant::class),
        ];
    }

    private function displayAppName(): string
    {
        $name = trim((string) config('app.name'));

        if ($name === '' || strcasecmp($name, 'Laravel') === 0) {
            return 'Gestão';
        }

        return $name;
    }

    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        if ($request->user()) {
            app(TenantContext::class)->resolveFromRequest($request);
        }

        $tenant = $request->user() ? app(TenantContext::class)->tenant() : null;
        $subscriptionSummary = null;
        if ($tenant) {
            $subscriptionSummary = app(SubscriptionEntitlementService::class)->summaryForTenant($tenant);
        }

        $tenantOnboarding = null;
        if ($request->user() && $tenant) {
            $tenantOnboarding = app(TenantOnboardingState::class)->summary($tenant, $request->user());
        }

        return [
            ...parent::share($request),
            'app' => [
                'name' => $this->displayAppName(),
            ],
            'auth' => [
                'user' => $request->user(),
            ],
            'navigation' => [
                'moduleGroups' => $this->groupedModulesFor($request->user()),
            ],
            'tenantNavigation' => $this->tenantNavigation($request),
            'tenantOnboarding' => $tenantOnboarding,
            'subscription' => $subscriptionSummary,
            'csrf_token' => csrf_token(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
