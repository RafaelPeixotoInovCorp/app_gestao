<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    module: { type: Object, required: true },
    tasks: { type: Object, required: true },
    canCreate: { type: Boolean, default: false },
    canUpdate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const page = usePage();

watch(
    () => page.props.flash?.success,
    (msg) => {
        if (msg) toast.success(msg);
    },
    { immediate: true },
);

const form = useForm({
    title: '',
    notes: '',
    due_date: '',
});
const showTaskModal = ref(false);
const taskSearch = ref('');
const taskStatus = ref('all');

function submitTask() {
    form.post(route('modules.calendar.tasks.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showTaskModal.value = false;
        },
    });
}

function toggleTask(task) {
    router.patch(route('modules.calendar.tasks.toggle', task.id), {}, { preserveScroll: true });
}

function removeTask(task) {
    if (!confirm('Remover esta tarefa?')) return;
    router.delete(route('modules.calendar.tasks.destroy', task.id), { preserveScroll: true });
}

const weekDays = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
const currentMonth = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));

const filteredTasks = computed(() => {
    return (props.tasks.data ?? []).filter((task) => {
        const matchesSearch =
            taskSearch.value.trim() === '' ||
            task.title?.toLowerCase().includes(taskSearch.value.toLowerCase()) ||
            task.notes?.toLowerCase().includes(taskSearch.value.toLowerCase());
        const matchesStatus =
            taskStatus.value === 'all' ||
            (taskStatus.value === 'done' && task.is_done) ||
            (taskStatus.value === 'pending' && !task.is_done);
        return matchesSearch && matchesStatus;
    });
});

const doneCount = computed(() => filteredTasks.value.filter((task) => task.is_done).length);
const pendingCount = computed(() => filteredTasks.value.filter((task) => !task.is_done).length);

const tasksByDate = computed(() => {
    const grouped = {};
    for (const task of filteredTasks.value) {
        if (!task.due_date) continue;
        if (!grouped[task.due_date]) grouped[task.due_date] = [];
        grouped[task.due_date].push(task);
    }
    return grouped;
});

const monthLabel = computed(() =>
    currentMonth.value.toLocaleDateString('pt-PT', {
        month: 'long',
        year: 'numeric',
    }),
);

const calendarDays = computed(() => {
    const year = currentMonth.value.getFullYear();
    const month = currentMonth.value.getMonth();
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const firstWeekDay = (firstDay.getDay() + 6) % 7;
    const totalDays = lastDay.getDate();
    const days = [];

    for (let i = 0; i < firstWeekDay; i++) {
        days.push(null);
    }

    for (let day = 1; day <= totalDays; day++) {
        const date = new Date(year, month, day);
        const isoDate = date.toISOString().slice(0, 10);
        days.push({
            day,
            isoDate,
            tasks: tasksByDate.value[isoDate] ?? [],
        });
    }

    while (days.length % 7 !== 0) {
        days.push(null);
    }

    return days;
});

const allTasks = computed(() => {
    const list = [...filteredTasks.value];
    return list.sort((a, b) => {
        if (a.is_done !== b.is_done) return a.is_done ? 1 : -1;
        if (!a.due_date && !b.due_date) return b.id - a.id;
        if (!a.due_date) return 1;
        if (!b.due_date) return -1;
        return a.due_date.localeCompare(b.due_date);
    });
});

function previousMonth() {
    currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() - 1, 1);
}

function nextMonth() {
    currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + 1, 1);
}
</script>

