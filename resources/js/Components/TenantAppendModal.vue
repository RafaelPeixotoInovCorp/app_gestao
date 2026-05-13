<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue']);

const form = useForm({
    name: '',
    slug: '',
});

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            form.clearErrors();
            form.reset();
        }
    },
);

function close() {
    emit('update:modelValue', false);
}

function submit() {
    form.post(route('tenants.store'), {
        preserveScroll: true,
        onSuccess: () => {
            close();
        },
    });
}
</script>

<template>
    <Modal :show="modelValue" max-width="lg" @close="close">
        <div class="p-6 sm:p-8">
            <h2 class="text-xl font-semibold tracking-tight text-slate-900">Nova organização</h2>
            <p class="mt-2 text-sm text-slate-600">
                Ficas como responsável desta organização e poderás convidar outros utilizadores.
            </p>

            <form class="mt-6 space-y-5" @submit.prevent="submit">
                <div>
                    <InputLabel for="append-tenant-name" value="Nome" />
                    <TextInput
                        id="append-tenant-name"
                        v-model="form.name"
                        type="text"
                        class="mt-1 block w-full"
                        required
                        autocomplete="organization"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="append-tenant-slug" value="Identificador (opcional)" />
                    <TextInput
                        id="append-tenant-slug"
                        v-model="form.slug"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="ex.: filial-norte"
                        autocomplete="off"
                    />
                    <p class="mt-1 text-xs text-slate-500">
                        Apenas letras minúsculas, números e hífens. Se deixares em branco, será gerado automaticamente.
                    </p>
                    <InputError class="mt-2" :message="form.errors.slug" />
                </div>

                <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="close"
                    >
                        Cancelar
                    </button>
                    <PrimaryButton class="justify-center" :disabled="form.processing">
                        Criar organização
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
