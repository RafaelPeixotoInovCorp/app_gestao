<script setup>
import Modal from '@/Components/Modal.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    summary: { type: Object, required: true },
    plans: { type: Array, required: true },
    ledger: { type: Array, default: () => [] },
    audit: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    currency: { type: String, default: 'EUR' },
});

const page = usePage();

function csrfTokenForForm() {
    const fromProps = page.props.csrf_token;
    if (typeof fromProps === 'string' && fromProps.trim() !== '') {
        return fromProps.trim();
    }
    if (typeof document !== 'undefined') {
        return document.head?.querySelector('meta[name="csrf-token"]')?.getAttribute('content')?.trim() ?? '';
    }
    return '';
}

/** @type {import('vue').Ref<null | 'period_end' | 'immediate'>} */
const confirmKind = ref(null);

const planForm = useForm({ plan_id: null });
planForm.transform((data) => {
    const token = csrfTokenForForm();
    return token ? { ...data, _token: token } : { ...data };
});

const cancelEndForm = useForm({ immediate: false });
cancelEndForm.transform((data) => {
    const token = csrfTokenForForm();
    return token ? { ...data, _token: token } : { ...data };
});

const cancelNowForm = useForm({ immediate: true });
cancelNowForm.transform((data) => {
    const token = csrfTokenForForm();
    return token ? { ...data, _token: token } : { ...data };
});

function selectPlan(planId) {
    if (!props.canManage) return;
    planForm.plan_id = planId;
    planForm.post(route('subscription.change-plan'), { preserveScroll: true });
}

const showConfirmModal = computed(() => confirmKind.value !== null);

function openConfirm(kind) {
    if (!props.canManage) return;
    if (kind === 'period_end' && props.summary.cancel_at_period_end) return;
    if (kind === 'period_end' && props.summary.pending_plan?.slug === 'basico') return;
    confirmKind.value = kind;
}

function closeConfirm() {
    if (cancelEndForm.processing || cancelNowForm.processing) return;
    confirmKind.value = null;
}

function submitConfirmedCancel() {
    const finish = () => {
        confirmKind.value = null;
    };

    if (confirmKind.value === 'period_end') {
        cancelEndForm.post(route('subscription.cancel'), {
            preserveScroll: true,
            onFinish: finish,
        });
        return;
    }

    if (confirmKind.value === 'immediate') {
        cancelNowForm.post(route('subscription.cancel'), {
            preserveScroll: true,
            onFinish: finish,
        });
    }
}

const isTrialing = computed(() => props.summary?.status === 'trialing');

const isFallbackBasicPlan = computed(
    () => props.summary?.plan?.slug === 'basico',
);

const confirmPeriodTitle = computed(() =>
    isTrialing.value ? 'Cancelar o trial no fim do período?' : 'Cancelar no fim do período?',
);

const confirmPeriodBody = computed(() => {
    if (isTrialing.value) {
        return `O trial termina a ${props.summary.trial_ends_at?.slice(0, 10) ?? 'data indicada'}. No fim desse período a subscrição passa automaticamente ao plano básico (gratuito). Continuarás com acesso até lá.`;
    }
    if (isFallbackBasicPlan.value) {
        return `O período atual termina a ${props.summary.current_period_end ?? '—'}. O acesso mantém-se até essa data; depois a subscrição termina. Não há cobrança extra neste cancelamento.`;
    }
    return `O período atual termina a ${props.summary.current_period_end ?? '—'}. Manténs o plano pago até essa data; no dia seguinte a renovação aplica-se automaticamente ao plano básico (sem nova cobrança de plano pago além do indicado no histórico de faturação).`;
});

const confirmImmediateBody = computed(() => {
    if (isFallbackBasicPlan.value) {
        return 'A subscrição termina de imediato e deixas de ter acesso aos módulos (exceto à página de subscrição). O valor de eventual crédito segue a percentagem configurada para cancelamento imediato.';
    }
    return 'O plano pago deixa de aplicar de imediato e a organização passa ao plano básico (com os respetivos limites e módulos). O valor de eventual crédito segue a percentagem configurada e aparece no histórico de faturação.';
});

