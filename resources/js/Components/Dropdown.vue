<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    align: {
        type: String,
        default: 'right',
    },
    width: {
        type: String,
        default: '48',
    },
    contentClasses: {
        type: String,
        default:
            'overflow-y-auto overflow-x-hidden rounded-lg bg-white py-1 shadow-xl ring-1 ring-slate-200/80',
    },
});

const open = ref(false);
const triggerRef = ref(null);
const panelStyle = ref({
    top: '8px',
    bottom: 'auto',
    left: '8px',
    right: 'auto',
    maxHeight: 'min(20rem, calc(100vh - 1rem))',
});

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') {
        open.value = false;
    }
};

const widthClass = computed(() => {
    const w = String(props.width);

    return (
        {
            48: 'w-48',
            64: 'w-64 min-w-[14rem]',
            80: 'w-80 min-w-[16rem] max-w-[calc(100vw-2rem)]',
            full: 'min-w-[12rem] max-w-[min(22rem,calc(100vw-2rem))]',
        }[w] ?? 'w-48'
    );
});

function minPanelWidthPx() {
    switch (String(props.width)) {
        case '64':
            return 256;
        case '80':
            return 320;
        case 'full':
            return 192;
        default:
            return 192;
    }
}

function anchorElement() {
    const root = triggerRef.value;
    if (!root) {
        return null;
    }
    const btn = root.querySelector('button');
    return btn instanceof HTMLElement ? btn : root;
}

function positionPanel() {
    if (!open.value) {
        return;
    }
    const el = anchorElement();
    if (!el) {
        return;
    }
    const r = el.getBoundingClientRect();
    if (r.width < 2 || r.height < 2) {
        open.value = false;
        return;
    }

    const gap = 8;
    const margin = 8;
    const viewportH = window.innerHeight;
    const viewportW = window.innerWidth;
    const minW = minPanelWidthPx();

    const spaceBelow = viewportH - r.bottom - gap - margin;
    const spaceAbove = r.top - margin - gap;

    let openUp = false;
    let maxH = Math.min(320, spaceBelow);

    if (maxH < 100 && spaceAbove > Math.max(maxH, 80)) {
        openUp = true;
        maxH = Math.max(80, Math.min(320, spaceAbove));
    } else {
        maxH = Math.max(80, Math.min(320, maxH));
    }

    const style = {
        maxHeight: `${maxH}px`,
    };

    if (openUp) {
        style.top = 'auto';
        style.bottom = `${viewportH - r.top + gap}px`;
    } else {
        style.bottom = 'auto';
        style.top = `${r.bottom + gap}px`;
    }

    if (props.align === 'right') {
        style.right = `${Math.max(margin, viewportW - r.right)}px`;
        style.left = 'auto';
    } else {
        style.left = `${Math.max(margin, Math.min(r.left, viewportW - margin - minW))}px`;
        style.right = 'auto';
    }

    if (props.width === 'full') {
        style.width = `${Math.min(Math.max(r.width, minW), viewportW - 2 * margin)}px`;
    }

    panelStyle.value = style;
}

function onReposition() {
    if (open.value) {
        positionPanel();
    }
}

function toggleOpen() {
    open.value = !open.value;
}

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }
    nextTick(() => {
        positionPanel();
        requestAnimationFrame(() => {
            positionPanel();
        });
    });
});

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);
    window.addEventListener('resize', onReposition);
    window.addEventListener('scroll', onReposition, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', closeOnEscape);
    window.removeEventListener('resize', onReposition);
    window.removeEventListener('scroll', onReposition, true);
});
</script>

<template>
    <div class="relative">
        <div ref="triggerRef" class="w-full" @click.stop="toggleOpen">
            <slot name="trigger" />
        </div>

        <Teleport to="body">
            <template v-if="open">
                <div
                    class="fixed inset-0 bg-slate-900/20"
                    style="z-index: 50000"
                    aria-hidden="true"
                    @click="open = false"
                />
                <div
                    class="fixed overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-xl ring-1 ring-slate-900/10"
                    style="z-index: 50001"
                    :class="[widthClass, props.align === 'left' ? 'origin-top-left' : 'origin-top-right']"
                    :style="panelStyle"
                    role="menu"
                    @click="open = false"
                >
                    <div :class="props.contentClasses">
                        <slot name="content" />
                    </div>
                </div>
            </template>
        </Teleport>
    </div>
</template>
