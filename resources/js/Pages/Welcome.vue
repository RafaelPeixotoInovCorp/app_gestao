<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Bem-vindo" />
    <div class="min-h-screen bg-slate-950 text-slate-100">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 py-10 lg:px-8">
            <header class="flex items-center justify-between">
                <h1 class="text-lg font-semibold tracking-wide text-slate-200">
                    Sistema de Gestão
                </h1>

                <nav v-if="canLogin" class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-md border border-slate-700 px-4 py-2 text-sm text-slate-200 transition hover:border-slate-500"
                    >
                        Painel
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="rounded-md border border-indigo-500 bg-indigo-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-400"
                        >
                            Entrar
                        </Link>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="rounded-md border border-slate-700 px-4 py-2 text-sm text-slate-200 transition hover:border-slate-500"
                        >
                            Registar
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="flex flex-1 items-center py-12">
                <div class="grid w-full gap-10 lg:grid-cols-2 lg:items-center">
                    <section>
                        <p class="mb-4 inline-block rounded-full border border-slate-700 px-3 py-1 text-xs uppercase tracking-wider text-slate-300">
                            Plataforma de Gestão
                        </p>
                        <h2 class="text-4xl font-bold leading-tight text-white lg:text-5xl">
                            Centraliza os teus processos num único lugar.
                        </h2>
                        <p class="mt-5 max-w-xl text-base leading-relaxed text-slate-300">
                            Organiza módulos, entidades e operações sem fricção.
                            Um espaço único para acompanhar o que importa no teu
                            negócio.
                        </p>
                    </section>

                    <section class="grid gap-4">
                        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-5">
                            <h3 class="text-sm font-semibold text-white">Módulos</h3>
                            <p class="mt-2 text-sm text-slate-300">
                                Estrutura o sistema por áreas funcionais com
                                acesso rápido.
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-5">
                            <h3 class="text-sm font-semibold text-white">Entidades</h3>
                            <p class="mt-2 text-sm text-slate-300">
                                Regista e mantém informação com validação e
                                consistência.
                            </p>
                        </div>
                        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-5">
                            <h3 class="text-sm font-semibold text-white">Produtividade</h3>
                            <p class="mt-2 text-sm text-slate-300">
                                Menos navegação e mais foco nas tarefas do dia a
                                dia.
                            </p>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</template>
