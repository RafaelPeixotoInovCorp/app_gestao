<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    module: { type: Object, required: true },
    slug: { type: String, required: true },
    entities: { type: Object, required: true },
    filters: { type: Object, default: () => ({ q: '', status: 'all', sort: 'latest' }) },
    canCreate: { type: Boolean, default: false },
    canUpdate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const filterForm = reactive({
    q: props.filters?.q ?? '',
    status: props.filters?.status ?? 'all',
    sort: props.filters?.sort ?? 'latest',
});

function destroyEntity(id) {
    if (!confirm('Remover esta entidade?')) {
        return;
    }
    const url = `${route('entities.destroy', id)}?from=${props.slug}`;
    router.delete(url);
}

function applyFilters() {
    router.get(
        route('modules.show', props.slug),
        {
            q: filterForm.q || undefined,
            status: filterForm.status === 'all' ? undefined : filterForm.status,
            sort: filterForm.sort === 'latest' ? undefined : filterForm.sort,
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
                <Button v-if="canCreate" as-child variant="default" size="sm">
                    <Link
                        :href="
                            route('entities.create', {
                                kind: slug === 'clients' ? 'client' : 'supplier',
                            })
                        "
                    >
                        Nova entidade
                    </Link>
                </Button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 grid gap-3 rounded-xl border border-border bg-card p-4 shadow-sm md:grid-cols-4">
                    <input
                        v-model="filterForm.q"
                        type="text"
                        placeholder="Pesquisar por nome, e-mail ou sítio na Web…"
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
                        <option value="name_asc">Nome A-Z</option>
                        <option value="name_desc">Nome Z-A</option>
                    </select>
                    <Button type="button" variant="outline" @click="applyFilters">Aplicar filtros</Button>
                </div>

                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>NIF</TableHead>
                                <TableHead>Nome</TableHead>
                                <TableHead>Telefone</TableHead>
                                <TableHead>Telemóvel</TableHead>
                                <TableHead>Sítio na Web</TableHead>
                                <TableHead>E-mail</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead class="text-end">Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="!entities.data?.length">
                                <TableCell colspan="8" class="text-center text-muted-foreground">
                                    <div class="space-y-3 py-6">
                                        <p>Sem registos.</p>
                                        <Button v-if="canCreate" as-child size="sm" variant="outline">
                                            <Link
                                                :href="
                                                    route('entities.create', {
                                                        kind: slug === 'clients' ? 'client' : 'supplier',
                                                    })
                                                "
                                            >
                                                Criar primeira entidade
                                            </Link>
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="row in entities.data" :key="row.id">
                                <TableCell class="font-medium">{{ row.nif ?? '—' }}</TableCell>
                                <TableCell>{{ row.name }}</TableCell>
                                <TableCell>{{ row.phone ?? '—' }}</TableCell>
                                <TableCell>{{ row.mobile ?? '—' }}</TableCell>
                                <TableCell>
                                    <a
                                        v-if="row.website"
                                        :href="
                                            row.website.startsWith('http')
                                                ? row.website
                                                : `https://${row.website}`
                                        "
                                        class="text-primary underline-offset-4 hover:underline"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        {{ row.website }}
                                    </a>
                                    <span v-else>—</span>
                                </TableCell>
                                <TableCell>{{ row.email ?? '—' }}</TableCell>
                                <TableCell>
                                    <Badge :variant="row.active ? 'default' : 'secondary'">
                                        {{ row.active ? 'Ativo' : 'Inactivo' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-end">
                                    <div class="flex justify-end gap-2">
                                        <Button v-if="canUpdate" variant="outline" size="sm" as-child>
                                            <Link :href="route('entities.edit', row.id)">Editar</Link>
                                        </Button>
                                        <Button
                                            v-if="canDelete"
                                            variant="destructive"
                                            size="sm"
                                            type="button"
                                            @click="destroyEntity(row.id)"
                                        >
                                            Remover
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div
                    v-if="entities.last_page > 1"
                    class="mt-4 flex flex-wrap items-center justify-center gap-3"
                >
                    <Button v-if="entities.prev_page_url" variant="outline" size="sm" as-child>
                        <Link :href="entities.prev_page_url" preserve-scroll>Anterior</Link>
                    </Button>
                    <span class="text-sm text-muted-foreground">
                        Página {{ entities.current_page }} de {{ entities.last_page }}
                    </span>
                    <Button v-if="entities.next_page_url" variant="outline" size="sm" as-child>
                        <Link :href="entities.next_page_url" preserve-scroll>Seguinte</Link>
                    </Button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
