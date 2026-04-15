<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface Project {
    id: number;
    name: string;
    slug: string;
}

interface Task {
    id: number;
    title: string;
    description?: string;
    status: 'open' | 'in_progress' | 'done' | 'deferred' | 'cancelled';
    priority: 'low' | 'medium' | 'high' | 'critical';
    type: string;
    due_date?: string;
    completed_at?: string;
    created_at: string;
    labels?: string[];
    source?: string;
    project?: Project;
}

interface PaginatedTasks {
    data: Task[];
    meta?: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    links?: {
        first?: string;
        last?: string;
        prev?: string;
        next?: string;
    };
    current_page?: number;
    last_page?: number;
    per_page?: number;
    total?: number;
}

interface Stats {
    total: number;
    open: number;
    in_progress: number;
    done: number;
    deferred: number;
    cancelled: number;
    overdue: number;
}

interface Filters {
    search?: string;
    status?: string;
    priority?: string;
    type?: string;
    project?: string;
    sort?: string;
    direction?: string;
}

const props = defineProps<{
    tasks: PaginatedTasks;
    projects: Project[];
    stats: Stats;
    filters: Filters;
}>();

const search = ref(props.filters.search ?? '');
const statusFilter = ref(props.filters.status ?? '');
const priorityFilter = ref(props.filters.priority ?? '');
const typeFilter = ref(props.filters.type ?? '');
const projectFilter = ref(props.filters.project ?? '');
const sortField = ref(props.filters.sort ?? 'created_at');
const sortDirection = ref(props.filters.direction ?? 'desc');

const taskList = computed(() => props.tasks.data ?? props.tasks);
const pagination = computed(() => {
    if (props.tasks.meta) return props.tasks.meta;
    return {
        current_page: props.tasks.current_page ?? 1,
        last_page: props.tasks.last_page ?? 1,
        per_page: props.tasks.per_page ?? 50,
        total: props.tasks.total ?? taskList.value.length,
    };
});

let searchTimeout: ReturnType<typeof setTimeout>;

