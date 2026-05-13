<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    module: { type: Object, required: true },
    countries: { type: Object, required: true },
    filters: { type: Object, default: () => ({ q: '' }) },
    canCreate: { type: Boolean, default: false },
    canUpdate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const filterForm = reactive({
    q: props.filters?.q ?? '',
});

function destroyCountry(id) {
    if (!confirm('Remover este país?')) {
        return;
    }
    router.delete(route('modules.countries.destroy', id));
}

function applyFilters() {
    router.get(
        route('modules.show', 'settings-countries'),
        { q: filterForm.q || undefined },
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
                    <Link :href="route('modules.countries.create')">Novo país</Link>
                </Button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-col gap-3 rounded-xl border border-border bg-card p-4 shadow-sm sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-muted-foreground">Pesquisar</label>
                        <input
                            v-model="filterForm.q"
                            type="text"
                            placeholder="Nome, sigla ou ISO3…"
                            class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                    <Button type="button" variant="outline" @click="applyFilters">Aplicar</Button>
                </div>

                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nome</TableHead>
                                <TableHead>Sigla</TableHead>
                                <TableHead>ISO3</TableHead>
                                <TableHead class="text-end">Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="!countries.data?.length">
                                <TableRow>
                                    <TableCell colspan="4" class="py-8 text-center text-muted-foreground">
                                        <p class="mb-3">Sem países.</p>
                                        <Button v-if="canCreate" as-child size="sm" variant="outline">
                                            <Link :href="route('modules.countries.create')">Adicionar país</Link>
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </template>
                            <template v-else>
                                <TableRow v-for="row in countries.data" :key="row.id">
                                    <TableCell class="font-medium">{{ row.name }}</TableCell>
                                    <TableCell class="font-mono text-sm">{{ row.iso_alpha_2 ?? '—' }}</TableCell>
                                    <TableCell class="font-mono text-sm">{{ row.iso_alpha_3 ?? '—' }}</TableCell>
                                    <TableCell class="text-end">
                                        <div class="flex justify-end gap-2">
                                            <Button v-if="canUpdate" variant="outline" size="sm" as-child>
                                                <Link :href="route('modules.countries.edit', row.id)">Editar</Link>
                                            </Button>
                                            <Button
                                                v-if="canDelete"
                                                variant="destructive"
                                                size="sm"
                                                type="button"
                                                @click="destroyCountry(row.id)"
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

                <div v-if="countries.last_page > 1" class="mt-4 flex items-center justify-center gap-3">
                    <Button v-if="countries.prev_page_url" variant="outline" size="sm" as-child>
                        <Link :href="countries.prev_page_url" preserve-scroll>Anterior</Link>
                    </Button>
                    <span class="text-sm text-muted-foreground">
                        Página {{ countries.current_page }} de {{ countries.last_page }}
                    </span>
                    <Button v-if="countries.next_page_url" variant="outline" size="sm" as-child>
                        <Link :href="countries.next_page_url" preserve-scroll>Seguinte</Link>
                    </Button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
