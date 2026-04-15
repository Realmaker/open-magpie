<script setup lang="ts">
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

interface Project {
    id: number;
    name: string;
    slug: string;
}

const props = defineProps<{
    show: boolean;
    projects: Project[];
}>();

const emit = defineEmits<{
    close: [];
}>();

const form = ref({
    title: '',
    prompt: '',
    description: '',
    project_slug: '',
    type: 'code_change',
    priority: 'medium',
});

const processing = ref(false);

watch(() => props.show, (val) => {
    if (val) {
        form.value = {
            title: '',
            prompt: '',
            description: '',
            project_slug: '',
            type: 'code_change',
            priority: 'medium',
        };
    }
});

function submit() {
    processing.value = true;
    router.post(route('workers.jobs.store'), form.value, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            emit('close');
        },
    });
}
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="2xl">
        <div class="p-6">
            <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                New Worker Job
            </h2>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Title *</label>
                    <input
                        v-model="form.title"
                        type="text"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        placeholder="What should be done?"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Project</label>
                    <select
                        v-model="form.project_slug"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                    >
                        <option value="">No project</option>
                        <option v-for="p in projects" :key="p.id" :value="p.slug">{{ p.name }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                        <select
                            v-model="form.type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <option value="code_change">Code Change</option>
                            <option value="new_project">New Project</option>
                            <option value="prepared">Prepared (no exec)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Priority</label>
                        <select
                            v-model="form.priority"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        placeholder="Optional context for the job..."
                    ></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Prompt *</label>
                    <textarea
                        v-model="form.prompt"
                        rows="6"
                        required
                        class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        placeholder="The prompt that will be sent to claude -p ..."
                    ></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="processing"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ processing ? 'Creating...' : 'Create Job' }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
