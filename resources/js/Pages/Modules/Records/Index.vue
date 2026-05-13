<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    module: { type: Object, required: true },
    records: { type: Object, required: true },
    relatedEntities: { type: Array, default: () => [] },
    requireEntitySelection: { type: Boolean, default: false },
    selectedEntityId: { type: Number, default: null },
    filters: { type: Object, default: () => ({ q: '', status: 'all', sort: 'latest' }) },
    canCreate: { type: Boolean, default: false },
    canUpdate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const usesCommercialGrid = computed(() =>
    ['proposals', 'customer-orders', 'supplier-orders'].includes(props.module.slug),
);

const usesCustomerLedgerGrid = computed(() => props.module.slug === 'financial-customer-ledger');

const usesBankAccountsGrid = computed(() => props.module.slug === 'financial-bank-accounts');

const isProposalsModule = computed(() => props.module.slug === 'proposals');

const filterForm = reactive({
    q: props.filters?.q ?? '',
    status: props.filters?.status ?? 'all',
    sort: props.filters?.sort ?? 'latest',
});

const proposalStatusLabels = {
    draft: 'Rascunho',
    sent: 'Enviada',
    pending: 'Pendente',
    accepted: 'Aceite',
    rejected: 'Recusada',
    expired: 'Expirada',
};

const orderStatusLabels = {
    draft: 'Rascunho',
    confirmed: 'Confirmada',
    processing: 'Em preparação',
    shipped: 'Expedida',
    delivered: 'Entregue',
    cancelled: 'Cancelada',
};

const sortOptions = computed(() => {
    if (usesCommercialGrid.value || usesCustomerLedgerGrid.value) {
        return [
            { value: 'latest', label: 'Data (mais recente)' },
            { value: 'oldest', label: 'Data (mais antiga)' },
        ];
    }
    if (usesBankAccountsGrid.value) {
        return [
            { value: 'latest', label: 'Mais recentes' },
            { value: 'bank_asc', label: 'Banco (A-Z)' },
            { value: 'bank_desc', label: 'Banco (Z-A)' },
        ];
    }
    return [
        { value: 'latest', label: 'Mais recentes' },
        { value: 'oldest', label: 'Mais antigos' },
        { value: 'title_asc', label: 'Titulo A-Z' },
        { value: 'title_desc', label: 'Titulo Z-A' },
    ];
});

function destroyRecord(id) {
    if (!confirm('Remover este registo?')) {
        return;
    }

    router.delete(route('modules.records.destroy', { slug: props.module.slug, record: id }));
}

