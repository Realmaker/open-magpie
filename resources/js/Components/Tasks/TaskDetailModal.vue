<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import MarkdownRenderer from '@/Components/MarkdownRenderer.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

interface TaskData {
    id: number;
    title: string;
    description?: string;
    status: string;
    priority: string;
    type: string;
    due_date?: string;
    completed_at?: string;
    created_at: string;
    labels?: string[];
    source?: string;
}

defineProps<{
    show: boolean;
    task: TaskData | null;
}>();

const emit = defineEmits<{
    close: [];
    edit: [];
}>();

const statusColors: Record<string, string> = {
    open: 'bg-yellow-100 text-yellow-800',
    in_progress: 'bg-blue-100 text-blue-800',
    done: 'bg-green-100 text-green-800',
    deferred: 'bg-gray-100 text-gray-800',
    cancelled: 'bg-red-100 text-red-800',
};

const priorityColors: Record<string, string> = {
    low: 'bg-gray-100 text-gray-600',
    medium: 'bg-blue-100 text-blue-700',
    high: 'bg-orange-100 text-orange-700',
    critical: 'bg-red-100 text-red-700',
};

const typeColors: Record<string, string> = {
    task: 'bg-gray-100 text-gray-700',
    bug: 'bg-red-100 text-red-700',
    feature: 'bg-purple-100 text-purple-700',
    improvement: 'bg-blue-100 text-blue-700',
    research: 'bg-indigo-100 text-indigo-700',
    todo: 'bg-yellow-100 text-yellow-700',
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="2xl">
        <div class="p-6" v-if="task">
            <div class="flex items-start justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">{{ task.title }}</h3>
                <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex flex-wrap gap-2 mb-4">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusColors[task.status]">
                    {{ task.status }}
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="priorityColors[task.priority]">
                    {{ task.priority }}
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="typeColors[task.type]">
                    {{ task.type }}
                </span>
            </div>

            <div v-if="task.due_date" class="text-sm text-gray-600 mb-3">
                Due: {{ task.due_date }}
                <span v-if="task.completed_at" class="text-green-600 ml-2">
                    Completed: {{ task.completed_at }}
                </span>
            </div>

            <div v-if="task.labels?.length" class="flex flex-wrap gap-1 mb-4">
                <span v-for="label in task.labels" :key="label"
                    class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">
                    {{ label }}
                </span>
            </div>

            <div v-if="task.description" class="border-t pt-4">
                <MarkdownRenderer :content="task.description" />
            </div>
            <p v-else class="border-t pt-4 text-sm text-gray-400 italic">No description</p>

            <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
                <SecondaryButton @click="emit('close')">Close</SecondaryButton>
                <PrimaryButton @click="emit('edit')">Edit</PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
