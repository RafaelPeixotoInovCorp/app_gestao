<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    module: { type: Object, required: true },
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({ q: '', status: 'all', sort: 'latest' }) },
    canCreate: { type: Boolean, default: false },
    canUpdate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
    authUserId: { type: Number, required: true },
    subscriptionUsage: { type: Object, default: null },
});

const filterForm = reactive({
    q: props.filters?.q ?? '',
    status: props.filters?.status ?? 'all',
    sort: props.filters?.sort ?? 'latest',
});

function destroyUser(id) {
    if (!confirm('Remover este utilizador?')) {
        return;
    }
    router.delete(route('modules.users.destroy', id));
}

function applyFilters() {
    router.get(
        route('modules.show', 'users'),
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
                <Button v-if="canCreate" as-child size="sm">
                    <Link :href="route('modules.users.create')">Novo utilizador</Link>
                </Button>
                <p
                    v-if="subscriptionUsage && !subscriptionUsage.unlimited_users"
                    class="text-sm text-muted-foreground"
                >
                    Limite do plano: {{ subscriptionUsage.users_count }} / {{ subscriptionUsage.max_users }} utilizadores
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 grid gap-3 rounded-xl border border-border bg-card p-4 shadow-sm md:grid-cols-4">
                    <input
                        v-model="filterForm.q"
                        type="text"
                        placeholder="Pesquisar nome ou e-mail…"
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        @keyup.enter="applyFilters"
                    />
                    <select v-model="filterForm.status" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                        <option value="all">Todos os estados</option>
                        <option value="active">Contas ativas</option>
                        <option value="inactive">Contas inativas</option>
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
                                <TableHead>Nome</TableHead>
                                <TableHead>E-mail</TableHead>
                                <TableHead>Telemóvel</TableHead>
                                <TableHead>Grupo de permissões</TableHead>
                                <TableHead>Estado da conta</TableHead>
                                <TableHead class="text-end">Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="!users.data?.length">
                                <TableRow>
                                    <TableCell colspan="6" class="text-center text-muted-foreground">
                                        <div class="space-y-3 py-6">
                                            <p>Sem utilizadores.</p>
                                            <Button v-if="canCreate" as-child size="sm" variant="outline">
                                                <Link :href="route('modules.users.create')">Criar utilizador</Link>
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else>
                                <TableRow v-for="row in users.data" :key="row.id">
                                    <TableCell class="font-medium">{{ row.name }}</TableCell>
                                    <TableCell>{{ row.email }}</TableCell>
                                    <TableCell>{{ row.phone ?? '—' }}</TableCell>
                                    <TableCell>{{ row.role_display ?? '—' }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="row.is_active ? 'default' : 'secondary'">
                                            {{ row.is_active ? 'Ativa' : 'Inativa' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-end">
                                        <div class="flex justify-end gap-2">
                                            <Button v-if="canUpdate" variant="outline" size="sm" as-child>
                                                <Link :href="route('modules.users.edit', row.id)">Editar</Link>
                                            </Button>
                                            <Button
                                                v-if="canDelete && row.id !== authUserId"
                                                variant="destructive"
                                                size="sm"
                                                type="button"
                                                @click="destroyUser(row.id)"
                                            >
                                                Remover
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </template>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="users.last_page > 1" class="mt-4 flex items-center justify-center gap-3">
                    <Button v-if="users.prev_page_url" variant="outline" size="sm" as-child>
                        <Link :href="users.prev_page_url" preserve-scroll>Anterior</Link>
                    </Button>
                    <span class="text-sm text-muted-foreground">
                        Página {{ users.current_page }} de {{ users.last_page }}
                    </span>
                    <Button v-if="users.next_page_url" variant="outline" size="sm" as-child>
                        <Link :href="users.next_page_url" preserve-scroll>Seguinte</Link>
                    </Button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