function applyFilters() {
    const params: Record<string, string> = {};
    if (search.value) params.search = search.value;
    if (statusFilter.value) params.status = statusFilter.value;
    if (priorityFilter.value) params.priority = priorityFilter.value;
    if (typeFilter.value) params.type = typeFilter.value;
    if (projectFilter.value) params.project = projectFilter.value;
    if (sortField.value !== 'created_at') params.sort = sortField.value;
    if (sortDirection.value !== 'desc') params.direction = sortDirection.value;

    router.get(route('tasks.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function onSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 400);
}

function toggleSort(field: string) {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = field === 'title' ? 'asc' : 'desc';
    }
    applyFilters();
}

function filterByStatus(status: string) {
    statusFilter.value = statusFilter.value === status ? '' : status;
    applyFilters();
}

function clearFilters() {
    search.value = '';
    statusFilter.value = '';
    priorityFilter.value = '';
    typeFilter.value = '';
    projectFilter.value = '';
    sortField.value = 'created_at';
    sortDirection.value = 'desc';
    applyFilters();
}

function quickStatusChange(task: Task, newStatus: string) {
    if (!task.project) return;
    router.patch(route('projects.tasks.update', { slug: task.project.slug, id: task.id }), {
        status: newStatus,
    }, { preserveScroll: true });
}

const hasActiveFilters = computed(() => {
    return search.value || statusFilter.value || priorityFilter.value || typeFilter.value || projectFilter.value;
});

watch([statusFilter, priorityFilter, typeFilter, projectFilter], () => {
    applyFilters();
});

const statusColors: Record<string, string> = {
    open: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    done: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    deferred: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

const priorityColors: Record<string, string> = {
    low: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
    medium: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200',
    high: 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-200',
    critical: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200',
};

const typeColors: Record<string, string> = {
    task: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    bug: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-200',
    feature: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-200',
    improvement: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-200',
    research: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200',
    todo: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-200',
};

const statusLabels: Record<string, string> = {
    open: 'Open',
    in_progress: 'In Progress',
    done: 'Done',
    deferred: 'Deferred',
    cancelled: 'Cancelled',
};

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function isOverdue(task: Task): boolean {
    if (!task.due_date || task.status === 'done' || task.status === 'cancelled') return false;
    return new Date(task.due_date) < new Date();
}

function sortIcon(field: string): string {
    if (sortField.value !== field) return '';
    return sortDirection.value === 'asc' ? '\u2191' : '\u2193';
}
</script>

<template>
    <Head title="Tasks" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                All Tasks
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-7">
                    <button
                        @click="filterByStatus('')"
                        :class="[
                            'rounded-lg p-4 text-center transition',
                            !statusFilter ? 'ring-2 ring-indigo-500 bg-white shadow-md dark:bg-gray-800' : 'bg-white shadow dark:bg-gray-800 hover:shadow-md'
                        ]"
                    >
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Total</div>
                    </button>
                    <button
                        @click="filterByStatus('open')"
                        :class="[
                            'rounded-lg p-4 text-center transition',
                            statusFilter === 'open' ? 'ring-2 ring-yellow-500 bg-white shadow-md dark:bg-gray-800' : 'bg-white shadow dark:bg-gray-800 hover:shadow-md'
                        ]"
                    >
                        <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ stats.open }}</div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Open</div>
                    </button>
                    <button
                        @click="filterByStatus('in_progress')"
                        :class="[
                            'rounded-lg p-4 text-center transition',
                            statusFilter === 'in_progress' ? 'ring-2 ring-blue-500 bg-white shadow-md dark:bg-gray-800' : 'bg-white shadow dark:bg-gray-800 hover:shadow-md'
                        ]"
                    >
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ stats.in_progress }}</div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">In Progress</div>
                    </button>
                    <button
                        @click="filterByStatus('done')"
                        :class="[
                            'rounded-lg p-4 text-center transition',
                            statusFilter === 'done' ? 'ring-2 ring-green-500 bg-white shadow-md dark:bg-gray-800' : 'bg-white shadow dark:bg-gray-800 hover:shadow-md'
                        ]"
                    >
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ stats.done }}</div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Done</div>
                    </button>
                    <button
                        @click="filterByStatus('deferred')"
                        :class="[
                            'rounded-lg p-4 text-center transition',
                            statusFilter === 'deferred' ? 'ring-2 ring-gray-500 bg-white shadow-md dark:bg-gray-800' : 'bg-white shadow dark:bg-gray-800 hover:shadow-md'
                        ]"
                    >
                        <div class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ stats.deferred }}</div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Deferred</div>
                    </button>
                    <button
                        @click="filterByStatus('cancelled')"
                        :class="[
                            'rounded-lg p-4 text-center transition',
                            statusFilter === 'cancelled' ? 'ring-2 ring-red-500 bg-white shadow-md dark:bg-gray-800' : 'bg-white shadow dark:bg-gray-800 hover:shadow-md'
                        ]"
                    >
                        <div class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.cancelled }}</div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Cancelled</div>
                    </button>
                    <div
                        :class="[
                            'rounded-lg p-4 text-center',
                            stats.overdue > 0 ? 'bg-red-50 shadow dark:bg-red-900/30' : 'bg-white shadow dark:bg-gray-800'
                        ]"
                    >
                        <div :class="['text-2xl font-bold', stats.overdue > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500']">
                            {{ stats.overdue }}
                        </div>
                        <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Overdue</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="mb-6 rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Search -->
                        <div class="flex-1 min-w-[200px]">
                            <input
                                v-model="search"
                                @input="onSearchInput"
                                type="text"
                                placeholder="Search tasks..."
                                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                            />
                        </div>

                        <!-- Priority Filter -->
                        <select
                            v-model="priorityFilter"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <option value="">All Priorities</option>
                            <option value="critical">Critical</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>

                        <!-- Type Filter -->
                        <select
                            v-model="typeFilter"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <option value="">All Types</option>
                            <option value="task">Task</option>
                            <option value="bug">Bug</option>
                            <option value="feature">Feature</option>
                            <option value="improvement">Improvement</option>
                            <option value="research">Research</option>
                            <option value="todo">TODO</option>
                        </select>

                        <!-- Project Filter -->
                        <select
                            v-model="projectFilter"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <option value="">All Projects</option>
                            <option v-for="p in projects" :key="p.id" :value="p.slug">{{ p.name }}</option>
                        </select>

                        <!-- Clear Filters -->
                        <button
                            v-if="hasActiveFilters"
                            @click="clearFilters"
                            class="rounded-md px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <!-- Task Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <div v-if="taskList.length > 0" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="w-8 px-4 py-3"></th>
                                    <th
                                        @click="toggleSort('title')"
                                        class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        Title {{ sortIcon('title') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Project
                                    </th>
                                    <th
                                        @click="toggleSort('status')"
                                        class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        Status {{ sortIcon('status') }}
                                    </th>
                                    <th
                                        @click="toggleSort('priority')"
                                        class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        Priority {{ sortIcon('priority') }}
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Type
                                    </th>
                                    <th
                                        @click="toggleSort('due_date')"
                                        class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        Due {{ sortIcon('due_date') }}
                                    </th>
                                    <th
                                        @click="toggleSort('created_at')"
                                        class="cursor-pointer px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 transition hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                    >
                                        Created {{ sortIcon('created_at') }}
                                    </th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="task in taskList"
                                    :key="task.id"
                                    class="transition hover:bg-gray-50 dark:hover:bg-gray-750"
                                    :class="{ 'bg-red-50/50 dark:bg-red-900/10': isOverdue(task) }"
                                >
                                    <!-- Quick status toggle -->
                                    <td class="px-4 py-3">
                                        <button
                                            v-if="task.status !== 'done' && task.status !== 'cancelled'"
                                            @click="quickStatusChange(task, task.status === 'open' ? 'in_progress' : 'done')"
                                            class="flex h-5 w-5 items-center justify-center rounded border-2 transition"
                                            :class="task.status === 'in_progress'
                                                ? 'border-blue-500 bg-blue-50 text-blue-500 hover:bg-blue-100 dark:bg-blue-900/30 dark:hover:bg-blue-900/50'
                                                : 'border-gray-300 hover:border-green-500 hover:bg-green-50 dark:border-gray-600 dark:hover:border-green-500'"
                                            :title="task.status === 'open' ? 'Start' : 'Complete'"
                                        >
                                            <svg v-if="task.status === 'in_progress'" class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                        <div
                                            v-else-if="task.status === 'done'"
                                            class="flex h-5 w-5 items-center justify-center rounded border-2 border-green-500 bg-green-500 text-white"
                                        >
                                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                        <div
                                            v-else
                                            class="flex h-5 w-5 items-center justify-center rounded border-2 border-red-300 dark:border-red-700"
                                        >
                                            <svg class="h-3 w-3 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </div>
                                    </td>

                                    <!-- Title -->
                                    <td class="px-4 py-3">
                                        <Link
                                            v-if="task.project"
                                            :href="route('projects.show', task.project.slug)"
                                            class="text-sm font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                                            :class="{ 'line-through opacity-60': task.status === 'done' || task.status === 'cancelled' }"
                                        >
                                            {{ task.title }}
                                        </Link>
                                        <span v-else class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ task.title }}</span>
                                        <div v-if="task.labels?.length" class="mt-1 flex flex-wrap gap-1">
                                            <span
                                                v-for="label in task.labels"
                                                :key="label"
                                                class="inline-flex rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300"
                                            >
                                                {{ label }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Project -->
                                    <td class="px-4 py-3">
                                        <Link
                                            v-if="task.project"
                                            :href="route('projects.show', task.project.slug)"
                                            class="text-sm text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400"
                                        >
                                            {{ task.project.name }}
                                        </Link>
                                    </td>

                                    <!-- Status -->
                                    <td class="px-4 py-3">
                                        <span
                                            :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', statusColors[task.status]]"
                                        >
                                            {{ statusLabels[task.status] ?? task.status }}
                                        </span>
                                    </td>

                                    <!-- Priority -->
                                    <td class="px-4 py-3">
                                        <span
                                            :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', priorityColors[task.priority]]"
                                        >
                                            {{ task.priority }}
                                        </span>
                                    </td>

                                    <!-- Type -->
                                    <td class="px-4 py-3">
                                        <span
                                            :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium', typeColors[task.type]]"
                                        >
                                            {{ task.type }}
                                        </span>
                                    </td>

                                    <!-- Due Date -->
                                    <td class="px-4 py-3">
                                        <span
                                            v-if="task.due_date"
                                            class="text-sm"
                                            :class="isOverdue(task) ? 'font-medium text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'"
                                        >
                                            {{ formatDate(task.due_date) }}
                                        </span>
                                        <span v-else class="text-sm text-gray-300 dark:text-gray-600">&mdash;</span>
                                    </td>

                                    <!-- Created -->
                                    <td class="px-4 py-3">
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDate(task.created_at) }}
                                        </span>
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-4 py-3 text-right">
                                        <Link
                                            v-if="task.project"
                                            :href="route('projects.show', task.project.slug)"
                                            class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No tasks found</h3>
                        <p v-if="hasActiveFilters" class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Try adjusting your filters.
                        </p>
                        <p v-else class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Tasks will appear here when created in projects.
                        </p>
                    </div>

                    <!-- Pagination -->
                    <div v-if="pagination.last_page > 1" class="flex items-center justify-between border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Showing page {{ pagination.current_page }} of {{ pagination.last_page }}
                            ({{ pagination.total }} tasks)
                        </div>
                        <div class="flex gap-2">
                            <button
                                v-if="pagination.current_page > 1"
                                @click="router.get(route('tasks.index'), { ...filters, page: pagination.current_page - 1 }, { preserveState: true })"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm transition hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
                            >
                                Previous
                            </button>
                            <button
                                v-if="pagination.current_page < pagination.last_page"
                                @click="router.get(route('tasks.index'), { ...filters, page: pagination.current_page + 1 }, { preserveState: true })"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm transition hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-700"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
