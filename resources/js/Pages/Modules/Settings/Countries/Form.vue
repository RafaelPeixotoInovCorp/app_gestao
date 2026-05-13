<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    module: { type: Object, required: true },
    country: { type: Object, default: null },
});

const isEdit = computed(() => props.country !== null);

const form = useForm({
    name: props.country?.name ?? '',
    iso_alpha_2: props.country?.iso_alpha_2 ?? '',
    iso_alpha_3: props.country?.iso_alpha_3 ?? '',
});

function submit() {
    if (isEdit.value) {
        form.put(route('modules.countries.update', props.country.id));
        return;
    }
    form.post(route('modules.countries.store'));
}
</script>

<template>
    <Head :title="isEdit ? `Editar país - ${module.label}` : `Novo país - ${module.label}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-foreground">
                    {{ isEdit ? 'Editar país' : 'Novo país' }}
                </h2>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="route('modules.show', 'settings-countries')">Voltar</Link>
                </Button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit">
                    <Card>
                        <CardHeader>
                            <CardTitle>Dados do país</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="space-y-2">
                                <Label for="name">Nome</Label>
                                <Input id="name" v-model="form.name" autocomplete="off" />
                                <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="iso_alpha_2">Sigla (ISO 3166-1 alpha-2)</Label>
                                <Input
                                    id="iso_alpha_2"
                                    v-model="form.iso_alpha_2"
                                    class="font-mono uppercase"
                                    maxlength="2"
                                    autocomplete="off"
                                    placeholder="PT"
                                />
                                <p v-if="form.errors.iso_alpha_2" class="text-sm text-destructive">{{ form.errors.iso_alpha_2 }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="iso_alpha_3">ISO3 (alpha-3)</Label>
                                <Input
                                    id="iso_alpha_3"
                                    v-model="form.iso_alpha_3"
                                    class="font-mono uppercase"
                                    maxlength="3"
                                    autocomplete="off"
                                    placeholder="PRT"
                                />
                                <p class="text-xs text-muted-foreground">Opcional.</p>
                                <p v-if="form.errors.iso_alpha_3" class="text-sm text-destructive">{{ form.errors.iso_alpha_3 }}</p>
                            </div>
                        </CardContent>
                        <CardFooter class="flex justify-end gap-2 border-t border-border pt-6">
                            <Button variant="outline" type="button" as-child>
                                <Link :href="route('modules.show', 'settings-countries')">Cancelar</Link>
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
