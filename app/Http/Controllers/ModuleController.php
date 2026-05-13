<?php

namespace App\Http\Controllers;

use App\Models\CalendarTask;
use App\Models\Entity;
use App\Models\ModuleRecord;
use App\Models\SubscriptionAuditLog;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use App\Services\TenantContext;
use App\Support\Modules;
use App\Support\OrderStatus;
use App\Support\ProposalStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    private function usesEntityPicker(string $slug): bool
    {
        return in_array($slug, ['customer-orders', 'supplier-orders', 'proposals', 'financial-customer-ledger'], true);
    }

    /** @return array<int, mixed> */
    private function entityIdValidationRules(string $slug): array
    {
        $tenantId = app(TenantContext::class)->id();
        $exists = Rule::exists('entities', 'id');
        if ($tenantId !== null) {
            $exists = $exists->where(fn ($query) => $query->where('tenant_id', $tenantId));
        }

        if ($this->usesEntityPicker($slug)) {
            return ['required', 'integer', $exists];
        }

        return ['nullable', 'integer', $exists];
    }

    private function isCustomerLedgerModule(string $slug): bool
    {
        return $slug === 'financial-customer-ledger';
    }

    private function isBankAccountsModule(string $slug): bool
    {
        return $slug === 'financial-bank-accounts';
    }

    private function isProposalsModule(string $slug): bool
    {
        return $slug === 'proposals';
    }

    private function isOrdersModule(string $slug): bool
    {
        return in_array($slug, ['customer-orders', 'supplier-orders'], true);
    }

    private function relatedEntitiesForModule(string $slug)
    {
        if ($slug === 'customer-orders' || $slug === 'proposals' || $slug === 'financial-customer-ledger') {
            return Entity::query()->where('is_client', true)->orderBy('name')->get(['id', 'name']);
        }

        if ($slug === 'supplier-orders') {
            return Entity::query()->where('is_supplier', true)->orderBy('name')->get(['id', 'name']);
        }

        return collect();
    }

    /** @return array<string, mixed> */
    private function proposalFieldRules(): array
    {
        return [
            'proposal_number' => ['required', 'string', 'max:64'],
            'proposal_date' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:proposal_date'],
            'proposal_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'proposal_status' => ['required', Rule::enum(ProposalStatus::class)],
        ];
    }

    /** @return array<string, mixed> */
    private function orderFieldRules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:64'],
            'order_date' => ['required', 'date'],
            'order_valid_until' => ['nullable', 'date', 'after_or_equal:order_date'],
            'order_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'order_status' => ['required', Rule::enum(OrderStatus::class)],
        ];
    }

    /** @return array<string, mixed> */
    private function ledgerFieldRules(): array
    {
        return [
            'ledger_entry_date' => ['required', 'date'],
            'ledger_debit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'ledger_credit' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function assertLedgerDebitCreditXor(array $data): void
    {
        $debit = (float) ($data['ledger_debit'] ?? 0);
        $credit = (float) ($data['ledger_credit'] ?? 0);
        $hasDebit = $debit > 0;
        $hasCredit = $credit > 0;
        if ($hasDebit === $hasCredit) {
            throw ValidationException::withMessages([
                'ledger_debit' => 'Indique apenas débito ou apenas crédito, com valor maior que zero.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function bankAccountFieldRules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:255'],
            'iban' => ['required', 'string', 'max:64'],
            'swift' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function normalizeIbanInput(string $iban): string
    {
        return strtoupper(preg_replace('/\s+/', '', $iban));
    }

    private function normalizeSwiftInput(?string $swift): ?string
    {
        if ($swift === null || trim($swift) === '') {
            return null;
        }

        return strtoupper(preg_replace('/\s+/', '', $swift));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeAndValidateBankAccountInput(array $data): array
    {
        $iban = $this->normalizeIbanInput((string) ($data['iban'] ?? ''));
        $swift = $this->normalizeSwiftInput($data['swift'] ?? null);

        if (strlen($iban) < 15 || strlen($iban) > 34 || ! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $iban)) {
            throw ValidationException::withMessages([
                'iban' => 'O IBAN não é válido.',
            ]);
        }

        if ($swift !== null && ! preg_match('/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $swift)) {
            throw ValidationException::withMessages([
                'swift' => 'O SWIFT/BIC não é válido.',
            ]);
        }

        $data['iban'] = $iban;
        $data['swift'] = $swift;

        return $data;
    }

    private function transformBankAccountRecordForFrontend(ModuleRecord $record): ModuleRecord
    {
        return $record->append(['iban', 'swift'])->makeHidden(['bank_iban_encrypted', 'bank_swift_encrypted']);
    }

    public function index(): Response
    {
        $tenant = app(TenantContext::class)->tenant();

        $tenantId = app(TenantContext::class)->id();
        $usersCount = 0;
        if ($tenantId !== null) {
            $usersCount = (int) User::query()
                ->whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))
                ->count();
        }

        $orgsCount = 0;
        $authUser = Auth::user();
        if ($authUser) {
            $orgsCount = (int) $authUser->tenants()->count();
        }

        $auditPreview = [];
        if ($tenant) {
            $auditPreview = SubscriptionAuditLog::query()
                ->where('tenant_id', $tenant->id)
                ->with('actor:id,name')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($r): array => [
                    'id' => $r->id,
                    'action' => $r->action,
                    'actor_name' => $r->actor?->name,
                    'created_at' => $r->created_at?->toIso8601String(),
                ])
                ->all();
        }

        return Inertia::render('Modules/Index', [
            'stats' => [
                'clients' => Entity::query()->where('is_client', true)->count(),
                'suppliers' => Entity::query()->where('is_supplier', true)->count(),
                'users' => $usersCount,
                'organizations' => $orgsCount,
            ],
            'audit_preview' => $auditPreview,
        ]);
    }

    public function show(Request $request, string $slug): Response|RedirectResponse
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === $slug);
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);

        $tenant = app(TenantContext::class)->tenant();
        if ($tenant && ! app(SubscriptionEntitlementService::class)->canAccessModule($tenant, $module['key'])) {
            return redirect()
                ->route('subscription.dashboard')
                ->with('error', 'Este módulo não está disponível no plano atual da organização.');
        }

        if ($slug === 'users') {
            return app(UserAdminController::class)->index($request);
        }

        if ($slug === 'permissions') {
            return app(PermissionGroupsController::class)->index($request);
        }

        if ($slug === 'settings-countries') {
            return app(CountrySettingsController::class)->index($request);
        }

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

        $selectedEntityId = $this->usesEntityPicker($slug)
            ? $request->integer('entity_id')
            : null;
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        $sort = (string) $request->query('sort', 'latest');

        $query = ModuleRecord::query()
            ->where('module_key', $module['key'])
            ->with('entity:id,name')
            ->when(
                $this->usesEntityPicker($slug) && $selectedEntityId,
                fn ($q) => $q->where('entity_id', $selectedEntityId)
            )
            ->when($search !== '', function ($q) use ($search, $slug): void {
                $q->where(function ($inner) use ($search, $slug): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                    if ($slug === 'proposals') {
                        $inner->orWhere('proposal_number', 'like', "%{$search}%");
                    }
                    if ($this->isOrdersModule($slug)) {
                        $inner->orWhere('order_number', 'like', "%{$search}%");
                    }
                    if ($this->isBankAccountsModule($slug)) {
                        $inner->orWhere('bank_name', 'like', "%{$search}%");
                    }
                });
            })
            ->when($status === 'active', fn ($q) => $q->where('active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('active', false));

        if ($this->isProposalsModule($slug)) {
            $query->when($sort === 'oldest', fn ($q) => $q->orderBy('proposal_date')->orderBy('id'))
                ->when($sort !== 'oldest', fn ($q) => $q->orderByDesc('proposal_date')->orderByDesc('id'));
        } elseif ($this->isOrdersModule($slug)) {
            $query->when($sort === 'oldest', fn ($q) => $q->orderBy('order_date')->orderBy('id'))
                ->when($sort !== 'oldest', fn ($q) => $q->orderByDesc('order_date')->orderByDesc('id'));
        } elseif ($this->isCustomerLedgerModule($slug)) {
            $query->when($sort === 'oldest', fn ($q) => $q->orderBy('ledger_entry_date')->orderBy('id'))
                ->when($sort !== 'oldest', fn ($q) => $q->orderByDesc('ledger_entry_date')->orderByDesc('id'));
        } elseif ($this->isBankAccountsModule($slug)) {
            $query->when($sort === 'bank_desc', fn ($q) => $q->orderByDesc('bank_name')->orderByDesc('id'))
                ->when($sort === 'bank_asc', fn ($q) => $q->orderBy('bank_name')->orderBy('id'))
                ->when(! in_array($sort, ['bank_asc', 'bank_desc'], true), fn ($q) => $q->orderByDesc('id'));
        } else {
            $query->when($sort === 'title_asc', fn ($q) => $q->orderBy('title'))
                ->when($sort === 'title_desc', fn ($q) => $q->orderByDesc('title'))
                ->when($sort === 'latest', fn ($q) => $q->orderByDesc('id'))
                ->when($sort === 'oldest', fn ($q) => $q->orderBy('id'));
        }

        $records = $query->paginate(15)->withQueryString();

        if ($this->isBankAccountsModule($slug)) {
            $records->getCollection()->transform(
                fn (ModuleRecord $record) => $this->transformBankAccountRecordForFrontend($record)
            );
        }

        return Inertia::render('Modules/Records/Index', [
            'module' => $module,
            'records' => $records,
            'relatedEntities' => $this->relatedEntitiesForModule($slug),
            'requireEntitySelection' => $this->usesEntityPicker($slug),
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
            'requireEntitySelection' => $this->usesEntityPicker($slug),
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === $slug);
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can("module.{$module['key']}.create"), 403);

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
            'entity_id' => $this->entityIdValidationRules($slug),
        ];

        if ($this->isCustomerLedgerModule($slug)) {
            $rules['title'] = ['nullable', 'string', 'max:255'];
            $rules['description'] = ['required', 'string', 'max:5000'];
            $rules = array_merge($rules, $this->ledgerFieldRules());
        }
        if ($this->isBankAccountsModule($slug)) {
            $rules['title'] = ['nullable', 'string', 'max:255'];
            $rules['description'] = ['nullable', 'string', 'max:5000'];
            $rules = array_merge($rules, $this->bankAccountFieldRules());
        }
        if ($this->isProposalsModule($slug)) {
            $rules = array_merge($rules, $this->proposalFieldRules());
        }
        if ($this->isOrdersModule($slug)) {
            $rules = array_merge($rules, $this->orderFieldRules());
        }

        $data = $request->validate($rules);

        if ($this->isBankAccountsModule($slug)) {
            $data = $this->normalizeAndValidateBankAccountInput($data);
        }

        if ($this->isCustomerLedgerModule($slug)) {
            $this->assertLedgerDebitCreditXor($data);
        }

        if ($this->usesEntityPicker($slug)) {
            $isValidEntity = Entity::query()
                ->whereKey($data['entity_id'])
                ->when($slug === 'customer-orders' || $slug === 'proposals' || $slug === 'financial-customer-ledger', fn ($q) => $q->where('is_client', true))
                ->when($slug === 'supplier-orders', fn ($q) => $q->where('is_supplier', true))
                ->exists();
            abort_unless($isValidEntity, 422, 'Escolha um cliente ou fornecedor válido.');
        }

        $title = $data['title'];
        if ($this->isCustomerLedgerModule($slug)) {
            $title = trim((string) $title) !== '' ? trim((string) $title) : Str::limit(trim((string) $data['description']), 80);
        }
        if ($this->isBankAccountsModule($slug)) {
            $title = trim((string) $title) !== '' ? trim((string) $title) : $data['bank_name'];
        }

        $payload = [
            'module_key' => $module['key'],
            'entity_id' => $data['entity_id'] ?? null,
            'title' => $title,
            'description' => $data['description'] ?: null,
            'active' => $data['active'],
        ];

        if ($this->isBankAccountsModule($slug)) {
            $payload['bank_name'] = $data['bank_name'];
            $payload['bank_iban_encrypted'] = $data['iban'];
            $payload['bank_swift_encrypted'] = $data['swift'];
        }

        if ($this->isCustomerLedgerModule($slug)) {
            $debit = (float) ($data['ledger_debit'] ?? 0);
            $credit = (float) ($data['ledger_credit'] ?? 0);
            $payload['ledger_entry_date'] = $data['ledger_entry_date'];
            $payload['ledger_debit'] = $debit > 0 ? round($debit, 2) : null;
            $payload['ledger_credit'] = $credit > 0 ? round($credit, 2) : null;
        }

        if ($this->isProposalsModule($slug)) {
            $payload['proposal_number'] = $data['proposal_number'];
            $payload['proposal_date'] = $data['proposal_date'];
            $payload['valid_until'] = $data['valid_until'] ?? null;
            $payload['proposal_amount'] = $data['proposal_amount'] ?? null;
            $payload['proposal_status'] = $data['proposal_status'];
        }
        if ($this->isOrdersModule($slug)) {
            $payload['order_number'] = $data['order_number'];
            $payload['order_date'] = $data['order_date'];
            $payload['order_valid_until'] = $data['order_valid_until'] ?? null;
            $payload['order_amount'] = $data['order_amount'] ?? null;
            $payload['order_status'] = $data['order_status'];
        }

        ModuleRecord::query()->create($payload);

        return redirect()->route('modules.show', $slug)->with('success', 'Registo criado com sucesso.');
    }

    public function edit(Request $request, string $slug, ModuleRecord $record): Response
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === $slug);
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can("module.{$module['key']}.update"), 403);
        abort_unless($record->module_key === $module['key'], 404);

        if ($this->isBankAccountsModule($slug)) {
            $record = $this->transformBankAccountRecordForFrontend($record);
        }

        return Inertia::render('Modules/Records/Form', [
            'module' => $module,
            'record' => $record,
            'relatedEntities' => $this->relatedEntitiesForModule($slug),
            'requireEntitySelection' => $this->usesEntityPicker($slug),
        ]);
    }

    public function update(Request $request, string $slug, ModuleRecord $record): RedirectResponse
    {
        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === $slug);
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->can("module.{$module['key']}.update"), 403);
        abort_unless($record->module_key === $module['key'], 404);

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['required', 'boolean'],
            'entity_id' => $this->entityIdValidationRules($slug),
        ];

        if ($this->isCustomerLedgerModule($slug)) {
            $rules['title'] = ['nullable', 'string', 'max:255'];
            $rules['description'] = ['required', 'string', 'max:5000'];
            $rules = array_merge($rules, $this->ledgerFieldRules());
        }
        if ($this->isBankAccountsModule($slug)) {
            $rules['title'] = ['nullable', 'string', 'max:255'];
            $rules['description'] = ['nullable', 'string', 'max:5000'];
            $rules = array_merge($rules, $this->bankAccountFieldRules());
        }
        if ($this->isProposalsModule($slug)) {
            $rules = array_merge($rules, $this->proposalFieldRules());
        }
        if ($this->isOrdersModule($slug)) {
            $rules = array_merge($rules, $this->orderFieldRules());
        }

        $data = $request->validate($rules);

        if ($this->isBankAccountsModule($slug)) {
            $data = $this->normalizeAndValidateBankAccountInput($data);
        }

        if ($this->isCustomerLedgerModule($slug)) {
            $this->assertLedgerDebitCreditXor($data);
        }

        if ($this->usesEntityPicker($slug)) {
            $isValidEntity = Entity::query()
                ->whereKey($data['entity_id'])
                ->when($slug === 'customer-orders' || $slug === 'proposals' || $slug === 'financial-customer-ledger', fn ($q) => $q->where('is_client', true))
                ->when($slug === 'supplier-orders', fn ($q) => $q->where('is_supplier', true))
                ->exists();
            abort_unless($isValidEntity, 422, 'Escolha um cliente ou fornecedor válido.');
        }

        $title = $data['title'];
        if ($this->isCustomerLedgerModule($slug)) {
            $title = trim((string) $title) !== '' ? trim((string) $title) : Str::limit(trim((string) $data['description']), 80);
        }
        if ($this->isBankAccountsModule($slug)) {
            $title = trim((string) $title) !== '' ? trim((string) $title) : $data['bank_name'];
        }

        $payload = [
            'entity_id' => $data['entity_id'] ?? null,
            'title' => $title,
            'description' => $data['description'] ?: null,
            'active' => $data['active'],
        ];

        if ($this->isBankAccountsModule($slug)) {
            $payload['bank_name'] = $data['bank_name'];
            $payload['bank_iban_encrypted'] = $data['iban'];
            $payload['bank_swift_encrypted'] = $data['swift'];
        }

        if ($this->isCustomerLedgerModule($slug)) {
            $debit = (float) ($data['ledger_debit'] ?? 0);
            $credit = (float) ($data['ledger_credit'] ?? 0);
            $payload['ledger_entry_date'] = $data['ledger_entry_date'];
            $payload['ledger_debit'] = $debit > 0 ? round($debit, 2) : null;
            $payload['ledger_credit'] = $credit > 0 ? round($credit, 2) : null;
        }

        if ($this->isProposalsModule($slug)) {
            $payload['proposal_number'] = $data['proposal_number'];
            $payload['proposal_date'] = $data['proposal_date'];
            $payload['valid_until'] = $data['valid_until'] ?? null;
            $payload['proposal_amount'] = $data['proposal_amount'] ?? null;
            $payload['proposal_status'] = $data['proposal_status'];
        }
        if ($this->isOrdersModule($slug)) {
            $payload['order_number'] = $data['order_number'];
            $payload['order_date'] = $data['order_date'];
            $payload['order_valid_until'] = $data['order_valid_until'] ?? null;
            $payload['order_amount'] = $data['order_amount'] ?? null;
            $payload['order_status'] = $data['order_status'];
        }

        $record->update($payload);

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

    public function recordPdf(string $slug, ModuleRecord $record)
    {
        abort_unless(in_array($slug, ['proposals', 'customer-orders', 'supplier-orders'], true), 404);

        $module = Arr::first(Modules::ITEMS, fn (array $item) => $item['slug'] === $slug);
        abort_if(! $module, 404);

        Gate::authorize('access-module', $module['key']);
        abort_unless($record->module_key === $module['key'], 404);

        $record->load(['entity:id,name,address_encrypted,postal_code,city']);

        if ($slug === 'proposals') {
            $safeName = preg_replace('/[^\p{L}\p{N}_\-]+/u', '_', $record->proposal_number ?? (string) $record->id) ?: 'proposta';

            return Pdf::loadView('pdf.proposal', ['record' => $record])
                ->download('Proposta_'.$safeName.'.pdf');
        }

        $safeName = preg_replace('/[^\p{L}\p{N}_\-]+/u', '_', $record->order_number ?? (string) $record->id) ?: 'encomenda';

        return Pdf::loadView('pdf.order', [
            'record' => $record,
            'isSupplierOrder' => $slug === 'supplier-orders',
        ])->download('Encomenda_'.$safeName.'.pdf');
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
