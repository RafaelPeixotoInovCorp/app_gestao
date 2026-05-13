<script setup>
import SidebarNavLink from '@/Components/SidebarNavLink.vue';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    kind: {
        type: String,
        default: 'other',
    },
    items: {
        type: Array,
        required: true,
    },
});

const page = usePage();

const categoryBadgeClass = computed(() => {
    const map = {
        entities: 'bg-emerald-100 text-emerald-700',
        operations: 'bg-amber-100 text-amber-700',
        financial: 'bg-green-100 text-green-700',
        access: 'bg-indigo-100 text-indigo-700',
        settings: 'bg-slate-100 text-slate-700',
        other: 'bg-rose-100 text-rose-700',
    };
    const tone = map[props.kind] ?? map.other;
    return `flex h-8 w-8 shrink-0 items-center justify-center rounded-lg ${tone}`;
});

const desktopTriggerRef = ref(null);
const desktopPanelOpen = ref(false);
const desktopPanelRef = ref(null);
const desktopPanelStyle = ref({});
let desktopCloseTimer = null;

function isModuleActive(slug) {
    const m = page.url.match(/^\/modulos\/([^/?]+)/);

    return m?.[1] === slug;
}

const groupActive = computed(() =>
    props.items.some((item) => route().current('modules.show') && isModuleActive(item.slug)),
);

/** Remove o prefixo da categoria (ex.: "Configurações - Logs" → "Logs"). Ignora diferenças de maiúsculas no prefixo. */
function itemShortLabel(item) {
    const full = String(item?.label ?? '').trim();
    const cat = String(props.label ?? '').trim();
    if (!cat || !full) {
        return full;
    }
    const escaped = cat.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const m = full.match(new RegExp(`^${escaped}\\s*[-–—]\\s*(.+)$`, 'iu'));
    if (m?.[1]) {
        return m[1].trim();
    }
    return full;
}

function clearDesktopCloseTimer() {
    if (desktopCloseTimer != null) {
        clearTimeout(desktopCloseTimer);
        desktopCloseTimer = null;
    }
}

function positionDesktopPanel() {
    const el = desktopTriggerRef.value;
    if (!el) {
        return;
    }
    const r = el.getBoundingClientRect();
    const gap = 8;
    const margin = 8;
    const width = 256;
    const maxPanelH = Math.min(window.innerHeight * 0.7, 384);
    let left = r.right + gap;
    if (left + width > window.innerWidth - gap) {
        left = Math.max(gap, r.left - width - gap);
    }

    desktopPanelStyle.value = {
        top: `${r.top}px`,
        left: `${left}px`,
        width: `${width}px`,
    };

    nextTick(() => {
        const panel = desktopPanelRef.value;
        const h = panel ? Math.min(panel.getBoundingClientRect().height, maxPanelH) : maxPanelH;
        let top = r.top;
        if (top + h > window.innerHeight - margin) {
            top = window.innerHeight - margin - h;
        }
        if (top < margin) {
            top = margin;
        }
        desktopPanelStyle.value = {
            ...desktopPanelStyle.value,
            top: `${top}px`,
        };
    });
}

function openDesktopPanel() {
    clearDesktopCloseTimer();
    desktopPanelOpen.value = true;
    nextTick(() => positionDesktopPanel());
}

function scheduleCloseDesktopPanel() {
    clearDesktopCloseTimer();
    desktopCloseTimer = window.setTimeout(() => {
        desktopPanelOpen.value = false;
        desktopCloseTimer = null;
    }, 120);
}

function onDesktopPanelEnter() {
    clearDesktopCloseTimer();
}

function onWindowResizeOrScroll() {
    if (desktopPanelOpen.value) {
        positionDesktopPanel();
    }
}

onMounted(() => {
    window.addEventListener('resize', onWindowResizeOrScroll);
    window.addEventListener('scroll', onWindowResizeOrScroll, true);
});

onBeforeUnmount(() => {
    clearDesktopCloseTimer();
    window.removeEventListener('resize', onWindowResizeOrScroll);
    window.removeEventListener('scroll', onWindowResizeOrScroll, true);
});
</script>

