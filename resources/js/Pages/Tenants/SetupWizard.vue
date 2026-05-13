<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    step: { type: Number, default: 1 },
    branding: { type: Object, default: () => ({}) },
    tenant_name: { type: String, default: '' },
    links: { type: Object, required: true },
});

const form = useForm({
    display_name: props.branding?.display_name ?? '',
    tagline: props.branding?.tagline ?? '',
    accent: props.branding?.accent ?? '#4f46e5',
});

watch(
    () => props.branding,
    (b) => {
        form.display_name = b?.display_name ?? '';
        form.tagline = b?.tagline ?? '';
        form.accent = b?.accent ?? '#4f46e5';
    },
    { deep: true },
);

const stepLabels = ['Identidade', 'Equipa', 'Permissões'];

const currentStep = computed(() => Math.min(3, Math.max(1, props.step || 1)));

function submitBranding() {
    form.patch(route('tenants.setup.branding'));
}

function submitTeam() {
    router.post(route('tenants.setup.team'));
}

function submitComplete() {
    router.post(route('tenants.setup.complete'));
}

function submitSkip() {
    router.post(route('tenants.setup.skip'));
}
</script>

<template>
    <Head title="Configuração inicial" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
                <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600">Onboarding</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">Configuração inicial</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Subscrição e permissões base já estão activas. Personaliza a organização e revê o acesso em três passos.
                </p>

                <ol class="mt-8 flex gap-2">
                    <li
                        v-for="(label, idx) in stepLabels"
                        :key="label"
                        class="flex flex-1 flex-col items-center gap-2 text-center"
                    >
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold transition"
                            :class="
                                idx + 1 < currentStep
                                    ? 'bg-emerald-100 text-emerald-800'
                                    : idx + 1 === currentStep
                                      ? 'bg-indigo-600 text-white ring-2 ring-indigo-200 ring-offset-2'
                                      : 'bg-slate-100 text-slate-500'
                            "
                        >
                            <span v-if="idx + 1 < currentStep">✓</span>
                            <span v-else>{{ idx + 1 }}</span>
                        </span>
                        <span
                            class="text-[11px] font-medium leading-tight sm:text-xs"
                            :class="idx + 1 === currentStep ? 'text-slate-900' : 'text-slate-500'"
                        >{{ label }}</span>
                    </li>
                </ol>

                <!-- Passo 1: Branding -->
                <div v-if="currentStep === 1" class="mt-10 space-y-5">
                    <h2 class="text-lg font-semibold text-slate-900">Identidade da organização</h2>
                    <p class="text-sm text-slate-600">
                        Nome oficial da organização: <strong>{{ tenant_name }}</strong>. Podes definir um nome público e um detalhe visual para o painel.
                    </p>
                    <form class="space-y-4" @submit.prevent="submitBranding">
                        <div>
                            <InputLabel for="display_name" value="Nome público (opcional)" />
                            <TextInput
                                id="display_name"
                                v-model="form.display_name"
                                type="text"
                                class="mt-1 block w-full"
                                :placeholder="tenant_name"
                                autocomplete="organization"
                            />
                            <InputError class="mt-2" :message="form.errors.display_name" />
                        </div>
                        <div>
                            <InputLabel for="tagline" value="Slogan / descrição curta (opcional)" />
                            <TextInput id="tagline" v-model="form.tagline" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.tagline" />
                        </div>
                        <div>
                            <InputLabel for="accent" value="Cor de destaque" />
                            <div class="mt-2 flex flex-wrap items-center gap-3">
                                <input
                                    id="accent"
                                    v-model="form.accent"
                                    type="color"
                                    class="h-10 w-16 cursor-pointer rounded border border-slate-200 bg-white p-1"
                                />
                                <TextInput v-model="form.accent" type="text" class="w-32 font-mono text-sm" />
                            </div>
                            <InputError class="mt-2" :message="form.errors.accent" />
                        </div>
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <PrimaryButton :disabled="form.processing">Continuar para equipa</PrimaryButton>
                            <button
                                type="button"
                                class="text-sm font-medium text-slate-500 underline-offset-2 hover:text-slate-800 hover:underline"
                                @click="submitSkip"
                            >
                                Saltar assistente
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Passo 2: Equipa -->
                <div v-else-if="currentStep === 2" class="mt-10 space-y-5">
                    <h2 class="text-lg font-semibold text-slate-900">Utilizadores e equipa</h2>
                    <p class="text-sm text-slate-600">
                        Convida colegas e define funções na área de utilizadores. O teu perfil já é administrador desta organização.
                    </p>
                    <Link
                        :href="links.users"
                        class="inline-flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-900 transition hover:border-indigo-300 hover:bg-indigo-100"
                    >
                        Abrir «Utilizadores»
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </Link>
                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <PrimaryButton type="button" @click="submitTeam">Continuar para permissões</PrimaryButton>
                        <button
                            type="button"
                            class="text-sm font-medium text-slate-500 underline-offset-2 hover:text-slate-800 hover:underline"
                            @click="submitSkip"
                        >
                            Saltar assistente
                        </button>
                    </div>
                </div>

                <!-- Passo 3: Permissões -->
                <div v-else class="mt-10 space-y-5">
                    <h2 class="text-lg font-semibold text-slate-900">Permissões e papéis</h2>
                    <p class="text-sm text-slate-600">
                        Os papéis «Administrador», «Básico» e «Operacional» já foram criados com permissões pré-definidas. Ajusta o que cada função pode fazer quando a tua equipa estiver definida.
                    </p>
                    <Link
                        :href="links.permissions"
                        class="inline-flex items-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-900 transition hover:border-violet-300 hover:bg-violet-100"
                    >
                        Abrir «Permissões»
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </Link>
                    <p class="text-xs text-slate-500">
                        Ao concluir, marcamos este passo na checklist do painel. Ainda podes rever a
                        <Link :href="links.subscription" class="font-semibold text-indigo-600 hover:text-indigo-800">subscrição</Link>
                        quando quiseres.
                    </p>
                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <PrimaryButton type="button" @click="submitComplete">Concluir configuração inicial</PrimaryButton>
                        <button
                            type="button"
                            class="text-sm font-medium text-slate-500 underline-offset-2 hover:text-slate-800 hover:underline"
                            @click="submitSkip"
                        >
                            Saltar assistente
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
