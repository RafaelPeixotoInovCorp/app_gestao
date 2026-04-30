<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEntityRequest;
use App\Http\Requests\UpdateEntityRequest;
use App\Models\Country;
use App\Models\Entity;
use App\Services\Vies\ViesLookupService;
use App\Support\Modules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EntityController extends Controller
{
    public function index(Request $request, string $slug): Response
    {
        abort_unless(in_array($slug, ['clients', 'suppliers'], true), 404);

        $moduleKey = $slug === 'clients' ? 'clients' : 'suppliers';
        Gate::authorize('access-module', $moduleKey);

        $module = Arr::first(Modules::ITEMS, fn (array $m) => $m['slug'] === $slug);
        abort_if(! $module, 404);

        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $sort = (string) $request->query('sort', 'latest');

        $query = Entity::query()
            ->with('country:id,name,iso_alpha_2')
            ->when($slug === 'clients', fn ($q) => $q->where('is_client', true))
            ->when($slug === 'suppliers', fn ($q) => $q->where('is_supplier', true))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('website', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($q) => $q->where('active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort === 'name_asc', fn ($q) => $q->orderBy('name'))
            ->when($sort === 'name_desc', fn ($q) => $q->orderByDesc('name'))
            ->when($sort === 'latest', fn ($q) => $q->orderByDesc('id'))
            ->when($sort === 'oldest', fn ($q) => $q->orderBy('id'));

        $entities = $query->paginate(15)->withQueryString()->through(fn (Entity $e) => [
            'id' => $e->id,
            'nif' => $e->decryptedNif(),
            'name' => $e->name,
            'phone' => $e->decryptedPhone(),
            'mobile' => $e->decryptedMobile(),
            'website' => $e->website,
            'email' => $e->email,
            'is_client' => $e->is_client,
            'is_supplier' => $e->is_supplier,
            'active' => $e->active,
        ]);

        return Inertia::render('Entities/Index', [
            'module' => $module,
            'slug' => $slug,
            'entities' => $entities,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'sort' => $sort,
            ],
            'canCreate' => $request->user()->hasRole('admin')
                || ($slug === 'clients' && $request->user()->can('module.clients.create'))
                || ($slug === 'suppliers' && $request->user()->can('module.suppliers.create')),
            'canUpdate' => $request->user()->hasRole('admin')
                || ($slug === 'clients' && $request->user()->can('module.clients.update'))
                || ($slug === 'suppliers' && $request->user()->can('module.suppliers.update')),
            'canDelete' => $request->user()->hasRole('admin')
                || ($slug === 'clients' && $request->user()->can('module.clients.delete'))
                || ($slug === 'suppliers' && $request->user()->can('module.suppliers.delete')),
        ]);
    }

    public function create(Request $request): Response
    {
        $kind = $request->query('kind', 'client');
        abort_unless(in_array($kind, ['client', 'supplier'], true), 404);

        $moduleKey = $kind === 'client' ? 'clients' : 'suppliers';
        Gate::authorize('access-module', $moduleKey);

        abort_unless(
            $request->user()->hasRole('admin')
            || ($kind === 'client' && $request->user()->can('module.clients.create'))
            || ($kind === 'supplier' && $request->user()->can('module.suppliers.create')),
            403
        );

        return Inertia::render('Entities/Form', [
            'entity' => null,
            'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'iso_alpha_2']),
            'defaultKind' => $kind,
        ]);
    }

    public function store(StoreEntityRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $entity = new Entity;
        $entity->fill([
            'is_client' => $data['is_client'],
            'is_supplier' => $data['is_supplier'],
            'name' => $data['name'],
            'postal_code' => $data['postal_code'] ?? null,
            'city' => $data['city'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'website' => $data['website'] ?: null,
            'email' => $data['email'] ?? null,
            'gdpr_consent' => $data['gdpr_consent'] ?? null,
            'notes' => $data['notes'] ?? null,
            'active' => $data['active'],
        ]);
        $entity->nif_hash = Entity::hashNif($data['nif']);
        $entity->nif_encrypted = $data['nif'];
        $entity->address_encrypted = $data['address'] ?? null;
        $entity->phone_encrypted = $data['phone'] ?? null;
        $entity->mobile_encrypted = $data['mobile'] ?? null;
        $entity->save();

        $slug = $data['is_client'] ? 'clients' : 'suppliers';

        return redirect()->route('modules.show', ['slug' => $slug])
            ->with('success', 'Entidade criada com sucesso.');
    }

    public function edit(Request $request, Entity $entity): Response
    {
        abort_unless(
            ($entity->is_client && Gate::check('access-module', 'clients'))
            || ($entity->is_supplier && Gate::check('access-module', 'suppliers')),
            403
        );

        abort_unless(
            $request->user()->hasRole('admin')
            || ($entity->is_client && $request->user()->can('module.clients.update'))
            || ($entity->is_supplier && $request->user()->can('module.suppliers.update')),
            403
        );

        return Inertia::render('Entities/Form', [
            'entity' => [
                'id' => $entity->id,
                'is_client' => $entity->is_client,
                'is_supplier' => $entity->is_supplier,
                'nif' => $entity->decryptedNif(),
                'name' => $entity->name,
                'address' => $entity->decryptedAddress(),
                'postal_code' => $entity->postal_code,
                'city' => $entity->city,
                'country_id' => $entity->country_id,
                'phone' => $entity->decryptedPhone(),
                'mobile' => $entity->decryptedMobile(),
                'website' => $entity->website,
                'email' => $entity->email,
                'gdpr_consent' => $entity->gdpr_consent,
                'notes' => $entity->notes,
                'active' => $entity->active,
            ],
            'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'iso_alpha_2']),
            'defaultKind' => $entity->is_client && ! $entity->is_supplier ? 'client' : ($entity->is_supplier && ! $entity->is_client ? 'supplier' : 'client'),
        ]);
    }

    public function update(UpdateEntityRequest $request, Entity $entity): RedirectResponse
    {
        abort_unless(
            ($entity->is_client && Gate::check('access-module', 'clients'))
            || ($entity->is_supplier && Gate::check('access-module', 'suppliers')),
            403
        );

        $data = $request->validated();

        $entity->fill([
            'is_client' => $data['is_client'],
            'is_supplier' => $data['is_supplier'],
            'name' => $data['name'],
            'postal_code' => $data['postal_code'] ?? null,
            'city' => $data['city'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'website' => $data['website'] ?: null,
            'email' => $data['email'] ?? null,
            'gdpr_consent' => $data['gdpr_consent'] ?? null,
            'notes' => $data['notes'] ?? null,
            'active' => $data['active'],
        ]);
        $entity->nif_hash = Entity::hashNif($data['nif']);
        $entity->nif_encrypted = $data['nif'];
        $entity->address_encrypted = $data['address'] ?? null;
        $entity->phone_encrypted = $data['phone'] ?? null;
        $entity->mobile_encrypted = $data['mobile'] ?? null;
        $entity->save();

        $slug = $data['is_client'] ? 'clients' : 'suppliers';

        return redirect()->route('modules.show', ['slug' => $slug])
            ->with('success', 'Entidade actualizada com sucesso.');
    }

    public function destroy(Request $request, Entity $entity): RedirectResponse
    {
        abort_unless(
            ($entity->is_client && Gate::check('access-module', 'clients'))
            || ($entity->is_supplier && Gate::check('access-module', 'suppliers')),
            403
        );

        abort_unless(
            $request->user()->hasRole('admin')
            || ($entity->is_client && $request->user()->can('module.clients.delete'))
            || ($entity->is_supplier && $request->user()->can('module.suppliers.delete')),
            403
        );

        $slug = $request->query('from', $entity->is_client ? 'clients' : 'suppliers');
        $entity->delete();

        return redirect()->route('modules.show', ['slug' => $slug])
            ->with('success', 'Entidade removida.');
    }

    public function viesLookup(Request $request, ViesLookupService $vies): JsonResponse
    {
        abort_unless(
            Gate::check('access-module', 'clients') || Gate::check('access-module', 'suppliers'),
            403
        );

        $validated = $request->validate([
            'country' => ['required', 'string', 'size:2'],
            'vat' => ['required', 'string', 'max:20'],
        ]);

        $result = $vies->check($validated['country'], $validated['vat']);

        return response()->json($result);
    }
}
