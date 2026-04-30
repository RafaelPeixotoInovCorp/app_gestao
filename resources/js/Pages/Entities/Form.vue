<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps({
    entity: { type: Object, default: null },
    countries: { type: Array, default: () => [] },
    defaultKind: { type: String, default: 'client' },
});

const page = usePage();
const isEdit = computed(() => props.entity !== null);

const gdprSelect = ref(
    props.entity?.gdpr_consent === true ? 'yes' : props.entity?.gdpr_consent === false ? 'no' : '',
);

const form = useForm({
    is_client: props.entity?.is_client ?? props.defaultKind === 'client',
    is_supplier: props.entity?.is_supplier ?? props.defaultKind === 'supplier',
    nif: props.entity?.nif ?? '',
    name: props.entity?.name ?? '',
    address: props.entity?.address ?? '',
    postal_code: props.entity?.postal_code ?? '',
    city: props.entity?.city ?? '',
    country_id:
        props.entity?.country_id != null && props.entity?.country_id !== ''
            ? String(props.entity.country_id)
            : '',
    phone: props.entity?.phone ?? '',
    mobile: props.entity?.mobile ?? '',
    website: props.entity?.website ?? '',
    email: props.entity?.email ?? '',
    gdpr_consent: props.entity?.gdpr_consent ?? null,
    notes: props.entity?.notes ?? '',
    active: props.entity?.active ?? true,
});

watch(gdprSelect, (v) => {
    if (v === 'yes') {
        form.gdpr_consent = true;
    } else if (v === 'no') {
        form.gdpr_consent = false;
    } else {
        form.gdpr_consent = null;
    }
});

watch(
    () => page.props.flash?.success,
    (msg) => {
        if (msg) {
            toast.success(msg);
        }
    },
);

const viesLoading = ref(false);

async function lookupVies() {
    if (!/^\d{9}$/.test(String(form.nif))) {
        toast.error('Introduza um NIF português válido (9 dígitos) antes de consultar o VIES.');
        return;
    }
    viesLoading.value = true;
    try {
        const { data } = await axios.post(route('entities.vies'), {
            country: 'PT',
            vat: form.nif,
        });
        if (data.valid) {
            if (data.name) {
                form.name = data.name;
            }
            if (data.address) {
                form.address = String(data.address).replace(/\r?\n+/g, '\n').trim();
            }
            toast.success('Dados obtidos via VIES.');
        } else {
            toast.error(data.fault || 'NIF não validado no VIES.');
        }
    } catch {
        toast.error('Não foi possível consultar o VIES.');
    } finally {
        viesLoading.value = false;
    }
}

function submit() {
    const mapPayload = (data) => ({
        ...data,
        country_id:
            data.country_id === '' || data.country_id === null ? null : Number(data.country_id),
    });

    if (isEdit.value) {
        form.transform(mapPayload).put(route('entities.update', props.entity.id));
    } else {
        form.transform(mapPayload).post(route('entities.store'));
    }
}

const backSlug = computed(() =>
    form.is_client && !form.is_supplier
        ? 'clients'
        : form.is_supplier && !form.is_client
          ? 'suppliers'
          : props.defaultKind === 'supplier'
            ? 'suppliers'
            : 'clients',
);
</script>