<template>
    <Head :title="module.label" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-foreground">{{ module.label }}</h2>
                <Button
                    v-if="canCreate"
                    size="sm"
                    variant="outline"
                    class="rounded-lg border-slate-300 bg-white text-slate-700 hover:bg-slate-100"
                    @click="showTaskModal = true"
                >
                    Nova tarefa
                </Button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
                <Card class="lg:col-span-12">
                    <CardContent class="grid gap-3 p-4 md:grid-cols-5">
                        <div class="rounded-lg border bg-card p-3">
                            <p class="text-xs text-muted-foreground">Total (filtro atual)</p>
                            <p class="mt-1 text-xl font-semibold">{{ filteredTasks.length }}</p>
                        </div>
                        <div class="rounded-lg border bg-card p-3">
                            <p class="text-xs text-muted-foreground">Pendentes</p>
                            <p class="mt-1 text-xl font-semibold">{{ pendingCount }}</p>
                        </div>
                        <div class="rounded-lg border bg-card p-3">
                            <p class="text-xs text-muted-foreground">Concluídas</p>
                            <p class="mt-1 text-xl font-semibold">{{ doneCount }}</p>
                        </div>
                        <input
                            v-model="taskSearch"
                            type="text"
                            placeholder="Pesquisar tarefa..."
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm md:col-span-2"
                        />
                        <select
                            v-model="taskStatus"
                            class="h-10 rounded-md border border-input bg-background px-3 text-sm md:col-span-1"
                        >
                            <option value="all">Todos os estados</option>
                            <option value="pending">Pendentes</option>
                            <option value="done">Concluídas</option>
                        </select>
                    </CardContent>
                </Card>

                <Card class="lg:col-span-8">
                    <CardHeader>
                        <div class="flex items-center justify-between gap-2">
                            <CardTitle class="capitalize">{{ monthLabel }}</CardTitle>
                            <div class="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="rounded-lg border-slate-300 bg-white text-slate-700 hover:bg-slate-100"
                                    @click="previousMonth"
                                >
                                    Anterior
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="rounded-lg border-slate-300 bg-white text-slate-700 hover:bg-slate-100"
                                    @click="nextMonth"
                                >
                                    Seguinte
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="grid grid-cols-7 gap-2 text-center text-xs font-medium text-muted-foreground">
                            <div v-for="dayName in weekDays" :key="dayName">{{ dayName }}</div>
                        </div>

                        <div
                            class="grid grid-cols-7 gap-2"
                        >
                            <div
                                v-for="(day, idx) in calendarDays"
                                :key="`day-${idx}`"
                                class="min-h-24 rounded-lg border p-2"
                            >
                                <template v-if="day">
                                    <p class="text-xs font-semibold text-foreground">{{ day.day }}</p>
                                    <div class="mt-2 space-y-1">
                                        <p
                                            v-for="task in day.tasks.slice(0, 2)"
                                            :key="task.id"
                                            class="truncate rounded bg-muted px-1.5 py-0.5 text-[11px]"
                                            :class="task.is_done ? 'line-through text-muted-foreground' : 'text-foreground'"
                                        >
                                            {{ task.title }}
                                        </p>
                                        <p v-if="day.tasks.length > 2" class="text-[11px] text-muted-foreground">
                                            +{{ day.tasks.length - 2 }} tarefas
                                        </p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="lg:col-span-4">
                    <CardHeader>
                        <CardTitle>Todas as tarefas</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div v-if="!allTasks.length" class="rounded-lg border p-4 text-sm text-muted-foreground">
                            Ainda não há tarefas.
                        </div>
                        <div
                            v-for="task in allTasks"
                            :key="task.id"
                            class="flex items-start justify-between gap-3 rounded-lg border p-3"
                        >
                            <label class="flex min-w-0 flex-1 items-start gap-3">
                                <input
                                    type="checkbox"
                                    class="mt-1 h-4 w-4"
                                    :checked="task.is_done"
                                    :disabled="!canUpdate"
                                    @change="toggleTask(task)"
                                />
                                <div class="min-w-0">
                                    <p :class="task.is_done ? 'text-muted-foreground line-through' : 'text-foreground'">
                                        {{ task.title }}
                                    </p>
                                    <p v-if="task.due_date" class="mt-1 text-xs text-muted-foreground">
                                        {{ task.due_date }}
                                    </p>
                                </div>
                            </label>

                            <Button v-if="canDelete" variant="destructive" size="sm" @click="removeTask(task)">
                                Remover
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <Modal :show="showTaskModal" max-width="lg" @close="showTaskModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-foreground">Nova tarefa</h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Preenche os dados da tarefa para adicionar ao calendário.
                </p>

                <form class="mt-4 space-y-4" @submit.prevent="submitTask">
                    <div class="space-y-2">
                        <Label for="modal_title">Tarefa</Label>
                        <Input id="modal_title" v-model="form.title" />
                        <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="modal_due_date">Data limite</Label>
                        <Input id="modal_due_date" v-model="form.due_date" type="date" />
                    </div>
                    <div class="space-y-2">
                        <Label for="modal_notes">Notas</Label>
                        <Textarea id="modal_notes" v-model="form.notes" rows="3" />
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" @click="showTaskModal = false">Cancelar</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'A guardar...' : 'Adicionar tarefa' }}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
