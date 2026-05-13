<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    module: { type: Object, required: true },
    user: { type: Object, default: null },
    roles: { type: Array, default: () => [] },
});

const isEdit = computed(() => props.user !== null);

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    phone: props.user?.phone ?? '',
    role_id: props.user?.role_id != null ? String(props.user.role_id) : '',
    password: '',
    password_confirmation: '',
    is_active: props.user?.is_active ?? true,
});

function submit() {
    if (isEdit.value) {
        form.transform((data) => {
            const payload = {
                name: data.name,
                email: data.email,
                phone: data.phone === '' ? null : data.phone,
                role_id: Number(data.role_id),
                is_active: data.is_active,
            };
            if (data.password !== '') {
                payload.password = data.password;
                payload.password_confirmation = data.password_confirmation;
            }
            return payload;
        }).put(route('modules.users.update', props.user.id));
        return;
    }

    form
        .transform((data) => ({
            name: data.name,
            email: data.email,
            phone: data.phone === '' ? null : data.phone,
            role_id: Number(data.role_id),
            password: data.password,
            password_confirmation: data.password_confirmation,
            is_active: data.is_active,
        }))
        .post(route('modules.users.store'));
}
</script>

<template>
    <Head :title="isEdit ? `Editar utilizador - ${module.label}` : `Novo utilizador - ${module.label}`" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-xl font-semibold leading-tight text-foreground">
                    {{ isEdit ? `Editar utilizador` : `Novo utilizador` }}
                </h2>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="route('modules.show', 'users')">Voltar</Link>
                </Button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-lg px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit">
                    <Card>
                        <CardHeader>
                            <CardTitle>Dados do utilizador</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="space-y-2">
                                <Label for="name">Nome</Label>
                                <Input id="name" v-model="form.name" autocomplete="name" />
                                <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="email">E-mail</Label>
                                <Input id="email" v-model="form.email" type="email" autocomplete="email" />
                                <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="phone">Telemóvel</Label>
                                <Input id="phone" v-model="form.phone" type="tel" autocomplete="tel" />
                                <p v-if="form.errors.phone" class="text-sm text-destructive">{{ form.errors.phone }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="role_id">Grupo de permissões</Label>
                                <select
                                    id="role_id"
                                    v-model="form.role_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="">Escolha…</option>
                                    <option v-for="r in roles" :key="r.id" :value="String(r.id)">
                                        {{ r.label }}
                                    </option>
                                </select>
                                <p v-if="form.errors.role_id" class="text-sm text-destructive">{{ form.errors.role_id }}</p>
                            </div>

                            <template v-if="!isEdit">
                                <div class="space-y-2">
                                    <Label for="password">Palavra-passe</Label>
                                    <Input id="password" v-model="form.password" type="password" autocomplete="new-password" />
                                    <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="password_confirmation">Confirmar palavra-passe</Label>
                                    <Input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        autocomplete="new-password"
                                    />
                                </div>
                            </template>
                            <template v-else>
                                <p class="text-xs text-muted-foreground">Deixe em branco para manter a palavra-passe atual.</p>
                                <div class="space-y-2">
                                    <Label for="password">Nova palavra-passe</Label>
                                    <Input id="password" v-model="form.password" type="password" autocomplete="new-password" />
                                    <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="password_confirmation">Confirmar nova palavra-passe</Label>
                                    <Input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        autocomplete="new-password"
                                    />
                                </div>
                            </template>

                            <div class="flex items-center gap-3">
                                <Switch id="is_active" v-model:checked="form.is_active" />
                                <Label for="is_active">Conta ativa</Label>
                            </div>
                            <p v-if="form.errors.is_active" class="text-sm text-destructive">{{ form.errors.is_active }}</p>
                        </CardContent>
                        <CardFooter class="flex justify-end gap-2 border-t border-border pt-6">
                            <Button variant="outline" type="button" as-child>
                                <Link :href="route('modules.show', 'users')">Cancelar</Link>
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
