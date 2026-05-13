<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    tenant: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'close']);

const confirmName = ref('');
const processing = ref(false);

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            confirmName.value = '';
            processing.value = false;
        }
    },
);

const nameMatches = computed(() => {
    if (!props.tenant?.name) {
        return false;
    }
    return confirmName.value.trim() === String(props.tenant.name).trim();
});

function close() {
    emit('update:modelValue', false);
    emit('close');
}

function destroy() {
    if (!props.tenant?.id || !nameMatches.value) {
        return;
    }
    processing.value = true;
    router.delete(route('tenants.destroy', props.tenant.id), {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
        },
        onSuccess: () => {
            close();
        },
    });
}
</script>

<template>
    <Modal max-width="lg" :show="modelValue" @close="close">
        <div v-if="tenant" class="p-6 sm:p-8">
            <h2 class="text-xl font-semibold tracking-tight text-slate-900">Eliminar organização</h2>
            <p class="mt-2 text-sm text-slate-600">
                Esta ação remove definitivamente
                <strong class="text-slate-900">{{ tenant.name }}</strong>
                e todos os dados associados (clientes, registos de módulos, subscrição, etc.). Os outros membros deixam de ter acesso.
            </p>
            <p class="mt-3 text-sm font-medium text-rose-700">
                Para confirmar, escreve o nome completo da organização tal como aparece acima.
            </p>

            <div class="mt-4">
                <InputLabel for="tenant-delete-confirm" value="Nome da organização" />
                <TextInput
                    id="tenant-delete-confirm"
                    v-model="confirmName"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="off"
                />
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="inline-flex justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    :disabled="processing"
                    @click="close"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="inline-flex justify-center rounded-md border border-transparent bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="processing || !nameMatches"
                    @click="destroy"
                >
                    Eliminar para sempre
                </button>
            </div>
        </div>
    </Modal>
</template>
