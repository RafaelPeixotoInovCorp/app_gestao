<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use App\Services\TenantContext;
use App\Support\Modules;
use App\Support\RoleLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserAdminController extends Controller
{
    use PasswordValidationRules;

    public function index(Request $request): Response
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'users');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);

        $tenantId = app(TenantContext::class)->id();
        abort_if($tenantId === null, 403);

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $sort = (string) $request->query('sort', 'latest');

        $query = User::query()
            ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
            ->with(['roles:id,name'])
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false));

        $query->when($sort === 'name_asc', fn ($q) => $q->orderBy('name')->orderBy('id'))
            ->when($sort === 'name_desc', fn ($q) => $q->orderByDesc('name')->orderByDesc('id'))
            ->when($sort === 'oldest', fn ($q) => $q->orderBy('id'))
            ->when(! in_array($sort, ['name_asc', 'name_desc', 'oldest'], true), fn ($q) => $q->orderByDesc('id'));

        $users = $query->paginate(15)->withQueryString()->through(function (User $user): array {
            $role = $user->roles->first();

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role_id' => $role?->id,
                'role_name' => $role?->name,
                'role_display' => $role ? RoleLabels::display($role->name) : null,
                'is_active' => $user->is_active,
            ];
        });

        $tenant = app(TenantContext::class)->tenant();
        $entitlements = app(SubscriptionEntitlementService::class);
        $usage = $tenant ? [
            'users_count' => $entitlements->userCount($tenant),
            'max_users' => $entitlements->maxUsers($tenant),
            'unlimited_users' => $entitlements->maxUsers($tenant) === null,
            'can_add' => $entitlements->canAddUser($tenant),
        ] : null;

        return Inertia::render('Modules/Users/Index', [
            'module' => $module,
            'users' => $users,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'sort' => $sort,
            ],
            'canCreate' => ($request->user()->hasRole('admin') || $request->user()->can('module.users.create'))
                && ($usage['can_add'] ?? true),
            'canUpdate' => $request->user()->hasRole('admin') || $request->user()->can('module.users.update'),
            'canDelete' => $request->user()->hasRole('admin') || $request->user()->can('module.users.delete'),
            'authUserId' => $request->user()->id,
            'subscriptionUsage' => $usage,
        ]);
    }

    public function create(Request $request): Response
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'users');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.users.create'), 403);

        $tenant = app(TenantContext::class)->tenant();
        $entitlements = app(SubscriptionEntitlementService::class);
        $usage = $tenant ? [
            'users_count' => $entitlements->userCount($tenant),
            'max_users' => $entitlements->maxUsers($tenant),
            'unlimited_users' => $entitlements->maxUsers($tenant) === null,
            'can_add' => $entitlements->canAddUser($tenant),
        ] : null;

        return Inertia::render('Modules/Users/Form', [
            'module' => $module,
            'user' => null,
            'roles' => $this->rolesForForm(),
            'subscriptionUsage' => $usage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'users');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.users.create'), 403);

        $tenant = app(TenantContext::class)->tenant();
        abort_if($tenant === null, 403);

        if (! app(SubscriptionEntitlementService::class)->canAddUser($tenant)) {
            throw ValidationException::withMessages([
                'email' => 'O limite de utilizadores do plano foi atingido. Atualize o plano ou remova utilizadores.',
            ]);
        }

        $teamsKey = config('permission.column_names.team_foreign_key');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where($teamsKey, $tenant->id)),
            ],
            'password' => $this->passwordRules(),
            'is_active' => ['required', 'boolean'],
        ]);

        $role = Role::query()->whereKey($data['role_id'])->where($teamsKey, $tenant->id)->firstOrFail();

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_encrypted' => $data['phone'] ?? null,
            'password' => $data['password'],
            'is_active' => $data['is_active'],
        ]);

        $user->syncRoles([$role]);

        $user->tenants()->syncWithoutDetaching([$tenant->id => ['role' => 'member']]);

        return redirect()->route('modules.show', 'users')->with('success', 'Utilizador criado com sucesso.');
    }

    public function edit(Request $request, User $user): Response
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'users');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.users.update'), 403);

        $tenantId = app(TenantContext::class)->id();
        abort_unless($tenantId !== null && $user->tenants()->whereKey($tenantId)->exists(), 403);

        $user->load('roles:id,name');
        $role = $user->roles->first();

        return Inertia::render('Modules/Users/Form', [
            'module' => $module,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'role_id' => $role?->id,
                'is_active' => $user->is_active,
            ],
            'roles' => $this->rolesForForm(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'users');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.users.update'), 403);

        $tenantId = app(TenantContext::class)->id();
        abort_unless($tenantId !== null && $user->tenants()->whereKey($tenantId)->exists(), 403);

        $teamsKey = config('permission.column_names.team_foreign_key');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where($teamsKey, $tenantId)),
            ],
            'is_active' => ['required', 'boolean'],
        ];

        if ($request->filled('password')) {
            $rules['password'] = $this->passwordRules();
        }

        $data = $request->validate($rules);

        $this->assertSafeSelfAdminChanges($request->user(), $user, (bool) $data['is_active'], (int) $data['role_id']);

        $role = Role::query()->whereKey($data['role_id'])->where($teamsKey, $tenantId)->firstOrFail();

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_encrypted' => $data['phone'] ?? null,
            'is_active' => $data['is_active'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);
        $user->syncRoles([$role]);

        return redirect()->route('modules.show', 'users')->with('success', 'Utilizador atualizado com sucesso.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === 'users');
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.users.delete'), 403);

        $tenantId = app(TenantContext::class)->id();
        abort_unless($tenantId !== null && $user->tenants()->whereKey($tenantId)->exists(), 403);

        if ($user->id === $request->user()->id) {
            return redirect()->route('modules.show', 'users')->with('error', 'Não pode remover a sua própria conta.');
        }

        if ($user->hasRole('admin')) {
            $otherAdmins = User::query()
                ->role('admin')
                ->whereKeyNot($user->id)
                ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
                ->count();
            if ($otherAdmins === 0) {
                return redirect()->route('modules.show', 'users')->with('error', 'Não pode remover o último administrador.');
            }
        }

        $user->delete();

        return redirect()->route('modules.show', 'users')->with('success', 'Utilizador removido.');
    }

    private function assertSafeSelfAdminChanges(User $actor, User $target, bool $newActive, int $newRoleId): void
    {
        if ($actor->id !== $target->id) {
            return;
        }

        if (! $newActive) {
            throw ValidationException::withMessages([
                'is_active' => 'Não pode desativar a sua própria conta.',
            ]);
        }

        $tenantId = app(TenantContext::class)->id();
        $teamsKey = config('permission.column_names.team_foreign_key');

        $adminRole = Role::query()
            ->where('name', 'admin')
            ->when($tenantId !== null, fn ($q) => $q->where($teamsKey, $tenantId))
            ->first();
        if (! $adminRole) {
            return;
        }

        if (! $target->hasRole('admin')) {
            return;
        }

        if ((int) $newRoleId === (int) $adminRole->id) {
            return;
        }

        $otherActiveAdmins = User::query()
            ->role('admin')
            ->where('is_active', true)
            ->whereKeyNot($target->id)
            ->when($tenantId !== null, fn ($q) => $q->whereHas('tenants', fn ($q2) => $q2->where('tenants.id', $tenantId)))
            ->count();

        if ($otherActiveAdmins === 0) {
            throw ValidationException::withMessages([
                'role_id' => 'Tem de existir pelo menos outro administrador ativo antes de alterar o seu grupo.',
            ]);
        }
    }

    /**
     * @return Collection<int, array{id: int, name: string, label: string}>
     */
    private function rolesForForm()
    {
        $tenantId = app(TenantContext::class)->id();
        $teamsKey = config('permission.column_names.team_foreign_key');

        return Role::query()
            ->when($tenantId !== null, fn ($q) => $q->where($teamsKey, $tenantId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Role $r): array => [
                'id' => $r->id,
                'name' => $r->name,
                'label' => RoleLabels::display($r->name),
            ])
            ->sortBy(fn (array $r): array => [RoleLabels::sortWeight($r['name']), $r['label']])
            ->values();
    }
}
