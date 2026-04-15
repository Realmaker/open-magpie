<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import MarkdownRenderer from '@/Components/MarkdownRenderer.vue';

interface DocumentData {
    id: number;
    title: string;
    slug: string;
    category: string;
    current_version: number;
    content?: string;
    updated_at: string;
}

defineProps<{
    show: boolean;
    document: DocumentData | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const categoryColors: Record<string, string> = {
    documentation: 'bg-blue-100 text-blue-800',
    specification: 'bg-purple-100 text-purple-800',
    changelog: 'bg-green-100 text-green-800',
    readme: 'bg-indigo-100 text-indigo-800',
    architecture: 'bg-orange-100 text-orange-800',
    meeting_notes: 'bg-yellow-100 text-yellow-800',
    guide: 'bg-teal-100 text-teal-800',
    other: 'bg-gray-100 text-gray-800',
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="2xl">
        <div class="p-6" v-if="document">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">{{ document.title }}</h2>
                    <div class="flex items-center gap-2 mt-1">
                        <span
                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="categoryColors[document.category] ?? categoryColors.other"
                        >
                            {{ document.category }}
                        </span>
                        <span class="text-sm text-gray-500">v{{ document.current_version }}</span>
                        <span class="text-sm text-gray-400">{{ document.updated_at }}</span>
                    </div>
                </div>
                <button @click="emit('close')" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="border-t pt-4 max-h-[70vh] overflow-y-auto">
                <MarkdownRenderer v-if="document.content" :content="document.content" />
                <p v-else class="text-gray-400 italic">No content available</p>
            </div>
        </div>
    </Modal>
</template>
