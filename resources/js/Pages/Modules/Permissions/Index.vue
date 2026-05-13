<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head } from '@inertiajs/vue3';

defineProps({
    module: { type: Object, required: true },
    groups: { type: Array, required: true },
});
</script>

<template>
    <Head :title="module.label" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-foreground">
                    {{ module.label }}
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <p class="mb-4 text-sm text-muted-foreground">
                    Grupos de permissões (papéis) e quantas permissões estão atribuídas a cada um no sistema.
                </p>

                <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nome do grupo</TableHead>
                                <TableHead class="text-end tabular-nums">Nº de permissões</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <template v-if="!groups.length">
                                <TableRow>
                                    <TableCell colspan="2" class="text-center text-muted-foreground py-8">
                                        Sem grupos definidos.
                                    </TableCell>
                                </TableRow>
                            </template>
                            <TableRow v-for="row in groups" :key="row.name">
                                <TableCell class="font-medium">{{ row.display_name }}</TableCell>
                                <TableCell class="text-end tabular-nums">{{ row.permissions_count }}</TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
