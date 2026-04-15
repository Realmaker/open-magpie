<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface SearchResult {
    id: number;
    type: 'project' | 'event' | 'task' | 'document';
    title: string;
    subtitle: string;
    excerpt: string | null;
    url: string;
    date: string;
}

interface Props {
    results: SearchResult[];
    query: string;
    type: string;
    totalResults: number;
}

const props = defineProps<Props>();

const searchQuery = ref(props.query);
const activeType = ref(props.type);

const typeFilters = [
    { value: 'all', label: 'All' },
    { value: 'projects', label: 'Projects' },
    { value: 'events', label: 'Events' },
    { value: 'tasks', label: 'Tasks' },
    { value: 'documents', label: 'Documents' },
];

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const performSearch = () => {
    const params: Record<string, string> = {};
    if (searchQuery.value) params.q = searchQuery.value;
    if (activeType.value !== 'all') params.type = activeType.value;

    router.get(route('search'), params, {
        preserveState: true,
        replace: true,
    });
};

watch(searchQuery, () => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        performSearch();
    }, 300);
});

const setType = (type: string) => {
    activeType.value = type;
    performSearch();
};

function relativeTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function getTypeBadge(type: string): { class: string; label: string; icon: string } {
    const badges: Record<string, { class: string; label: string; icon: string }> = {
        project: {
            class: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
            label: 'Project',
            icon: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
        },
        event: {
            class: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
            label: 'Event',
            icon: 'M13 10V3L4 14h7v7l9-11h-7z',
        },
        task: {
            class: 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200',
            label: 'Task',
            icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        },
        document: {
            class: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            label: 'Document',
            icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        },
    };
    return badges[type] || badges.event;
}

function getTypeCount(type: string): number {
    if (type === 'all') return props.results.length;
    return props.results.filter(r => r.type === type).length;
}
</script>

<template>
    <Head title="Search" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Search</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Search across all projects, events, tasks, and documents.
                    </p>
                </div>

                <!-- Search Input -->
                <div class="mb-6">
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input
                            v-model="searchQuery"
                            type="text"
                            autofocus
                            placeholder="Type to search..."
                            class="block w-full rounded-lg border-gray-300 py-3 pl-11 pr-4 text-lg shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-400"
                        />
                        <div v-if="searchQuery" class="absolute inset-y-0 right-0 flex items-center pr-4">
                            <button
                                @click="searchQuery = ''"
                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Type Filter Tabs -->
                <div class="mb-6 flex flex-wrap gap-2 border-b border-gray-200 pb-4 dark:border-gray-700">
                    <button
                        v-for="filter in typeFilters"
                        :key="filter.value"
                        @click="setType(filter.value)"
                        :class="[
                            'rounded-md px-4 py-2 text-sm font-medium transition-colors',
                            activeType === filter.value
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700'
                        ]"
                    >
                        {{ filter.label }}
                    </button>
                </div>

                <!-- Results Count -->
                <div v-if="query && query.length >= 2" class="mb-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ totalResults }} {{ totalResults === 1 ? 'result' : 'results' }} for
                        "<span class="font-medium text-gray-700 dark:text-gray-200">{{ query }}</span>"
                    </p>
                </div>

                <!-- Results List -->
                <div v-if="results.length > 0" class="space-y-3">
                    <Link
                        v-for="result in results"
                        :key="`${result.type}-${result.id}`"
                        :href="result.url"
                        class="block rounded-lg bg-white p-5 shadow transition hover:shadow-md dark:bg-gray-800 dark:hover:bg-gray-750"
                    >
                        <div class="flex items-start gap-4">
                            <!-- Type Icon -->
                            <div class="mt-0.5 flex-shrink-0">
                                <span
                                    :class="getTypeBadge(result.type).class"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            :d="getTypeBadge(result.type).icon"
                                        />
                                    </svg>
                                </span>
                            </div>

                            <!-- Content -->
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="truncate text-base font-semibold text-gray-900 dark:text-white">
                                        {{ result.title }}
                                    </h3>
                                    <span
                                        :class="getTypeBadge(result.type).class"
                                        class="inline-flex flex-shrink-0 rounded px-2 py-0.5 text-xs font-medium"
                                    >
                                        {{ getTypeBadge(result.type).label }}
                                    </span>
                                </div>

                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    {{ result.subtitle }}
                                </p>

                                <p v-if="result.excerpt" class="mt-1.5 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">
                                    {{ result.excerpt }}
                                </p>
                            </div>

                            <!-- Date -->
                            <div class="flex-shrink-0 text-right">
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ relativeTime(result.date) }}
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>

                <!-- Empty State: No query -->
                <div v-else-if="!query || query.length < 2" class="rounded-lg bg-white py-16 text-center shadow dark:bg-gray-800">
                    <svg class="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                        Search your workspace
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Type at least 2 characters to start searching across all your projects, events, tasks, and documents.
                    </p>
                </div>

                <!-- Empty State: No results -->
                <div v-else class="rounded-lg bg-white py-16 text-center shadow dark:bg-gray-800">
                    <svg class="mx-auto h-16 w-16 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
                        No results found
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        No matches for "<span class="font-medium">{{ query }}</span>". Try a different search term or filter.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
