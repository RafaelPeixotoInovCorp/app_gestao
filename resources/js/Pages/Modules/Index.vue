<script setup>
import TenantAppendModal from '@/Components/TenantAppendModal.vue';
import TenantSwitcher from '@/Components/TenantSwitcher.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();

const subscription = computed(() => page.props.subscription ?? null);
const tenantNav = computed(() => page.props.tenantNavigation ?? { current: null, items: [], canCreateAdditional: false });
const tenantOnboarding = computed(() => page.props.tenantOnboarding ?? null);

const appendTenantOpen = ref(false);

const onboardingCardVisible = computed(() => {
    const o = tenantOnboarding.value;
    if (!o) return false;
    return !o.wizard_completed || o.checklist_done < o.checklist_total;
});

const checklistMeta = {
    branding: { label: 'Identidade e marca', hint: 'Assistente ou definições de marca' },
    team: { label: 'Equipa e utilizadores', hint: 'Convites e funções' },
    permissions: { label: 'Permissões', hint: 'Papéis e módulos' },
    subscription: { label: 'Subscrição', hint: 'Plano e limites' },
};

function patchChecklist(key, done) {
    router.patch(route('tenants.setup.checklist'), { key, done }, { preserveScroll: true });
}

defineProps({
    stats: {
        type: Object,
        required: true,
    },
    audit_preview: {
        type: Array,
        default: () => [],
    },
});

const userLimitLabel = computed(() => {
    const s = subscription.value;
    if (!s?.has_subscription) return '—';
    if (s.unlimited_users) return 'Limite: ∞';
    if (s.max_users == null) return 'Limite: ∞';
    return `Limite: ${s.max_users}`;
});

function auditLabel(action) {
    const map = {
        trial_started: 'Trial iniciado',
        initial_provision: 'Subscrição inicial',
        cancel_to_basic: 'Passagem ao plano básico',
        upgrade: 'Upgrade de plano',
        downgrade_scheduled: 'Downgrade agendado',
        downgrade_applied: 'Downgrade aplicado',
        downgrade_cleared: 'Downgrade cancelado',
        cancel_scheduled: 'Cancelamento agendado',
        cancel_immediate: 'Cancelamento imediato',
        period_renewed: 'Renovação',
        trial_converted: 'Trial convertido',
        subscription_ended: 'Subscrição terminada',
    };
    return map[action] ?? action;
}
</script>