function applyEntityFilter(event) {
    const value = event.target.value;
    router.get(
        route('modules.show', props.module.slug),
        { entity_id: value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function applyFilters() {
    router.get(
        route('modules.show', props.module.slug),
        {
            q: filterForm.q || undefined,
            status: filterForm.status === 'all' ? undefined : filterForm.status,
            sort: filterForm.sort === 'latest' ? undefined : filterForm.sort,
            entity_id: props.selectedEntityId ?? undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function formatPtDate(val) {
    if (!val) {
        return '—';
    }
    const s = String(val).slice(0, 10);
    const [y, m, d] = s.split('-');
    if (!y || !m || !d) {
        return '—';
    }
    return `${d}/${m}/${y}`;
}

function formatEuro(amount) {
    if (amount === null || amount === undefined || amount === '') {
        return '—';
    }
    return new Intl.NumberFormat('pt-PT', { style: 'currency', currency: 'EUR' }).format(Number(amount));
}

function rowDocDate(row) {
    return isProposalsModule.value ? row.proposal_date : row.order_date;
}

function rowDocNumber(row) {
    return isProposalsModule.value ? row.proposal_number : row.order_number;
}

function rowValidUntil(row) {
    return isProposalsModule.value ? row.valid_until : row.order_valid_until;
}

function rowAmount(row) {
    return isProposalsModule.value ? row.proposal_amount : row.order_amount;
}

function rowWorkflowStatus(row) {
    return isProposalsModule.value ? row.proposal_status : row.order_status;
}

function workflowStatusLabel(status) {
    if (!status) {
        return '—';
    }
    if (isProposalsModule.value) {
        return proposalStatusLabels[status] ?? status;
    }
    return orderStatusLabels[status] ?? status;
}

function workflowStatusVariant(status) {
    if (isProposalsModule.value) {
        switch (status) {
            case 'accepted':
                return 'default';
            case 'rejected':
            case 'expired':
                return 'destructive';
            case 'sent':
                return 'secondary';
            default:
                return 'outline';
        }
    }
    switch (status) {
        case 'delivered':
        case 'confirmed':
            return 'default';
        case 'cancelled':
            return 'destructive';
        case 'shipped':
        case 'processing':
            return 'secondary';
        default:
            return 'outline';
    }
}

const entityFilterLabel = computed(() => {
    if (props.module.slug === 'supplier-orders') {
        return 'fornecedor';
    }
    return 'cliente';
});

const numberColumnLabel = computed(() =>
    props.module.slug === 'proposals' ? 'Nº proposta' : 'Nº encomenda',
);

const entityColumnLabel = computed(() =>
    props.module.slug === 'supplier-orders' ? 'Fornecedor' : 'Cliente',
);

const searchPlaceholder = computed(() => {
    if (usesCommercialGrid.value) {
        return 'Pesquisar título, descrição ou número…';
    }
    if (usesCustomerLedgerGrid.value) {
        return 'Pesquisar descrição ou título…';
    }
    if (usesBankAccountsGrid.value) {
        return 'Pesquisar banco, título ou descrição…';
    }
    return 'Pesquisar título/descrição…';
});

const tableColspan = computed(() => {
    if (usesCommercialGrid.value) {
        return 8;
    }
    if (usesCustomerLedgerGrid.value) {
        return 6;
    }
    if (usesBankAccountsGrid.value) {
        return 5;
    }
    return props.requireEntitySelection ? 5 : 4;
});

function formatIbanDisplay(val) {
    if (!val) {
        return '—';
    }
    const clean = String(val).replace(/\s/g, '').toUpperCase();
    return clean.replace(/(.{4})/g, '$1 ').trim();
}
</script>

<template>
    <Head :title="module.label" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-foreground">
                    {{ module.label }}
                </h2>
                <Button v-if="canCreate" as-child size="sm">
                    <Link :href="route('modules.records.create', module.slug)">Novo registo</Link>
                </Button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="mb-4 grid gap-3 rounded-xl border border-border bg-card p-4 shadow-sm md:grid-cols-4"
                    :class="usesCustomerLedgerGrid ? 'md:grid-cols-3' : ''"
                >
                    <input
                        v-model="filterForm.q"
                        type="text"
                        :placeholder="searchPlaceholder"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        @keyup.enter="applyFilters"
                    />
                    <select
                        v-if="!usesCustomerLedgerGrid"
                        v-model="filterForm.status"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="all">Todos os estados</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                    <select v-model="filterForm.sort" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                        <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <Button type="button" variant="outline" @click="applyFilters">Aplicar filtros</Button>
                </div>

                <div v-if="requireEntitySelection" class="mb-4 rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Filtro por {{ entityFilterLabel }}
                    </p>
                    <select
                        :value="selectedEntityId ?? ''"
                        class="flex h-10 w-full max-w-md rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        @change="applyEntityFilter"
                    >
                        <option value="">Todos</option>
                        <option v-for="entity in relatedEntities" :key="entity.id" :value="entity.id">
                            {{ entity.name }}
                        </option>
                    </select>
                </div>

                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow v-if="usesCommercialGrid">
                                <TableHead>Data</TableHead>
                                <TableHead>{{ numberColumnLabel }}</TableHead>
                                <TableHead>Validade</TableHead>
                                <TableHead>{{ entityColumnLabel }}</TableHead>
                                <TableHead class="text-end">Valor</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Assunto</TableHead>
                                <TableHead class="text-end">Ações</TableHead>
                            </TableRow>
                            <TableRow v-else-if="usesCustomerLedgerGrid">
                                <TableHead>Data</TableHead>
                                <TableHead>Cliente</TableHead>
                                <TableHead>Descrição</TableHead>
                                <TableHead class="text-end">Débito</TableHead>
                                <TableHead class="text-end">Crédito</TableHead>
                                <TableHead class="text-end">Ações</TableHead>
                            </TableRow>
                            <TableRow v-else-if="usesBankAccountsGrid">
                                <TableHead>Banco</TableHead>
                                <TableHead>IBAN</TableHead>
                                <TableHead>SWIFT</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-end">Ações</TableHead>
                            </TableRow>
                            <TableRow v-else>
                                <TableHead v-if="requireEntitySelection">
                                    {{ module.slug === 'customer-orders' ? 'Cliente' : 'Fornecedor' }}
                                </TableHead>
                                <TableHead>Título</TableHead>
                                <TableHead>Descrição</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-end">Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="!records.data?.length">
                                <TableRow>
                                    <TableCell :colspan="tableColspan" class="text-center text-muted-foreground">
                                        <div class="space-y-3 py-6">
                                            <p>Sem registos neste módulo.</p>
                                            <Button v-if="canCreate" as-child size="sm" variant="outline">
                                                <Link :href="route('modules.records.create', module.slug)">Criar primeiro registo</Link>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else>
                                <template v-for="row in records.data" :key="row.id">
                                    <TableRow v-if="usesCommercialGrid">
                                        <TableCell class="whitespace-nowrap text-sm">{{ formatPtDate(rowDocDate(row)) }}</TableCell>
                                        <TableCell class="font-medium">{{ rowDocNumber(row) ?? '—' }}</TableCell>
                                        <TableCell class="whitespace-nowrap text-sm">{{ formatPtDate(rowValidUntil(row)) }}</TableCell>
                                        <TableCell class="font-medium">{{ row.entity?.name ?? '—' }}</TableCell>
                                        <TableCell class="text-end font-medium tabular-nums">{{ formatEuro(rowAmount(row)) }}</TableCell>
                                        <TableCell>
                                            <Badge :variant="workflowStatusVariant(rowWorkflowStatus(row))">
                                                {{ workflowStatusLabel(rowWorkflowStatus(row)) }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="max-w-xs truncate text-muted-foreground">{{ row.title }}</TableCell>
                                        <TableCell class="text-end">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <Button variant="outline" size="sm" as-child>
                                                    <a
                                                        :href="route('modules.records.pdf', { slug: module.slug, record: row.id })"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        PDF
                                                    </a>
                                                </Button>
                                                <Button v-if="canUpdate" variant="outline" size="sm" as-child>
                                                    <Link
                                                        :href="
                                                            route('modules.records.edit', {
                                                                slug: module.slug,
                                                                record: row.id,
                                                            })
                                                        "
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                                <Button
                                                    v-if="canDelete"
                                                    variant="destructive"
                                                    size="sm"
                                                    type="button"
                                                    @click="destroyRecord(row.id)"
                                                >
                                                    Remover
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-else-if="usesCustomerLedgerGrid">
                                        <TableCell class="whitespace-nowrap text-sm">{{ formatPtDate(row.ledger_entry_date) }}</TableCell>
                                        <TableCell class="font-medium">{{ row.entity?.name ?? '—' }}</TableCell>
                                        <TableCell class="max-w-md text-muted-foreground">{{ row.description ?? '—' }}</TableCell>
                                        <TableCell class="text-end font-medium tabular-nums">{{ formatEuro(row.ledger_debit) }}</TableCell>
                                        <TableCell class="text-end font-medium tabular-nums">{{ formatEuro(row.ledger_credit) }}</TableCell>
                                        <TableCell class="text-end">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <Button v-if="canUpdate" variant="outline" size="sm" as-child>
                                                    <Link
                                                        :href="
                                                            route('modules.records.edit', {
                                                                slug: module.slug,
                                                                record: row.id,
                                                            })
                                                        "
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                                <Button
                                                    v-if="canDelete"
                                                    variant="destructive"
                                                    size="sm"
                                                    type="button"
                                                    @click="destroyRecord(row.id)"
                                                >
                                                    Remover
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-else-if="usesBankAccountsGrid">
                                        <TableCell class="font-medium">{{ row.bank_name ?? '—' }}</TableCell>
                                        <TableCell class="max-w-xs font-mono text-sm">{{ formatIbanDisplay(row.iban) }}</TableCell>
                                        <TableCell class="font-mono text-sm">{{ row.swift ?? '—' }}</TableCell>
                                        <TableCell>
                                            <Badge :variant="row.active ? 'default' : 'secondary'">
                                                {{ row.active ? 'Ativa' : 'Inativa' }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="text-end">
                                            <div class="flex flex-wrap justify-end gap-2">
                                                <Button v-if="canUpdate" variant="outline" size="sm" as-child>
                                                    <Link
                                                        :href="
                                                            route('modules.records.edit', {
                                                                slug: module.slug,
                                                                record: row.id,
                                                            })
                                                        "
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                                <Button
                                                    v-if="canDelete"
                                                    variant="destructive"
                                                    size="sm"
                                                    type="button"
                                                    @click="destroyRecord(row.id)"
                                                >
                                                    Remover
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-else>
                                        <TableCell v-if="requireEntitySelection" class="font-medium">
                                            {{ row.entity?.name ?? '—' }}
                                        </TableCell>
                                        <TableCell class="font-medium">{{ row.title }}</TableCell>
                                        <TableCell class="max-w-2xl truncate">{{ row.description ?? '—' }}</TableCell>
                                        <TableCell>
                                            <Badge :variant="row.active ? 'default' : 'secondary'">
                                                {{ row.active ? 'Ativo' : 'Inativo' }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="text-end">
                                            <div class="flex justify-end gap-2">
                                                <Button v-if="canUpdate" variant="outline" size="sm" as-child>
                                                    <Link
                                                        :href="
                                                            route('modules.records.edit', {
                                                                slug: module.slug,
                                                                record: row.id,
                                                            })
                                                        "
                                                    >
                                                        Editar
                                                    </Link>
                                                </Button>
                                                <Button
                                                    v-if="canDelete"
                                                    variant="destructive"
                                                    size="sm"
                                                    type="button"
                                                    @click="destroyRecord(row.id)"
                                                >
                                                    Remover
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                </template>
                            </template>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="records.last_page > 1" class="mt-4 flex items-center justify-center gap-3">
                    <Button v-if="records.prev_page_url" variant="outline" size="sm" as-child>
                        <Link :href="records.prev_page_url" preserve-scroll>Anterior</Link>
                    </Button>
                    <span class="text-sm text-muted-foreground">
                        Página {{ records.current_page }} de {{ records.last_page }}
                    </span>
                    <Button v-if="records.next_page_url" variant="outline" size="sm" as-child>
                        <Link :href="records.next_page_url" preserve-scroll>Seguinte</Link>
                    </Button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
