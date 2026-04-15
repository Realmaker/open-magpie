<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

interface TaskData {
    id?: number;
    title: string;
    description?: string;
    status: string;
    priority: string;
    type: string;
    due_date?: string;
}

const props = defineProps<{
    show: boolean;
    projectSlug: string;
    task?: TaskData | null;
}>();

const emit = defineEmits<{
    close: [];
    saved: [];
}>();

const form = useForm({
    title: '',
    description: '',
    status: 'open',
    priority: 'medium',
    type: 'task',
    due_date: '',
    source: 'manual',
});

const statuses = [
    { value: 'open', label: 'Open' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'done', label: 'Done' },
    { value: 'deferred', label: 'Deferred' },
    { value: 'cancelled', label: 'Cancelled' },
];

const priorities = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
    { value: 'critical', label: 'Critical' },
];

const types = [
    { value: 'task', label: 'Task' },
    { value: 'bug', label: 'Bug' },
    { value: 'feature', label: 'Feature' },
    { value: 'improvement', label: 'Improvement' },
    { value: 'research', label: 'Research' },
    { value: 'todo', label: 'TODO' },
];

watch(() => props.show, (newVal) => {
    if (newVal && props.task) {
        form.title = props.task.title;
        form.description = props.task.description ?? '';
        form.status = props.task.status;
        form.priority = props.task.priority;
        form.type = props.task.type;
        form.due_date = props.task.due_date ?? '';
    } else if (newVal) {
        form.reset();
    }
});

const submit = () => {
    if (props.task?.id) {
        form.patch(route('projects.tasks.update', { slug: props.projectSlug, id: props.task.id }), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        });
    } else {
        form.post(route('projects.tasks.store', { slug: props.projectSlug }), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        });
    }
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="2xl">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                {{ task?.id ? 'Edit Task' : 'New Task' }}
            </h3>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <InputLabel for="task-title" value="Title" />
                    <TextInput id="task-title" v-model="form.title" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="task-description" value="Description (optional)" />
                    <textarea
                        id="task-description"
                        v-model="form.description"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        placeholder="Describe the task..."
                    />
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <InputLabel for="task-status" value="Status" />
                        <select id="task-status" v-model="form.status"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="task-priority" value="Priority" />
                        <select id="task-priority" v-model="form.priority"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel for="task-type" value="Type" />
                        <select id="task-type" v-model="form.type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <InputLabel for="task-due-date" value="Due Date (optional)" />
                    <TextInput id="task-due-date" v-model="form.due_date" type="date" class="mt-1 block w-full" />
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t">
                    <SecondaryButton @click="emit('close')">Cancel</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : (task?.id ? 'Update Task' : 'Create Task') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
