<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    suggestedName: {
        type: String,
        default: '',
    },
});

const form = useForm({
    name: props.suggestedName || '',
    slug: '',
});

const submit = () => {
    form.post(route('tenants.onboarding.store'));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Criar organização" />

        <div class="mx-auto max-w-lg px-4 py-10 sm:px-6 lg:px-8">
            <h1
                class="text-2xl font-semibold tracking-tight text-slate-900"
            >
                Criar a sua organização
            </h1>
            <p class="mt-2 text-sm text-slate-600">
                Precisa de uma organização para continuar. Poderá alterar o nome mais tarde nas definições.
            </p>

            <form class="mt-8 space-y-6" @submit.prevent="submit">
                <div>
                    <InputLabel for="name" value="Nome da organização" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autocomplete="organization"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel
                        for="slug"
                        value="Identificador (URL, opcional)"
                    />
                    <TextInput
                        id="slug"
                        v-model="form.slug"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="ex.: minha-empresa"
                        autocomplete="off"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Apenas letras minúsculas, números e hífens. Se deixar em
                        branco, será gerado automaticamente.
                    </p>
                    <InputError class="mt-2" :message="form.errors.slug" />
                </div>

                <PrimaryButton :disabled="form.processing">
                    Continuar
                </PrimaryButton>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
