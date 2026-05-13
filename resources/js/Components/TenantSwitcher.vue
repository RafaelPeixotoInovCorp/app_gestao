<script setup>
import TenantAppendModal from '@/Components/TenantAppendModal.vue';
import TenantDeleteModal from '@/Components/TenantDeleteModal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'toolbar',
        validator: (v) => ['toolbar', 'sidebar', 'dashboard'].includes(v),
    },
});

const page = usePage();

const nav = computed(
    () =>
        page.props.tenantNavigation ?? {
            current: null,
            items: [],
            canCreateAdditional: false,
        },
);

const hasSwitcher = computed(
    () => nav.value.current !== null && nav.value.items.length > 0,
);

const isDashboard = computed(() => props.variant === 'dashboard');
const isWideTrigger = computed(() => props.variant === 'sidebar' || props.variant === 'dashboard');

const dropdownWidth = computed(() => {
    if (isDashboard.value) {
        return 'full';
    }
    if (props.variant === 'sidebar') {
        return 'full';
    }

    return '64';
});

const dropdownPanelClass = computed(() => {
    if (isDashboard.value) {
        return 'overflow-hidden rounded-xl bg-white py-2 shadow-xl ring-1 ring-slate-200/80';
    }

    return '';
});

const triggerButtonClass = computed(() => {
    const base =
        'inline-flex max-w-full items-center gap-3 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2';

    if (isDashboard.value) {
        return [
            base,
            'w-full rounded-xl border border-slate-200/90 bg-white px-4 py-3.5 shadow-sm ring-1 ring-slate-900/5 hover:border-indigo-200 hover:bg-slate-50/90',
        ].join(' ');
    }

    if (isWideTrigger.value) {
        return [
            base,
            'w-full rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50',
        ].join(' ');
    }

    return [
        base,
        'rounded-lg border border-slate-200 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50',
    ].join(' ');
});

function selectTenant(tenantId) {
    if (tenantId === nav.value.current?.id) {
        return;
    }
    router.post(
        route('tenants.switch'),
        { tenant_id: tenantId },
        { preserveScroll: true },
    );
}

const appendModalOpen = ref(false);

function openAppendModal() {
    appendModalOpen.value = true;
}

const deleteModalOpen = ref(false);
const deleteTarget = ref(null);

function openDeleteModal(t) {
    deleteTarget.value = t;
    deleteModalOpen.value = true;
}

function closeDeleteModal() {
    deleteModalOpen.value = false;
    deleteTarget.value = null;
}

function onTrailingCellClick(event, t) {
    if (event.target.closest('[data-tenant-delete-overlay]')) {
        return;
    }
    selectTenant(t.id);
}
</script>

