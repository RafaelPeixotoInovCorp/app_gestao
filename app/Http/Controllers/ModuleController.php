<?php

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\CalendarTask;
use App\Models\ModuleRecord;
use App\Support\Modules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    private function isEntityScopedOrdersModule(string $slug): bool
    {
        return in_array($slug, ['customer-orders', 'supplier-orders'], true);
    }

    private function relatedEntitiesForModule(string $slug)
    {
        if ($slug === 'customer-orders') {
            return Entity::query()->where('is_client', true)->orderBy('name')->get(['id', 'name']);
        }

        if ($slug === 'supplier-orders') {
            return Entity::query()->where('is_supplier', true)->orderBy('name')->get(['id', 'name']);
        }

        return collect();
    }

    public function index(): Response
    {
        return Inertia::render('Modules/Index', [
            'modules' => Modules::ITEMS,
            'stats' => [
                'clients' => Entity::query()->where('is_client', true)->count(),
                'suppliers' => Entity::query()->where('is_supplier', true)->count(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === $slug);
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);

        if (in_array($slug, ['clients', 'suppliers'], true)) {
            return app(EntityController::class)->index($request, $slug);
        }
        if ($slug === 'calendar') {
            $tasks = CalendarTask::query()
                ->orderBy('is_done')
                ->orderBy('due_date')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString();

            return Inertia::render('Modules/Calendar/Index', [
                'module' => $module,
                'tasks' => $tasks,
                'canCreate' => $request->user()->hasRole('admin') || $request->user()->can('module.calendar.create'),
                'canUpdate' => $request->user()->hasRole('admin') || $request->user()->can('module.calendar.update'),
                'canDelete' => $request->user()->hasRole('admin') || $request->user()->can('module.calendar.delete'),
            ]);
        }

        $selectedEntityId = $this->isEntityScopedOrdersModule($slug)
            ? $request->integer('entity_id')
            : null;
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $sort = (string) $request->query('sort', 'latest');

        $records = ModuleRecord::query()
            ->where('module_key', $module['key'])
            ->with('entity:id,name')
            ->when(
                $this->isEntityScopedOrdersModule($slug) && $selectedEntityId,
                fn ($q) => $q->where('entity_id', $selectedEntityId)
            )
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($q) => $q->where('active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('active', false))
            ->when($sort === 'title_asc', fn ($q) => $q->orderBy('title'))
            ->when($sort === 'title_desc', fn ($q) => $q->orderByDesc('title'))
            ->when($sort === 'latest', fn ($q) => $q->orderByDesc('id'))
            ->when($sort === 'oldest', fn ($q) => $q->orderBy('id'))
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Modules/Records/Index', [
            'module' => $module,
            'records' => $records,
            'relatedEntities' => $this->relatedEntitiesForModule($slug),
            'requireEntitySelection' => $this->isEntityScopedOrdersModule($slug),
            'selectedEntityId' => $selectedEntityId,
            'filters' => [
                'q' => $search,
                'status' => $status,
                'sort' => $sort,
            ],
            'canCreate' => $request->user()->hasRole('admin') || $request->user()->can("module.{$module['key']}.create"),
            'canUpdate' => $request->user()->hasRole('admin') || $request->user()->can("module.{$module['key']}.update"),
            'canDelete' => $request->user()->hasRole('admin') || $request->user()->can("module.{$module['key']}.delete"),
        ]);
    }

    public function create(Request $request, string $slug): Response
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === $slug);
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can("module.{$module['key']}.create"), 403);

        return Inertia::render('Modules/Records/Form', [
            'module' => $module,
            'record' => null,
            'relatedEntities' => $this->relatedEntitiesForModule($slug),
            'requireEntitySelection' => $this->isEntityScopedOrdersModule($slug),
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === $slug);
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can("module.{$module['key']}.create"), 403);

        $entityRules = ['nullable', 'integer', 'exists:entities,id'];
        if ($this->isEntityScopedOrdersModule($slug)) {
            $entityRules = ['required', 'integer', 'exists:entities,id'];
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
            'entity_id' => $entityRules,
        ]);

        if ($this->isEntityScopedOrdersModule($slug)) {
            $isValidEntity = Entity::query()
                ->whereKey($data['entity_id'])
                ->when($slug === 'customer-orders', fn ($q) => $q->where('is_client', true))
                ->when($slug === 'supplier-orders', fn ($q) => $q->where('is_supplier', true))
                ->exists();
            abort_unless($isValidEntity, 422, 'Selecione um cliente/fornecedor válido.');
        }

        ModuleRecord::query()->create([
            'module_key' => $module['key'],
            'entity_id' => $data['entity_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'active' => $data['active'],
        ]);

        return redirect()->route('modules.show', $slug)->with('success', 'Registo criado com sucesso.');
    }

    public function edit(Request $request, string $slug, ModuleRecord $record): Response
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === $slug);
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can("module.{$module['key']}.update"), 403);
        abort_unless($record->module_key === $module['key'], 404);

        return Inertia::render('Modules/Records/Form', [
            'module' => $module,
            'record' => $record,
            'relatedEntities' => $this->relatedEntitiesForModule($slug),
            'requireEntitySelection' => $this->isEntityScopedOrdersModule($slug),
        ]);
    }

    public function update(Request $request, string $slug, ModuleRecord $record): RedirectResponse
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === $slug);
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can("module.{$module['key']}.update"), 403);
        abort_unless($record->module_key === $module['key'], 404);

        $entityRules = ['nullable', 'integer', 'exists:entities,id'];
        if ($this->isEntityScopedOrdersModule($slug)) {
            $entityRules = ['required', 'integer', 'exists:entities,id'];
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
            'entity_id' => $entityRules,
        ]);

        if ($this->isEntityScopedOrdersModule($slug)) {
            $isValidEntity = Entity::query()
                ->whereKey($data['entity_id'])
                ->when($slug === 'customer-orders', fn ($q) => $q->where('is_client', true))
                ->when($slug === 'supplier-orders', fn ($q) => $q->where('is_supplier', true))
                ->exists();
            abort_unless($isValidEntity, 422, 'Selecione um cliente/fornecedor válido.');
        }

        $record->update([
            'entity_id' => $data['entity_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?: null,
            'active' => $data['active'],
        ]);

        return redirect()->route('modules.show', $slug)->with('success', 'Registo atualizado com sucesso.');
    }

    public function destroy(Request $request, string $slug, ModuleRecord $record): RedirectResponse
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === $slug);
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can("module.{$module['key']}.delete"), 403);
        abort_unless($record->module_key === $module['key'], 404);

        $record->delete();

        return redirect()->route('modules.show', $slug)->with('success', 'Registo removido.');
    }

    public function storeCalendarTask(Request $request): RedirectResponse
    {
        Gate::authorize('access-module', 'calendar');
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.calendar.create'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
        ]);

        CalendarTask::query()->create([
            'title' => $data['title'],
            'notes' => $data['notes'] ?: null,
            'due_date' => $data['due_date'] ?? null,
            'is_done' => false,
        ]);

        return redirect()->route('modules.show', 'calendar')->with('success', 'Tarefa criada.');
    }

    public function toggleCalendarTask(Request $request, CalendarTask $task): RedirectResponse
    {
        Gate::authorize('access-module', 'calendar');
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.calendar.update'), 403);

        $task->update([
            'is_done' => ! $task->is_done,
        ]);

        return redirect()->route('modules.show', 'calendar')->with('success', 'Tarefa atualizada.');
    }

    public function destroyCalendarTask(Request $request, CalendarTask $task): RedirectResponse
    {
        Gate::authorize('access-module', 'calendar');
        abort_unless($request->user()->hasRole('admin') || $request->user()->can('module.calendar.delete'), 403);

        $task->delete();

        return redirect()->route('modules.show', 'calendar')->with('success', 'Tarefa removida.');
    }
}
