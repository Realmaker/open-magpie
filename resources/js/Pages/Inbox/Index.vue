<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MarkdownRenderer from '@/Components/MarkdownRenderer.vue';
import Pagination from '@/Components/Pagination.vue';

interface InboxEvent {
    id: number;
    type: string;
    title: string;
    content: string;
    source: string;
    created_at: string;
    project: { id: number; name: string; slug: string };
    user?: { id: number; name: string };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedEvents {
    data: InboxEvent[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
}

interface ProjectOption {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    events: PaginatedEvents;
    projects: ProjectOption[];
    filters: { type?: string; project?: string };
}

const props = defineProps<Props>();

const selectedType = ref(props.filters.type || '');
const selectedProject = ref(props.filters.project || '');

const eventTypes = [
    { value: '', label: 'All Types' },
    { value: 'changelog', label: 'Changelog' },
    { value: 'documentation', label: 'Documentation' },
    { value: 'decision', label: 'Decision' },
    { value: 'milestone', label: 'Milestone' },
    { value: 'note', label: 'Note' },
    { value: 'task_update', label: 'Task Update' },
    { value: 'session_summary', label: 'Session Summary' },
    { value: 'deployment', label: 'Deployment' },
    { value: 'issue', label: 'Issue' },
    { value: 'review', label: 'Review' },
];

const typeColors: Record<string, string> = {
    changelog: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    decision: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    milestone: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    issue: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    note: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    documentation: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
    task_update: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    session_summary: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
    deployment: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    review: 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
};

const sourceColors: Record<string, string> = {
    'claude-code': 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200',
    manual: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    api: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
    system: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
};

function getTypeColor(type: string): string {
    return typeColors[type] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
}

function getSourceColor(source: string): string {
    return sourceColors[source] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
}

function formatTypeLabel(type: string): string {
    return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function relativeTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
    return date.toLocaleDateString();
}

function applyFilters(): void {
    const params: Record<string, string> = {};
    if (selectedType.value) params.type = selectedType.value;
    if (selectedProject.value) params.project = selectedProject.value;

    router.get(route('inbox'), params, { preserveState: true });
}

watch([selectedType, selectedProject], () => {
    applyFilters();
});
</script>

<template>
    <Head title="Inbox" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Inbox
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="flex-1 sm:max-w-xs">
                        <label for="type-filter" class="sr-only">Filter by type</label>
                        <select
                            id="type-filter"
                            v-model="selectedType"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 sm:text-sm"
                        >
                            <option v-for="eventType in eventTypes" :key="eventType.value" :value="eventType.value">
                                {{ eventType.label }}
                            </option>
                        </select>
                    </div>

                    <div class="flex-1 sm:max-w-xs">
                        <label for="project-filter" class="sr-only">Filter by project</label>
                        <select
                            id="project-filter"
                            v-model="selectedProject"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 sm:text-sm"
                        >
                            <option value="">All Projects</option>
                            <option v-for="project in projects" :key="project.id" :value="project.slug">
                                {{ project.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Event Feed -->
                <div v-if="events.data.length > 0" class="space-y-4">
                    <div
                        v-for="event in events.data"
                        :key="event.id"
                        class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800"
                    >
                        <div class="p-6">
                            <!-- Event Header -->
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <span
                                    :class="getTypeColor(event.type)"
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                >
                                    {{ formatTypeLabel(event.type) }}
                                </span>

                                <span
                                    :class="getSourceColor(event.source)"
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                >
                                    {{ event.source }}
                                </span>

                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ relativeTime(event.created_at) }}
                                </span>

                                <span v-if="event.user" class="text-xs text-gray-500 dark:text-gray-400">
                                    by {{ event.user.name }}
                                </span>
                            </div>

                            <!-- Event Title -->
                            <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">
                                {{ event.title }}
                            </h3>

                            <!-- Project Link -->
                            <div class="mb-3">
                                <Link
                                    :href="route('projects.show', event.project.slug)"
                                    class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                    {{ event.project.name }}
                                </Link>
                            </div>

                            <!-- Event Content (Markdown) -->
                            <div class="prose prose-sm max-w-none text-gray-700 dark:text-gray-300">
                                <MarkdownRenderer :content="event.content" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="rounded-lg bg-white p-12 text-center shadow dark:bg-gray-800">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">No events found</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        <template v-if="selectedType || selectedProject">
                            No events match your current filters. Try adjusting the filters above.
                        </template>
                        <template v-else>
                            Events from your projects will appear here as they are created.
                        </template>
                    </p>
                </div>

                <!-- Pagination -->
                <Pagination :links="events.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
