<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    modules: {
        type: Array,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
});

const moduleSearch = ref('');

const filteredModules = computed(() => {
    const query = moduleSearch.value.trim().toLowerCase();
    if (!query) return props.modules;
    return props.modules.filter((moduleItem) => moduleItem.label.toLowerCase().includes(query));
});
</script>

<template>
    <Head title="Módulos" />
    <AuthenticatedLayout>
        <div class="py-8 sm:py-10">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-2xl border border-slate-200 bg-gradient-to-r from-sky-50 via-indigo-50 to-violet-50 p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-600">Painel principal</p>
                    <h3 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">
                        Escolha um módulo para começar
                    </h3>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">
                        Navegação simplificada com foco em rapidez para as tarefas de gestão mais frequentes.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <Card class="border-slate-200 shadow-sm">
                        <CardContent class="p-5">
                            <p class="text-sm font-medium text-slate-600">Clientes</p>
                            <p class="mt-1 text-3xl font-semibold text-slate-900">
                                {{ stats.clients }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">Total registado</p>
                        </CardContent>
                    </Card>
                    <Card class="border-slate-200 shadow-sm">
                        <CardContent class="p-5">
                            <p class="text-sm font-medium text-slate-600">Fornecedores</p>
                            <p class="mt-1 text-3xl font-semibold text-slate-900">
                                {{ stats.suppliers }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">Total registado</p>
                        </CardContent>
                    </Card>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">Pesquisa rápida</p>
                    <Input
                        v-model="moduleSearch"
                        placeholder="Pesquisar módulo..."
                        class="border-slate-300 bg-white"
                    />
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <Link
                        v-for="moduleItem in filteredModules"
                        :key="moduleItem.key"
                        :href="route(moduleItem.route, moduleItem.slug)"
                        class="group rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                    >
                        <Card class="h-full border-slate-200 transition-all duration-200 group-hover:-translate-y-0.5 group-hover:border-indigo-300 group-hover:shadow-md">
                            <CardContent class="p-5">
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ moduleItem.label }}
                                </p>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
                <p v-if="!filteredModules.length" class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-500">
                    Nenhum módulo encontrado para essa pesquisa.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