const statusLabel = computed(() => {
    const s = props.summary?.status;
    if (s === 'trialing') return 'Em trial';
    if (s === 'active') return 'Ativa';
    if (s === 'ended') return 'Terminada';
    return s ?? '—';
});

function formatMoney(amount) {
    const n = Number(amount);
    return `${n.toFixed(2)} ${props.currency}`;
}

/** Fallback se o backend não enviar `entry_label` (dados antigos em cache). */
function ledgerEntryLabel(entryType) {
    const map = {
        upgrade_proration: 'Ajuste por upgrade (pró-rata)',
        downgrade_scheduled: 'Plano inferior a partir do próximo ciclo',
        downgrade_cycle_credit: 'Crédito na renovação (mudança de plano)',
        renewal_charge: 'Renovação do período',
        trial_conversion: 'Fim do trial — subscrição ativa',
        cancellation_policy: 'Cancelamento ou ajuste de período',
        immediate_cancel_refund: 'Crédito por cancelamento imediato',
    };
    return map[entryType] ?? 'Movimento de faturação';
}

function auditActionLabel(action) {
    const map = {
        trial_started: 'Trial iniciado',
        initial_provision: 'Subscrição inicial',
        upgrade: 'Upgrade',
        downgrade_scheduled: 'Downgrade agendado',
        downgrade_applied: 'Downgrade aplicado',
        downgrade_cleared: 'Downgrade cancelado',
        cancel_scheduled: 'Cancelamento agendado',
        cancel_immediate: 'Cancelamento imediato',
        cancel_to_basic: 'Passagem ao plano básico',
        period_renewed: 'Renovação',
        trial_converted: 'Trial convertido',
        subscription_ended: 'Subscrição terminada',
    };
    return map[action] ?? action;
}
</script>

