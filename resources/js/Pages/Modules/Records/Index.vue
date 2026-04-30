<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { reactive, watch } from 'vue';

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

const page = usePage();
const filterForm = reactive({
    q: props.filters?.q ?? '',
    status: props.filters?.status ?? 'all',
    sort: props.filters?.sort ?? 'latest',
});

watch(
    () => page.props.flash?.success,
    (msg) => {
        if (msg) {
            toast.success(msg);
        }
    },
    { immediate: true },
);

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
                <div class="mb-4 grid gap-3 rounded-xl border border-border bg-card p-4 shadow-sm md:grid-cols-4">
                    <input
                        v-model="filterForm.q"
                        type="text"
                        placeholder="Pesquisar título/descrição..."
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        @keyup.enter="applyFilters"
                    />
                    <select v-model="filterForm.status" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="all">Todos os estados</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                    <select v-model="filterForm.sort" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="latest">Mais recentes</option>
                        <option value="oldest">Mais antigos</option>
                        <option value="title_asc">Titulo A-Z</option>
                        <option value="title_desc">Titulo Z-A</option>
                    </select>
                    <Button type="button" variant="outline" @click="applyFilters">Aplicar filtros</Button>
                </div>

                <div v-if="requireEntitySelection" class="mb-4 rounded-xl border border-border bg-card p-4 shadow-sm">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Filtro por {{ module.slug === 'customer-orders' ? 'cliente' : 'fornecedor' }}
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
                            <TableRow>
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
                            <TableRow v-if="!records.data?.length">
                                <TableCell :colspan="requireEntitySelection ? 5 : 4" class="text-center text-muted-foreground">
                                    <div class="space-y-3 py-6">
                                        <p>Sem registos neste módulo.</p>
                                        <Button v-if="canCreate" as-child size="sm" variant="outline">
                                            <Link :href="route('modules.records.create', module.slug)">Criar primeiro registo</Link>
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="row in records.data" :key="row.id">
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
