<script setup>
import { computed, provide, ref } from 'vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import SidebarNavLink from '@/Components/SidebarNavLink.vue';
import SidebarModuleCategory from '@/Components/SidebarModuleCategory.vue';
import { Link, usePage } from '@inertiajs/vue3';

const sidebarOpen = ref(false);
const page = usePage();

const moduleGroups = computed(() => page.props.navigation?.moduleGroups ?? []);
const sidebarBrand = computed(() => {
    const n = String(page.props.app?.name ?? '').trim();
    if (!n || /^laravel$/i.test(n)) {
        return 'Gestão';
    }
    return n;
});

function closeSidebar() {
    sidebarOpen.value = false;
}

provide('closeMobileSidebar', closeSidebar);

const subscriptionRouteActive = computed(() => String(route().current() ?? '').startsWith('subscription.'));
</script>

<template>
    <div class="theme min-h-screen bg-slate-100/90 lg:flex lg:h-screen lg:min-h-0 lg:flex-col lg:overflow-hidden">
        <!-- Mobile top bar -->
        <header
            class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-slate-200 bg-white px-4 lg:hidden"
        >
            <div class="flex min-w-0 items-center gap-2">
                <Link
                    :href="route('dashboard')"
                    class="flex shrink-0 items-center gap-2 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                    @click="closeSidebar"
                >
                    <span
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-sm"
                        aria-hidden="true"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                            />
                        </svg>
                    </span>
                    <span class="truncate text-sm font-semibold text-slate-900">{{ sidebarBrand }}</span>
                </Link>
            </div>
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white p-2 text-slate-600 shadow-sm hover:bg-slate-50"
                aria-label="Abrir menu"
                @click="sidebarOpen = true"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </header>

        <!-- Mobile overlay -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-show="sidebarOpen"
                class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-[2px] lg:hidden"
                @click="closeSidebar"
            />
        </Transition>

        <div class="flex min-h-[calc(100vh-3.5rem)] flex-1 lg:min-h-0 lg:flex-1 lg:overflow-hidden">
            <!-- Sidebar -->
            <aside
                :class="[
                    'fixed inset-y-0 left-0 z-50 flex w-72 max-w-[88vw] flex-col overflow-hidden border-r border-slate-200 bg-white shadow-xl transition-transform duration-200 ease-out lg:static lg:z-30 lg:h-full lg:max-h-full lg:min-h-0 lg:max-w-none lg:shrink-0 lg:translate-x-0 lg:shadow-none',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                ]"
            >
                <div class="flex h-14 shrink-0 items-center gap-2 border-b border-slate-100 px-4 lg:h-16 lg:px-5">
                    <Link
                        :href="route('dashboard')"
                        class="flex min-w-0 items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        @click="closeSidebar"
                    >
                        <span
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white shadow-md shadow-indigo-500/25"
                            aria-hidden="true"
                        >
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                                />
                            </svg>
                        </span>
                        <div class="min-w-0 self-center">
                            <p class="truncate text-sm font-semibold tracking-tight text-slate-900">{{ sidebarBrand }}</p>
                        </div>
                    </Link>
                    <button
                        type="button"
                        class="ms-auto rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                        aria-label="Fechar menu"
                        @click="closeSidebar"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav class="min-h-0 flex-1 overflow-y-auto overflow-x-visible px-3 py-4 lg:px-4">
                    <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Principal</p>
                    <div class="space-y-0.5">
                        <SidebarNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                            </span>
                            Painel
                        </SidebarNavLink>
                        <SidebarNavLink
                            :href="route('subscription.dashboard')"
                            :active="subscriptionRouteActive"
                        >
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                            </span>
                            Subscrição
                        </SidebarNavLink>
                    </div>

                    <template v-if="moduleGroups.length">
                        <p class="mt-6 px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                            Módulos
                        </p>
                        <div class="space-y-1">
                            <SidebarModuleCategory
                                v-for="group in moduleGroups"
                                :key="group.label"
                                :label="group.label"
                                :kind="group.kind ?? 'other'"
                                :items="group.items"
                            />
                        </div>
                    </template>
                </nav>

                <div class="shrink-0 border-t border-slate-100 bg-slate-50/80 p-4">
                    <Dropdown align="left" width="64">
                        <template #trigger>
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-left shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
                            >
                                <span
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-slate-200 to-slate-300 text-slate-600"
                                    aria-hidden="true"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                        />
                                    </svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-slate-900">{{
                                        $page.props.auth.user?.name
                                    }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{
                                        $page.props.auth.user?.email
                                    }}</span>
                                </span>
                                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Perfil</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">
                                Terminar sessão
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </aside>

            <!-- Main -->
            <div class="relative z-0 flex min-h-0 min-w-0 flex-1 flex-col overflow-y-auto lg:min-h-0">
                <header
                    v-if="$slots.header"
                    class="shrink-0 border-b border-slate-200/80 bg-white px-4 py-5 shadow-sm sm:px-6 lg:px-8"
                >
                    <slot name="header" />
                </header>
                <main class="flex-1">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