<template>
    <Head title="Subscrição" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h2 class="text-xl font-semibold leading-tight text-slate-900">Subscrição e planos</h2>
                <p class="text-sm text-slate-600">
                    Limites, utilização, histórico de faturação e registo de alterações de plano.
                </p>
            </div>
        </template>

        <div class="py-8 sm:py-10">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-3">
                    <Card class="border-slate-200 shadow-sm">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-base">Estado</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-1 text-sm text-slate-700">
                            <p><span class="text-slate-500">Situação:</span> {{ statusLabel }}</p>
                            <p v-if="summary.plan">
                                <span class="text-slate-500">Plano:</span> {{ summary.plan.name }}
                            </p>
                            <p v-if="summary.days_trial_left != null">
                                <span class="text-slate-500">Trial:</span> faltam {{ summary.days_trial_left }} dia(s)
                            </p>
                            <p v-if="summary.pending_plan">
                                <span class="text-slate-500">{{
                                    summary.pending_plan.slug === 'basico' ? 'Plano básico a partir do próximo ciclo:' : 'Downgrade pendente:'
                                }}</span>
                                {{ summary.pending_plan.name }} (crédito estimado {{ formatMoney(summary.pending_cycle_credit) }})
                            </p>
                            <p v-if="summary.cancel_at_period_end" class="text-amber-700">
                                Cancelamento agendado para o fim do período ({{ summary.current_period_end }}).
                            </p>
                        </CardContent>
                    </Card>

                    <Card class="border-slate-200 shadow-sm">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-base">Utilizadores</CardTitle>
                        </CardHeader>
                        <CardContent class="text-sm text-slate-700">
                            <p class="text-2xl font-semibold text-slate-900">
                                <template v-if="summary.unlimited_users">Ilimitado</template>
                                <template v-else>
                                    {{ summary.users_count }} / {{ summary.max_users ?? '—' }}
                                </template>
                            </p>
                            <p class="mt-1 text-xs text-slate-500">Membros associados à organização atual.</p>
                        </CardContent>
                    </Card>

                    <Card class="border-slate-200 shadow-sm">
                        <CardHeader class="pb-2">
                            <CardTitle class="text-base">Período de faturação</CardTitle>
                        </CardHeader>
                        <CardContent class="text-sm text-slate-700">
                            <p v-if="summary.current_period_start && summary.current_period_end">
                                {{ summary.current_period_start }} → {{ summary.current_period_end }}
                            </p>
                            <p v-else class="text-slate-500">—</p>
                            <p v-if="summary.plan" class="mt-2 text-xs text-slate-500">
                                Preço de referência mensal: {{ formatMoney(summary.plan.price_per_month) }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div
                    v-if="canManage && summary.status !== 'ended'"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-900/5"
                >
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Cancelamento</h3>
                            <p class="mt-1 max-w-xl text-xs text-slate-500">
                                Em planos pagos, cancelar no fim do período agenda a passagem ao plano básico; cancelar já aplica o básico de imediato. No plano
                                básico, cancelar significa terminar a subscrição. Em ambos os casos confirmas numa janela antes de aplicar.
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            size="default"
                            :disabled="
                                cancelEndForm.processing || summary.cancel_at_period_end || summary.pending_plan?.slug === 'basico'
                            "
                            class="h-9 gap-2 border-slate-300 bg-white px-4 text-slate-800 shadow-sm hover:bg-slate-50 hover:text-slate-900 disabled:opacity-50"
                            :title="
                                summary.cancel_at_period_end
                                    ? 'Já existe cancelamento agendado.'
                                    : summary.pending_plan?.slug === 'basico'
                                      ? 'Já está agendada a passagem ao plano básico.'
                                      : ''
                            "
                            @click="openConfirm('period_end')"
                        >
                            <svg class="size-4 shrink-0 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ isTrialing ? 'Terminar trial no fim do período' : 'Cancelar no fim do período' }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="default"
                            :disabled="cancelNowForm.processing"
                            class="h-9 gap-2 border border-red-700/90 bg-red-600 px-4 text-white shadow-md hover:bg-red-700 hover:brightness-100 disabled:opacity-50"
                            @click="openConfirm('immediate')"
                        >
                            <svg class="size-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Cancelar imediatamente
                        </Button>
                    </div>
                </div>

                <Modal :show="showConfirmModal" max-width="md" @close="closeConfirm">
                    <div class="p-6 sm:p-8">
                        <div class="flex gap-3">
                            <div
                                v-if="confirmKind === 'immediate'"
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-700"
                            >
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div
                                v-else
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800"
                            >
                                <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-lg font-semibold text-slate-900">
                                    <template v-if="confirmKind === 'period_end'">{{ confirmPeriodTitle }}</template>
                                    <template v-else-if="confirmKind === 'immediate'">Cancelar imediatamente?</template>
                                </h3>
                                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                                    <template v-if="confirmKind === 'period_end'">{{ confirmPeriodBody }}</template>
                                    <template v-else-if="confirmKind === 'immediate'">{{ confirmImmediateBody }}</template>
                                </p>
                            </div>
                        </div>
                        <div class="mt-8 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button type="button" variant="outline" class="sm:min-w-[7rem]" :disabled="cancelEndForm.processing || cancelNowForm.processing" @click="closeConfirm">
                                Voltar
                            </Button>
                            <Button
                                v-if="confirmKind === 'period_end'"
                                type="button"
                                class="sm:min-w-[7rem] border-slate-800 bg-slate-900 text-white hover:bg-slate-800"
                                :disabled="cancelEndForm.processing"
                                @click="submitConfirmedCancel"
                            >
                                {{ cancelEndForm.processing ? 'A processar…' : 'Confirmar agendamento' }}
                            </Button>
                            <Button
                                v-else-if="confirmKind === 'immediate'"
                                type="button"
                                class="sm:min-w-[7rem] border border-red-800 bg-red-600 text-white hover:bg-red-700"
                                :disabled="cancelNowForm.processing"
                                @click="submitConfirmedCancel"
                            >
                                {{ cancelNowForm.processing ? 'A processar…' : 'Sim, terminar já' }}
                            </Button>
                        </div>
                    </div>
                </Modal>

                <div v-if="!canManage" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Apenas administradores da organização podem alterar o plano ou cancelar.
                </div>

                <div>
                    <h3 class="mb-3 text-lg font-semibold text-slate-900">Planos disponíveis</h3>
                    <div class="grid gap-4 md:grid-cols-3">
                        <Card
                            v-for="p in plans"
                            :key="p.id"
                            class="border-slate-200 shadow-sm transition hover:border-indigo-300"
                            :class="{ 'ring-2 ring-indigo-500': summary.plan?.id === p.id }"
                        >
                            <CardHeader class="pb-2">
                                <CardTitle class="text-base">{{ p.name }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3 text-sm text-slate-600">
                                <p>{{ p.description }}</p>
                                <ul class="list-inside list-disc space-y-1 text-xs">
                                    <li v-if="p.max_users == null">Utilizadores ilimitados</li>
                                    <li v-else>Até {{ p.max_users }} utilizadores</li>
                                    <li>{{ p.has_premium_modules ? 'Inclui módulos premium' : 'Sem módulos financeiros premium' }}</li>
                                    <li v-if="p.trial_days">Trial: {{ p.trial_days }} dias (novas subscrições)</li>
                                </ul>
                                <p class="font-medium text-slate-900">{{ formatMoney(p.price_per_month) }} / mês</p>
                                <Button
                                    v-if="canManage"
                                    size="sm"
                                    class="w-full"
                                    :disabled="
                                        planForm.processing ||
                                        (summary.status !== 'ended' &&
                                            summary.plan?.id === p.id &&
                                            !summary.pending_plan)
                                    "
                                    @click="selectPlan(p.id)"
                                >
                                    {{
                                        summary.status === 'ended'
                                            ? 'Reativar'
                                            : summary.plan?.id === p.id && !summary.pending_plan
                                              ? 'Plano atual'
                                              : 'Escolher'
                                    }}
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <Card class="border-slate-200 shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-base">Histórico de faturação</CardTitle>
                            <CardDescription>Movimentos do período de subscrição (cobranças, créditos e ajustes).</CardDescription>
                        </CardHeader>
                        <CardContent class="max-h-80 overflow-auto text-sm">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b text-slate-500">
                                        <th class="py-2 pr-2">Data</th>
                                        <th class="py-2 pr-2">Movimento</th>
                                        <th class="py-2 text-end">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in ledger" :key="row.id" class="border-b border-slate-100">
                                        <td class="py-2 pr-2 text-slate-600 whitespace-nowrap">{{ row.created_at?.slice(0, 10) }}</td>
                                        <td class="py-2 pr-2">
                                            <div class="font-medium text-slate-800">{{ row.entry_label ?? ledgerEntryLabel(row.entry_type) }}</div>
                                            <div v-if="row.description" class="mt-0.5 text-slate-500">{{ row.description }}</div>
                                        </td>
                                        <td class="py-2 text-end font-mono whitespace-nowrap">{{ formatMoney(row.amount) }}</td>
                                    </tr>
                                    <tr v-if="!ledger.length">
                                        <td colspan="3" class="py-4 text-center text-slate-500">Sem movimentos.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <Card class="border-slate-200 shadow-sm">
                        <CardHeader>
                            <CardTitle class="text-base">Auditoria de alterações de plano</CardTitle>
                        </CardHeader>
                        <CardContent class="max-h-80 overflow-auto text-sm">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="border-b text-slate-500">
                                        <th class="py-2 pr-2">Data</th>
                                        <th class="py-2 pr-2">Ação</th>
                                        <th class="py-2">Quem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in audit" :key="row.id" class="border-b border-slate-100">
                                        <td class="py-2 pr-2 text-slate-600">{{ row.created_at?.slice(0, 10) }}</td>
                                        <td class="py-2 pr-2 font-medium text-slate-800">{{ auditActionLabel(row.action) }}</td>
                                        <td class="py-2 text-slate-600">{{ row.actor?.name ?? 'Sistema' }}</td>
                                    </tr>
                                    <tr v-if="!audit.length">
                                        <td colspan="3" class="py-4 text-center text-slate-500">Sem registos.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
