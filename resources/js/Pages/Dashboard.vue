<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Painel" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Painel
                </h2>
                <Badge class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-700 hover:bg-emerald-100">
                    Sessão ativa
                </Badge>
            </div>
        </template>

        <div class="py-8 sm:py-10">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="rounded-2xl border border-slate-200 bg-gradient-to-r from-sky-50 via-indigo-50 to-violet-50 p-6 shadow-sm">
                    <p class="text-sm font-medium text-slate-600">Bem-vindo de volta</p>
                    <h3 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900">
                        A sua gestão está no controlo
                    </h3>
                    <p class="mt-2 max-w-2xl text-sm text-slate-600">
                        Acompanhe rapidamente o estado da aplicação e aceda aos atalhos mais usados no dia a dia.
                    </p>
                    <div class="mt-5 flex flex-wrap gap-3">
                        <Button as-child>
                            <Link :href="route('modules.show', 'clients')">Ver clientes</Link>
                        </Button>
                        <Button as-child variant="secondary">
                            <Link :href="route('profile.edit')">Editar perfil</Link>
                        </Button>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <Card class="border-slate-200 shadow-sm">
                        <CardHeader class="pb-2">
                            <CardDescription>Estado da sessão</CardDescription>
                            <CardTitle class="text-base">Autenticação</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-slate-600">
                                Sessão iniciada com sucesso.
                            </p>
                        </CardContent>
                    </Card>

                    <Card class="border-slate-200 shadow-sm">
                        <CardHeader class="pb-2">
                            <CardDescription>Navegação rápida</CardDescription>
                            <CardTitle class="text-base">Ações úteis</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm text-slate-600">
                            <p>- Gerir entidades de clientes e fornecedores.</p>
                            <p>- Atualizar preferências da conta.</p>
                        </CardContent>
                    </Card>

                    <Card class="border-slate-200 shadow-sm">
                        <CardHeader class="pb-2">
                            <CardDescription>Próximos passos</CardDescription>
                            <CardTitle class="text-base">Começar agora</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <Button as-child class="w-full">
                                <Link :href="route('entities.create')">Criar nova entidade</Link>
                            </Button>
                            <Button as-child variant="outline" class="w-full">
                                <Link :href="route('dashboard')">Atualizar painel</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
