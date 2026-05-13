<script setup>
</script>

<template>
    <div
        class="relative flex min-h-screen flex-col items-center bg-gradient-to-br from-slate-50 via-white to-indigo-50/55 px-4 pt-10 pb-8 sm:justify-center sm:px-6 sm:pt-0"
    >
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_80%_50%_at_50%_-10%,rgb(99_102_241/0.12),transparent)]"
            aria-hidden="true"
        />
        <div
            class="relative z-10 w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white/95 px-6 py-8 shadow-xl shadow-slate-900/[0.06] backdrop-blur-sm sm:max-w-md"
        >
            <slot />
        </div>
    </div>
</template>
