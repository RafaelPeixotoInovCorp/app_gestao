<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    slug: '',
});

const submit = () => {
    form.post(route('tenants.store'));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Nova organização" />

        <div class="mx-auto max-w-lg px-4 py-10 sm:px-6 lg:px-8">
            <h1
                class="text-2xl font-semibold tracking-tight text-slate-900"
            >
                Nova organização
            </h1>
            <p class="mt-2 text-sm text-slate-600">
                Fica como responsável desta organização e poderá convidar outros utilizadores.
            </p>

            <form class="mt-8 space-y-6" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="Nome" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="slug" value="Identificador (opcional)" />
                    <TextInput
                        id="slug"
                        v-model="form.slug"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="ex.: filial-norte"
                    />
                    <InputError class="mt-2" :message="form.errors.slug" />
                </div>

                <PrimaryButton :disabled="form.processing">
                    Criar organização
                </PrimaryButton>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
