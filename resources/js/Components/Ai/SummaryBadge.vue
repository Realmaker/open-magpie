<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    summary: string;
}>();

const expanded = ref(false);
</script>

<template>
    <div v-if="summary" class="mt-2">
        <button
            @click="expanded = !expanded"
            class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200 hover:bg-violet-200 dark:hover:bg-violet-800 transition-colors"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
            </svg>
            AI Summary
            <svg
                class="w-3 h-3 transition-transform"
                :class="{ 'rotate-180': expanded }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div
            v-show="expanded"
            class="mt-2 p-3 text-sm text-gray-700 dark:text-gray-300 bg-violet-50 dark:bg-violet-900/30 rounded-md border border-violet-200 dark:border-violet-800"
        >
            {{ summary }}
        </div>
    </div>
</template>
