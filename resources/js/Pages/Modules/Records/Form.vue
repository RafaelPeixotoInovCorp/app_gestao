<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    module: { type: Object, required: true },
    record: { type: Object, default: null },
    relatedEntities: { type: Array, default: () => [] },
    requireEntitySelection: { type: Boolean, default: false },
});

const isEdit = computed(() => props.record !== null);

const isProposals = computed(() => props.module.slug === 'proposals');

const isOrders = computed(() =>
    ['customer-orders', 'supplier-orders'].includes(props.module.slug),
);

const isCustomerLedger = computed(() => props.module.slug === 'financial-customer-ledger');

const isBankAccounts = computed(() => props.module.slug === 'financial-bank-accounts');

const usesCommercialFields = computed(() => isProposals.value || isOrders.value);

const proposalStatuses = [
    { value: 'draft', label: 'Rascunho' },
    { value: 'sent', label: 'Enviada' },
    { value: 'pending', label: 'Pendente' },
    { value: 'accepted', label: 'Aceite' },
    { value: 'rejected', label: 'Recusada' },
    { value: 'expired', label: 'Expirada' },
];

const orderStatuses = [
    { value: 'draft', label: 'Rascunho' },
    { value: 'confirmed', label: 'Confirmada' },
    { value: 'processing', label: 'Em preparação' },
    { value: 'shipped', label: 'Expedida' },
    { value: 'delivered', label: 'Entregue' },
    { value: 'cancelled', label: 'Cancelada' },
];

function recordDateField(val) {
    if (!val) {
        return '';
    }
    return String(val).slice(0, 10);
}

function defaultCommercialDate() {
    return new Date().toISOString().slice(0, 10);
}

const form = useForm({
    entity_id: props.record?.entity_id != null ? String(props.record.entity_id) : '',
    title: props.record?.title ?? '',
    description: props.record?.description ?? '',
    active: props.record?.active ?? true,
    proposal_number: props.record?.proposal_number ?? '',
    proposal_date:
        recordDateField(props.record?.proposal_date) ||
        (props.module.slug === 'proposals' ? defaultCommercialDate() : ''),
    valid_until: recordDateField(props.record?.valid_until),
    proposal_amount:
        props.record?.proposal_amount != null && props.record?.proposal_amount !== ''
            ? String(props.record.proposal_amount)
            : '',
    proposal_status: props.record?.proposal_status ?? 'draft',
    order_number: props.record?.order_number ?? '',
    order_date:
        recordDateField(props.record?.order_date) ||
        (isOrders.value && !props.record ? defaultCommercialDate() : ''),
    order_valid_until: recordDateField(props.record?.order_valid_until),
    order_amount:
        props.record?.order_amount != null && props.record?.order_amount !== ''
            ? String(props.record.order_amount)
            : '',
    order_status: props.record?.order_status ?? 'draft',
    ledger_entry_date:
        recordDateField(props.record?.ledger_entry_date) ||
        (isCustomerLedger.value && !props.record ? defaultCommercialDate() : ''),
    ledger_debit:
        props.record?.ledger_debit != null && props.record?.ledger_debit !== ''
            ? String(props.record.ledger_debit)
            : '',
    ledger_credit:
        props.record?.ledger_credit != null && props.record?.ledger_credit !== ''
            ? String(props.record.ledger_credit)
            : '',
    bank_name: props.record?.bank_name ?? '',
    iban: props.record?.iban ?? '',
    swift: props.record?.swift ?? '',
});

function mapPayload(data) {
    const base = {
        entity_id: data.entity_id === '' ? null : Number(data.entity_id),
        title: data.title,
        description: data.description,
        active: data.active,
    };
    if (isCustomerLedger.value) {
        return {
            ...base,
            title: '',
            description: data.description,
            ledger_entry_date: data.ledger_entry_date,
            ledger_debit:
                data.ledger_debit === '' || data.ledger_debit === null ? null : Number(data.ledger_debit),
            ledger_credit:
                data.ledger_credit === '' || data.ledger_credit === null ? null : Number(data.ledger_credit),
        };
    }
    if (isBankAccounts.value) {
        return {
            ...base,
            title: '',
            description: data.description || null,
            bank_name: data.bank_name,
            iban: data.iban,
            swift: data.swift === '' || data.swift === null ? null : data.swift,
        };
    }
    if (isProposals.value) {
        return {
            ...base,
            proposal_number: data.proposal_number,
            proposal_date: data.proposal_date,
            valid_until: data.valid_until === '' ? null : data.valid_until,
            proposal_amount:
                data.proposal_amount === '' || data.proposal_amount === null ? null : Number(data.proposal_amount),
            proposal_status: data.proposal_status,
        };
    }
    if (isOrders.value) {
        return {
            ...base,
            order_number: data.order_number,
            order_date: data.order_date,
            order_valid_until: data.order_valid_until === '' ? null : data.order_valid_until,
            order_amount:
                data.order_amount === '' || data.order_amount === null ? null : Number(data.order_amount),
            order_status: data.order_status,
        };
    }

    return base;
}

