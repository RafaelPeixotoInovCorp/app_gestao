<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, inject } from 'vue';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
        default: false,
    },
});

const closeMobileSidebar = inject('closeMobileSidebar', null);

const classes = computed(() =>
    props.active
        ? 'flex items-center gap-3 rounded-lg border border-indigo-200/80 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-900 shadow-sm'
        : 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900',
);

function onNavigate() {
    if (typeof closeMobileSidebar === 'function') {
        closeMobileSidebar();
    }
}
</script>

<template>
    <Link :href="href" :class="classes" @click="onNavigate">
        <slot />
    </Link>
</template>
