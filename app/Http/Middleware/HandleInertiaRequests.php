<?php

namespace App\Http\Middleware;

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
            in_array($key, ['users', 'permissions'], true) => 'Acessos',
            str_starts_with($key, 'settings-') => 'Configurações',
            default => 'Outros',
        };
    }

    private function groupedModulesFor(?\App\Models\User $user): array
    {
        if (! $user) {
            return [];
        }

        $groups = [];

        foreach (Modules::ITEMS as $module) {
            $canView = $user->hasRole('admin') || $user->can("module.{$module['key']}.view");
            if (! $canView) {
                continue;
            }

            $group = $this->moduleGroupLabel($module['key']);
            if (! array_key_exists($group, $groups)) {
                $groups[$group] = [];
            }

            $groups[$group][] = [
                'key' => $module['key'],
                'label' => $module['label'],
                'slug' => $module['slug'],
                'route' => $module['route'],
            ];
        }

        return array_map(
            fn (string $label, array $items) => ['label' => $label, 'items' => $items],
            array_keys($groups),
            array_values($groups)
        );
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
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'navigation' => [
                'moduleGroups' => $this->groupedModulesFor($request->user()),
            ],
            'csrf_token' => csrf_token(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