function submit() {
    if (isEdit.value) {
        form.transform(mapPayload).put(route('modules.records.update', { slug: props.module.slug, record: props.record.id }));
        return;
    }

    form.transform(mapPayload).post(route('modules.records.store', props.module.slug));
}
</script>

<template>
    <Head :title="isEdit ? `Editar - ${module.label}` : `Novo - ${module.label}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-foreground">
                    {{ isEdit ? `Editar registo (${module.label})` : `Novo registo (${module.label})` }}
                </h2>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="route('modules.show', module.slug)">Voltar</Link>
                </Button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit">
                    <Card>
                        <CardHeader>
                            <CardTitle>Dados do registo</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div v-if="requireEntitySelection" class="space-y-2">
                                <Label for="entity_id">
                                    {{ module.slug === 'supplier-orders' ? 'Fornecedor' : 'Cliente' }}
                                </Label>
                                <select
                                    id="entity_id"
                                    v-model="form.entity_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="">Escolha…</option>
                                    <option v-for="entity in relatedEntities" :key="entity.id" :value="String(entity.id)">
                                        {{ entity.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.entity_id" class="text-sm text-destructive">
                                    {{ form.errors.entity_id }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Campo obrigatório para este módulo.
                                </p>
                            </div>

                            <template v-if="isCustomerLedger">
                                <div class="space-y-2">
                                    <Label for="ledger_entry_date">Data do movimento</Label>
                                    <Input id="ledger_entry_date" v-model="form.ledger_entry_date" type="date" />
                                    <p v-if="form.errors.ledger_entry_date" class="text-sm text-destructive">
                                        {{ form.errors.ledger_entry_date }}
                                    </p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="ledger_debit">Débito (€)</Label>
                                        <Input
                                            id="ledger_debit"
                                            v-model="form.ledger_debit"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            inputmode="decimal"
                                            placeholder="0,00"
                                            autocomplete="off"
                                        />
                                        <p v-if="form.errors.ledger_debit" class="text-sm text-destructive">
                                            {{ form.errors.ledger_debit }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="ledger_credit">Crédito (€)</Label>
                                        <Input
                                            id="ledger_credit"
                                            v-model="form.ledger_credit"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            inputmode="decimal"
                                            placeholder="0,00"
                                            autocomplete="off"
                                        />
                                        <p v-if="form.errors.ledger_credit" class="text-sm text-destructive">
                                            {{ form.errors.ledger_credit }}
                                        </p>
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Preencha só débito ou só crédito (valor maior que zero).
                                </p>

                                <div class="space-y-2">
                                    <Label for="description">Descrição</Label>
                                    <Textarea id="description" v-model="form.description" rows="4" />
                                    <p v-if="form.errors.description" class="text-sm text-destructive">
                                        {{ form.errors.description }}
                                    </p>
                                </div>
                            </template>

                            <template v-else-if="isBankAccounts">
                                <div class="space-y-2">
                                    <Label for="bank_name">Banco</Label>
                                    <Input id="bank_name" v-model="form.bank_name" autocomplete="organization" />
                                    <p v-if="form.errors.bank_name" class="text-sm text-destructive">
                                        {{ form.errors.bank_name }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="iban">IBAN</Label>
                                    <Input
                                        id="iban"
                                        v-model="form.iban"
                                        class="font-mono"
                                        autocomplete="off"
                                        placeholder="PT50 …"
                                    />
                                    <p v-if="form.errors.iban" class="text-sm text-destructive">{{ form.errors.iban }}</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="swift">SWIFT / BIC</Label>
                                    <Input
                                        id="swift"
                                        v-model="form.swift"
                                        class="font-mono uppercase"
                                        maxlength="11"
                                        autocomplete="off"
                                        placeholder="Opcional"
                                    />
                                    <p v-if="form.errors.swift" class="text-sm text-destructive">{{ form.errors.swift }}</p>
                                </div>
                            </template>

                            <template v-else-if="isProposals">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="proposal_number">Número da proposta</Label>
                                        <Input id="proposal_number" v-model="form.proposal_number" autocomplete="off" />
                                        <p v-if="form.errors.proposal_number" class="text-sm text-destructive">
                                            {{ form.errors.proposal_number }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="proposal_status">Estado</Label>
                                        <select
                                            id="proposal_status"
                                            v-model="form.proposal_status"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <option v-for="s in proposalStatuses" :key="s.value" :value="s.value">
                                                {{ s.label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.proposal_status" class="text-sm text-destructive">
                                            {{ form.errors.proposal_status }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="proposal_date">Data da proposta</Label>
                                        <Input id="proposal_date" v-model="form.proposal_date" type="date" />
                                        <p v-if="form.errors.proposal_date" class="text-sm text-destructive">
                                            {{ form.errors.proposal_date }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="valid_until">Validade</Label>
                                        <Input id="valid_until" v-model="form.valid_until" type="date" />
                                        <p v-if="form.errors.valid_until" class="text-sm text-destructive">
                                            {{ form.errors.valid_until }}
                                        </p>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="proposal_amount">Valor (€)</Label>
                                    <Input
                                        id="proposal_amount"
                                        v-model="form.proposal_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        inputmode="decimal"
                                        placeholder="0,00"
                                    />
                                    <p v-if="form.errors.proposal_amount" class="text-sm text-destructive">
                                        {{ form.errors.proposal_amount }}
                                    </p>
                                </div>
                            </template>

                            <template v-else-if="isOrders">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="order_number">Número da encomenda</Label>
                                        <Input id="order_number" v-model="form.order_number" autocomplete="off" />
                                        <p v-if="form.errors.order_number" class="text-sm text-destructive">
                                            {{ form.errors.order_number }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="order_status">Estado</Label>
                                        <select
                                            id="order_status"
                                            v-model="form.order_status"
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <option v-for="s in orderStatuses" :key="s.value" :value="s.value">
                                                {{ s.label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors.order_status" class="text-sm text-destructive">
                                            {{ form.errors.order_status }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="order_date">Data da encomenda</Label>
                                        <Input id="order_date" v-model="form.order_date" type="date" />
                                        <p v-if="form.errors.order_date" class="text-sm text-destructive">
                                            {{ form.errors.order_date }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="order_valid_until">Validade</Label>
                                        <Input id="order_valid_until" v-model="form.order_valid_until" type="date" />
                                        <p v-if="form.errors.order_valid_until" class="text-sm text-destructive">
                                            {{ form.errors.order_valid_until }}
                                        </p>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="order_amount">Valor (€)</Label>
                                    <Input
                                        id="order_amount"
                                        v-model="form.order_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        inputmode="decimal"
                                        placeholder="0,00"
                                    />
                                    <p v-if="form.errors.order_amount" class="text-sm text-destructive">
                                        {{ form.errors.order_amount }}
                                    </p>
                                </div>
                            </template>

                            <template v-if="!isCustomerLedger && !isBankAccounts">
                                <div class="space-y-2">
                                    <Label for="title">{{ usesCommercialFields ? 'Assunto / objecto' : 'Título' }}</Label>
                                    <Input id="title" v-model="form.title" />
                                    <p v-if="form.errors.title" class="text-sm text-destructive">
                                        {{ form.errors.title }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Descrição</Label>
                                    <Textarea id="description" v-model="form.description" rows="4" />
                                    <p v-if="form.errors.description" class="text-sm text-destructive">
                                        {{ form.errors.description }}
                                    </p>
                                </div>
                            </template>

                            <div class="flex items-center gap-3">
                                <Switch id="active" v-model:checked="form.active" />
                                <Label for="active">{{ isBankAccounts ? 'Conta ativa' : 'Registo ativo' }}</Label>
                            </div>
                        </CardContent>
                        <CardFooter class="flex justify-end gap-2 border-t border-border pt-6">
                            <Button variant="outline" type="button" as-child>
                                <Link :href="route('modules.show', module.slug)">Cancelar</Link>
                            </Button>
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'A guardar…' : 'Guardar' }}
                            </Button>
                        </CardFooter>
                    </Card>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