<template>
    <Head title="Painel" />
    <AuthenticatedLayout>
        <div class="p-4 sm:p-6 lg:p-8">
            <div class="mx-auto max-w-6xl space-y-6">
                <!-- Hero -->
                <div
                    class="overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 p-6 text-white shadow-lg shadow-indigo-500/25 sm:p-8"
                >
                    <p class="text-sm font-medium text-white/80">Dashboard</p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">
                        {{ tenantOnboarding?.public_display_name ?? tenantNav.current?.name ?? 'Painel' }}
                    </h1>
                    <p class="mt-2 max-w-xl text-sm text-white/85">
                        Para mudar de organização, utilize a secção «Organização» abaixo. Os módulos estão no menu lateral.
                    </p>
                </div>

                <!-- Primeira configuração: wizard + checklist -->
                <Card
                    v-if="onboardingCardVisible"
                    class="overflow-visible border-indigo-200/80 bg-gradient-to-br from-indigo-50/40 to-white shadow-sm ring-1 ring-indigo-100/60"
                >
                    <CardHeader class="pb-2">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <CardTitle class="text-lg font-semibold text-slate-900">Primeira configuração</CardTitle>
                                <p class="mt-1 text-sm text-slate-600">
                                    Registo do tenant com subscrição e permissões base já concluídos. Completa o assistente e marca os itens abaixo quando estiveres pronto.
                                </p>
                            </div>
                            <div v-if="tenantOnboarding && !tenantOnboarding.wizard_completed" class="shrink-0">
                                <Button as-child size="sm" class="rounded-lg bg-indigo-600 font-semibold hover:bg-indigo-700">
                                    <Link :href="tenantOnboarding.setup_wizard_url">Abrir assistente</Link>
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div v-if="tenantOnboarding && !tenantOnboarding.wizard_completed" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            O assistente de identidade, equipa e permissões ainda não foi concluído.
                            <Link :href="tenantOnboarding.setup_wizard_url" class="font-semibold text-amber-950 underline underline-offset-2">Continuar</Link>
                            ou
                            <Link :href="`${tenantOnboarding.setup_wizard_url}?reopen=1`" class="font-semibold text-amber-950 underline underline-offset-2">rever passos</Link>.
                        </div>
                        <div v-if="tenantOnboarding" class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Checklist</p>
                            <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white">
                                <li
                                    v-for="key in ['branding', 'team', 'permissions', 'subscription']"
                                    :key="key"
                                    class="flex flex-wrap items-center justify-between gap-3 px-3 py-3 sm:px-4"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-900">{{ checklistMeta[key]?.label ?? key }}</p>
                                        <p class="text-xs text-slate-500">{{ checklistMeta[key]?.hint }}</p>
                                    </div>
                                    <label class="flex shrink-0 cursor-pointer items-center gap-2 text-sm text-slate-700">
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                            :checked="!!tenantOnboarding.checklist[key]"
                                            @change="patchChecklist(key, $event.target.checked)"
                                        />
                                        <span class="hidden sm:inline">Concluído</span>
                                    </label>
                                </li>
                            </ul>
                            <p class="text-xs text-slate-500">
                                Progresso: {{ tenantOnboarding.checklist_done }} / {{ tenantOnboarding.checklist_total }} ·
                                <Link
                                    v-if="subscription?.has_subscription"
                                    :href="route('subscription.dashboard')"
                                    class="font-semibold text-indigo-600 hover:text-indigo-800"
                                >Subscrição</Link>
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Métricas -->
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Card class="border-slate-200/80 shadow-sm">
                        <CardContent class="flex items-center gap-4 p-5">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sky-700">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-slate-500">Clientes</p>
                                <p class="text-2xl font-bold text-slate-900">{{ stats.clients }}</p>
                                <p class="text-xs text-slate-400">Total registado</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="border-slate-200/80 shadow-sm">
                        <CardContent class="flex items-center gap-4 p-5">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-slate-500">Fornecedores</p>
                                <p class="text-2xl font-bold text-slate-900">{{ stats.suppliers }}</p>
                                <p class="text-xs text-slate-400">Total registado</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="border-slate-200/80 shadow-sm">
                        <CardContent class="flex items-center gap-4 p-5">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-slate-500">Utilizadores</p>
                                <p class="text-2xl font-bold text-slate-900">{{ stats.users }}</p>
                                <p class="text-xs text-slate-400">{{ userLimitLabel }}</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card class="border-slate-200/80 shadow-sm">
                        <CardContent class="flex items-center gap-4 p-5">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-sm font-medium text-slate-500">Organizações</p>
                                <p class="text-2xl font-bold text-slate-900">{{ stats.organizations }}</p>
                                <p class="text-xs text-slate-400">Em que participa</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Organização: overflow-visible para o dropdown não ser cortado pelo Card (overflow-hidden por defeito) -->
                <Card class="overflow-visible border-slate-200/80 shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-4">
                        <CardTitle class="text-lg font-semibold text-slate-900">Organização</CardTitle>
                        <Button
                            v-if="tenantNav.canCreateAdditional"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="h-9 gap-2 rounded-xl border-indigo-200/90 bg-white px-3.5 font-semibold text-indigo-800 shadow-sm ring-1 ring-indigo-100/80 transition hover:border-indigo-300 hover:bg-gradient-to-br hover:from-indigo-50/90 hover:to-violet-50/50 hover:text-indigo-900 hover:shadow-md"
                            @click="appendTenantOpen = true"
                        >
                            <span
                                class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-sm shadow-indigo-600/25"
                                aria-hidden="true"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                            </span>
                            Nova organização
                        </Button>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <TenantSwitcher variant="dashboard" />
                        <p v-if="tenantNav.items?.length" class="text-xs text-slate-500">
                            Abre o seletor para mudar de contexto: os dados e as permissões seguem a organização escolhida.
                        </p>
                        <p v-else class="text-sm text-slate-500">Sem organizações.</p>
                    </CardContent>
                </Card>

                <!-- Utilização do plano -->
                <Card class="border-slate-200/80 shadow-sm">
                    <CardHeader class="pb-2">
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                                </svg>
                            </span>
                            <CardTitle class="text-lg font-semibold text-slate-900">Utilização do plano</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm text-slate-600">
                        <template v-if="subscription?.has_subscription">
                            <p>
                                <span class="font-medium text-slate-800">Plano:</span>
                                {{ subscription.plan?.name ?? '—' }}
                            </p>
                            <p v-if="!subscription.unlimited_users && subscription.max_users != null">
                                <span class="font-medium text-slate-800">Utilizadores:</span>
                                {{ subscription.users_count }} / {{ subscription.max_users }}
                            </p>
                            <p v-else>
                                <span class="font-medium text-slate-800">Utilizadores:</span>
                                {{ subscription.users_count }} (ilimitado neste plano)
                            </p>
                            <p v-if="subscription.pending_plan" class="rounded-lg bg-amber-50 px-3 py-2 text-amber-900">
                                Downgrade agendado para <strong>{{ subscription.pending_plan.name }}</strong>.
                            </p>
                            <Link
                                :href="route('subscription.dashboard')"
                                class="inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-800"
                            >Ver subscrição completa</Link>
                        </template>
                        <p v-else class="text-slate-500">Sem dados de subscrição.</p>
                    </CardContent>
                </Card>

                <!-- Histórico -->
                <Card class="border-slate-200/80 shadow-sm">
                    <CardHeader class="pb-2">
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </span>
                            <CardTitle class="text-lg font-semibold text-slate-900">Histórico de alterações</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <ul v-if="audit_preview?.length" class="space-y-2 text-sm">
                            <li
                                v-for="row in audit_preview"
                                :key="row.id"
                                class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-50 pb-2 last:border-0"
                            >
                                <span class="text-slate-800">{{ auditLabel(row.action) }}</span>
                                <span class="text-xs text-slate-500">{{ row.actor_name ?? 'Sistema' }} · {{ row.created_at?.slice(0, 10) }}</span>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-slate-500">Nenhuma alteração registada.</p>
                        <Link
                            v-if="audit_preview?.length"
                            :href="route('subscription.dashboard')"
                            class="mt-3 inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-800"
                        >Ver auditoria completa</Link>
                    </CardContent>
                </Card>

                <div
                    v-if="subscription && subscription.has_subscription && !subscription.has_access"
                    class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
                >
                    <p class="font-medium">Subscrição inativa</p>
                    <p class="mt-1 text-amber-800">
                        Escolha um plano para voltar a utilizar os módulos.
                    </p>
                    <Link
                        :href="route('subscription.dashboard')"
                        class="mt-3 inline-flex font-semibold text-indigo-700 underline underline-offset-2 hover:text-indigo-900"
                    >Ir para subscrição</Link>
                </div>
            </div>
        </div>
        <TenantAppendModal v-if="tenantNav.canCreateAdditional" v-model="appendTenantOpen" />
    </AuthenticatedLayout>
</template>