<template>
    <!-- Mobile / touch: acordeão nativo -->
    <details class="group/details relative z-0 lg:hidden">
        <summary
            class="flex cursor-pointer list-none items-center justify-between gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-700 transition marker:content-none hover:bg-slate-100 [&::-webkit-details-marker]:hidden"
            :class="
                groupActive
                    ? 'border border-indigo-200/80 bg-indigo-50 text-indigo-900'
                    : 'border border-transparent'
            "
        >
            <span class="flex min-w-0 flex-1 items-center gap-2.5">
                <span :class="categoryBadgeClass" aria-hidden="true">
                    <!-- entities -->
                    <svg
                        v-if="kind === 'entities'"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                        />
                    </svg>
                    <!-- operations -->
                    <svg
                        v-else-if="kind === 'operations'"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                    <!-- financial -->
                    <svg
                        v-else-if="kind === 'financial'"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <!-- access -->
                    <svg
                        v-else-if="kind === 'access'"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                        />
                    </svg>
                    <!-- settings -->
                    <svg
                        v-else-if="kind === 'settings'"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                        />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <!-- other -->
                    <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                        />
                    </svg>
                </span>
                <span class="min-w-0 truncate">{{ label }}</span>
            </span>
            <svg
                class="size-4 shrink-0 text-slate-400 transition group-open/details:rotate-180"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </summary>
        <div class="mt-1 space-y-0.5 border-l-2 border-slate-100 py-1 ps-3">
            <SidebarNavLink
                v-for="item in items"
                :key="item.key"
                :href="route(item.route, item.slug)"
                :active="route().current('modules.show') && isModuleActive(item.slug)"
            >
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600"
                    aria-hidden="true"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                        />
                    </svg>
                </span>
                <span class="truncate text-sm font-medium">{{ itemShortLabel(item) }}</span>
            </SidebarNavLink>
        </div>
    </details>

    <!-- Desktop: hover abre painel (Teleport evita recorte pelo overflow da nav) -->
    <div
        ref="desktopTriggerRef"
        class="relative z-0 hidden lg:block"
        @mouseenter="openDesktopPanel"
        @mouseleave="scheduleCloseDesktopPanel"
    >
        <div
            class="flex cursor-default select-none items-center justify-between gap-2 rounded-lg border px-3 py-2.5 text-sm font-semibold transition"
            :class="
                desktopPanelOpen
                    ? 'border-slate-200 bg-slate-50 text-slate-900'
                    : groupActive
                      ? 'border-indigo-200/80 bg-indigo-50 text-indigo-900'
                      : 'border-transparent text-slate-700 hover:border-slate-200 hover:bg-slate-50 hover:text-slate-900'
            "
            role="presentation"
        >
            <span class="flex min-w-0 flex-1 items-center gap-2.5">
                <span :class="categoryBadgeClass" aria-hidden="true">
                    <svg
                        v-if="kind === 'entities'"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                        />
                    </svg>
                    <svg
                        v-else-if="kind === 'operations'"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                    <svg
                        v-else-if="kind === 'financial'"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <svg
                        v-else-if="kind === 'access'"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                        />
                    </svg>
                    <svg
                        v-else-if="kind === 'settings'"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                        />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                        />
                    </svg>
                </span>
                <span class="min-w-0 truncate">{{ label }}</span>
            </span>
            <svg
                class="size-4 shrink-0 text-slate-400 opacity-70 transition"
                :class="desktopPanelOpen ? 'translate-x-0.5 opacity-100' : ''"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-show="desktopPanelOpen"
            ref="desktopPanelRef"
            class="fixed z-[1050] hidden max-h-[min(70vh,24rem)] overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl ring-1 ring-slate-900/5 lg:block"
            :style="desktopPanelStyle"
            @mouseenter="onDesktopPanelEnter"
            @mouseleave="scheduleCloseDesktopPanel"
        >
            <SidebarNavLink
                v-for="item in items"
                :key="item.key"
                :href="route(item.route, item.slug)"
                :active="route().current('modules.show') && isModuleActive(item.slug)"
                class="!mx-1 !rounded-md"
            >
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600"
                    aria-hidden="true"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                        />
                    </svg>
                </span>
                <span class="truncate text-sm font-medium">{{ itemShortLabel(item) }}</span>
            </SidebarNavLink>
        </div>
    </Teleport>
</template>