<template>
    <div class="contents">
    <div
        v-if="hasSwitcher"
        :class="isWideTrigger ? 'w-full' : 'me-2 min-w-0 max-w-[11rem] shrink-0 sm:me-3 sm:max-w-xs'"
    >
        <Dropdown
            align="left"
            :width="dropdownWidth"
            :content-classes="dropdownPanelClass || undefined"
        >
            <template #trigger>
                <button
                    type="button"
                    :class="triggerButtonClass"
                    :title="nav.current?.name"
                >
                    <span
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white shadow-sm shadow-indigo-600/25"
                        :class="isDashboard ? 'rounded-xl' : 'rounded-md'"
                        aria-hidden="true"
                    >
                        <svg class="h-[18px] w-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                            />
                        </svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="block truncate leading-tight text-slate-900"
                            :class="isDashboard ? 'text-[15px] font-semibold tracking-tight' : 'text-sm font-medium'"
                        >{{ nav.current?.name }}</span>
                        <span class="block truncate text-xs font-normal text-slate-500">{{ nav.current?.slug }}</span>
                    </span>
                    <svg
                        class="h-5 w-5 shrink-0 text-slate-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </template>
            <template #content>
                <template v-if="isDashboard">
                    <div class="border-b border-slate-100 px-4 pb-2 pt-1">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Organizações</p>
                        <p class="mt-0.5 text-xs text-slate-500">Escolhe a organização em que queres trabalhar.</p>
                    </div>
                    <div class="max-h-[min(22rem,70vh)] overflow-y-auto px-2 py-2">
                        <div
                            v-for="t in nav.items"
                            :key="t.id"
                            class="flex items-center gap-1 rounded-lg px-1 py-0.5"
                            :class="
                                t.id === nav.current?.id
                                    ? 'bg-indigo-50 ring-1 ring-indigo-200/60'
                                    : ''
                            "
                        >
                            <button
                                type="button"
                                class="flex min-w-0 flex-1 items-center gap-3 rounded-md px-1.5 py-2 text-left transition"
                                :class="t.id === nav.current?.id ? '' : 'hover:bg-slate-50/90'"
                                @click="selectTenant(t.id)"
                            >
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600"
                                    :class="t.id === nav.current?.id ? 'bg-white text-indigo-600 ring-1 ring-indigo-100' : ''"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                        />
                                    </svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-slate-900">{{ t.name }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ t.slug }}</span>
                                </span>
                            </button>
                            <div
                                class="group/chev relative flex h-9 shrink-0 cursor-pointer items-center justify-center rounded-md"
                                :class="t.id === nav.current?.id ? 'min-w-[4.75rem] px-0.5' : 'w-9'"
                                role="presentation"
                                @click="onTrailingCellClick($event, t)"
                            >
                                <button
                                    v-if="t.can_delete"
                                    type="button"
                                    data-tenant-delete-overlay
                                    class="absolute inset-0 z-10 flex items-center justify-center rounded-md bg-rose-50 text-rose-600 opacity-0 pointer-events-none transition group-hover/chev:pointer-events-auto group-hover/chev:opacity-100"
                                    title="Eliminar organização"
                                    aria-label="Eliminar organização"
                                    @click.stop="openDeleteModal(t)"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                        />
                                    </svg>
                                </button>
                                <span
                                    v-if="t.id === nav.current?.id"
                                    class="relative z-0 inline-flex items-center gap-1 rounded-full bg-indigo-600 px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-white transition pointer-events-none group-hover/chev:opacity-0"
                                >
                                    Ativa
                                </span>
                                <svg
                                    v-else
                                    class="relative z-0 h-5 w-5 shrink-0 text-slate-300 transition pointer-events-none group-hover/chev:opacity-0"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="nav.canCreateAdditional"
                        class="border-t border-slate-100 bg-gradient-to-r from-slate-50/90 to-indigo-50/50 p-2.5"
                    >
                        <button
                            type="button"
                            class="group flex w-full items-center gap-3 rounded-xl border border-indigo-200/70 bg-white px-3 py-2.5 text-left shadow-sm ring-1 ring-indigo-500/5 transition hover:border-indigo-300 hover:bg-indigo-50/80 hover:shadow-md hover:ring-indigo-500/10"
                            @click="openAppendModal"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-600/25 transition group-hover:scale-[1.02]"
                                aria-hidden="true"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-slate-900">Nova organização</span>
                                <span class="mt-0.5 block text-xs font-normal text-slate-500">Criar ou associar outra empresa</span>
                            </span>
                            <svg
                                class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-indigo-500"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>
                </template>
                <template v-else>
                    <div class="max-h-72 overflow-y-auto py-1">
                        <div
                            v-for="t in nav.items"
                            :key="t.id"
                            class="flex items-center gap-0.5 px-2"
                        >
                            <button
                                type="button"
                                class="min-w-0 flex-1 rounded-md px-2 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-100"
                                :class="{
                                    'bg-indigo-50 font-medium text-indigo-900': t.id === nav.current?.id,
                                }"
                                @click="selectTenant(t.id)"
                            >
                                <span class="block truncate">{{ t.name }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ t.slug }}</span>
                            </button>
                            <div
                                class="group/chev relative flex h-8 shrink-0 cursor-pointer items-center justify-center rounded-md"
                                :class="t.id === nav.current?.id ? 'min-w-[3.75rem] px-0.5' : 'w-8'"
                                role="presentation"
                                @click="onTrailingCellClick($event, t)"
                            >
                                <button
                                    v-if="t.can_delete"
                                    type="button"
                                    data-tenant-delete-overlay
                                    class="absolute inset-0 z-10 flex items-center justify-center rounded-md bg-rose-50 text-rose-600 opacity-0 pointer-events-none transition group-hover/chev:pointer-events-auto group-hover/chev:opacity-100"
                                    title="Eliminar organização"
                                    aria-label="Eliminar organização"
                                    @click.stop="openDeleteModal(t)"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                        />
                                    </svg>
                                </button>
                                <span
                                    v-if="t.id === nav.current?.id"
                                    class="relative z-0 rounded bg-indigo-600 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-white transition pointer-events-none group-hover/chev:opacity-0"
                                >
                                    Ativa
                                </span>
                                <svg
                                    v-else
                                    class="relative z-0 h-4 w-4 shrink-0 text-slate-300 transition pointer-events-none group-hover/chev:opacity-0"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                        <div v-if="nav.canCreateAdditional" class="border-t border-slate-100 p-2">
                            <button
                                type="button"
                                class="group flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50"
                                @click="openAppendModal"
                            >
                                <span
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-indigo-600 text-white shadow-sm shadow-indigo-600/20"
                                    aria-hidden="true"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                </span>
                                <span class="truncate">Nova organização</span>
                            </button>
                        </div>
                    </div>
                </template>
            </template>
        </Dropdown>
    </div>
    <TenantAppendModal v-if="nav.canCreateAdditional" v-model="appendModalOpen" />
    <TenantDeleteModal v-model="deleteModalOpen" :tenant="deleteTarget" @close="closeDeleteModal" />
    </div>
</template>
