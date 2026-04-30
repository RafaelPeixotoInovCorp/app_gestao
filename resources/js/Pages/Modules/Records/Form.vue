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

const form = useForm({
    entity_id: props.record?.entity_id != null ? String(props.record.entity_id) : '',
    title: props.record?.title ?? '',
    description: props.record?.description ?? '',
    active: props.record?.active ?? true,
});

function submit() {
    if (isEdit.value) {
        form.transform((data) => ({
            ...data,
            entity_id: data.entity_id === '' ? null : Number(data.entity_id),
        })).put(route('modules.records.update', { slug: props.module.slug, record: props.record.id }));
        return;
    }

    form.transform((data) => ({
        ...data,
        entity_id: data.entity_id === '' ? null : Number(data.entity_id),
    })).post(route('modules.records.store', props.module.slug));
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
                                    {{ module.slug === 'customer-orders' ? 'Cliente' : 'Fornecedor' }}
                                </Label>
                                <select
                                    id="entity_id"
                                    v-model="form.entity_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="">Selecione...</option>
                                    <option v-for="entity in relatedEntities" :key="entity.id" :value="String(entity.id)">
                                        {{ entity.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.entity_id" class="text-sm text-destructive">
                                    {{ form.errors.entity_id }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    Campo obrigatório para permitir filtros por {{ module.slug === 'customer-orders' ? 'cliente' : 'fornecedor' }}.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="title">Título</Label>
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

                            <div class="flex items-center gap-3">
                                <Switch id="active" v-model:checked="form.active" />
                                <Label for="active">Ativo</Label>
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
