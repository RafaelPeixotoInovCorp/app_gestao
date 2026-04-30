<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    module: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="module.label" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ module.label }}</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <p class="text-sm text-gray-700">
                        Área do módulo <strong>{{ module.label }}</strong> preparada com autenticação, autorização e menu central.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