<template>
    <Head :title="isEdit ? 'Editar entidade' : 'Nova entidade'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-foreground">
                    {{ isEdit ? 'Editar entidade' : 'Nova entidade' }}
                </h2>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="route('modules.show', backSlug)">Voltar à lista</Link>
                </Button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit">
                    <Card>
                        <CardHeader>
                            <CardTitle>Dados da entidade</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div class="flex flex-1 flex-col gap-2">
                                    <Label for="nif">NIF</Label>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <Input
                                            id="nif"
                                            v-model="form.nif"
                                            maxlength="9"
                                            inputmode="numeric"
                                            autocomplete="off"
                                            class="sm:max-w-xs"
                                        />
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            :disabled="viesLoading"
                                            @click="lookupVies"
                                        >
                                            {{ viesLoading ? 'A consultar…' : 'Validar no VIES' }}
                                        </Button>
                                    </div>
                                    <p v-if="form.errors.nif" class="text-sm text-destructive">
                                        {{ form.errors.nif }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <Switch id="is_client" v-model:checked="form.is_client" />
                                    <Label for="is_client">Cliente</Label>
                                </div>
                                <div class="flex items-center gap-3">
                                    <Switch id="is_supplier" v-model:checked="form.is_supplier" />
                                    <Label for="is_supplier">Fornecedor</Label>
                                </div>
                            </div>
                            <p
                                v-if="form.errors.is_client || form.errors.is_supplier"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.is_client || form.errors.is_supplier }}
                            </p>

                            <div class="space-y-2">
                                <Label for="name">Nome</Label>
                                <Input id="name" v-model="form.name" />
                                <p v-if="form.errors.name" class="text-sm text-destructive">
                                    {{ form.errors.name }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="address">Morada</Label>
                                <Textarea id="address" v-model="form.address" rows="3" />
                                <p v-if="form.errors.address" class="text-sm text-destructive">
                                    {{ form.errors.address }}
                                </p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="postal_code">Código postal</Label>
                                    <Input
                                        id="postal_code"
                                        v-model="form.postal_code"
                                        placeholder="0000-000"
                                    />
                                    <p v-if="form.errors.postal_code" class="text-sm text-destructive">
                                        {{ form.errors.postal_code }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="city">Localidade</Label>
                                    <Input id="city" v-model="form.city" />
                                    <p v-if="form.errors.city" class="text-sm text-destructive">
                                        {{ form.errors.city }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="country_id">País</Label>
                                <select
                                    id="country_id"
                                    v-model="form.country_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="">—</option>
                                    <option v-for="c in countries" :key="c.id" :value="String(c.id)">
                                        {{ c.name }}
                                        <template v-if="c.iso_alpha_2"> ({{ c.iso_alpha_2 }})</template>
                                    </option>
                                </select>
                                <p v-if="form.errors.country_id" class="text-sm text-destructive">
                                    {{ form.errors.country_id }}
                                </p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="phone">Telefone</Label>
                                    <Input id="phone" v-model="form.phone" />
                                    <p v-if="form.errors.phone" class="text-sm text-destructive">
                                        {{ form.errors.phone }}
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="mobile">Telemóvel</Label>
                                    <Input id="mobile" v-model="form.mobile" />
                                    <p v-if="form.errors.mobile" class="text-sm text-destructive">
                                        {{ form.errors.mobile }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="website">Website</Label>
                                <Input id="website" v-model="form.website" />
                                <p v-if="form.errors.website" class="text-sm text-destructive">
                                    {{ form.errors.website }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="email">E-mail</Label>
                                <Input id="email" v-model="form.email" type="email" />
                                <p v-if="form.errors.email" class="text-sm text-destructive">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="gdpr">Consentimento RGPD</Label>
                                <select
                                    id="gdpr"
                                    v-model="gdprSelect"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="">—</option>
                                    <option value="yes">Sim</option>
                                    <option value="no">Não</option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <Label for="notes">Observações</Label>
                                <Textarea id="notes" v-model="form.notes" rows="3" />
                                <p v-if="form.errors.notes" class="text-sm text-destructive">
                                    {{ form.errors.notes }}
                                </p>
                            </div>

                            <div class="flex items-center gap-3">
                                <Switch id="active" v-model:checked="form.active" />
                                <Label for="active">Estado activo</Label>
                            </div>
                        </CardContent>
                        <CardFooter class="flex justify-end gap-2 border-t border-border pt-6">
                            <Button variant="outline" type="button" as-child>
                                <Link :href="route('modules.show', backSlug)">Cancelar</Link>
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
