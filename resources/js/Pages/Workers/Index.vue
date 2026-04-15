<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import WorkerStatusCard from '@/Components/Workers/WorkerStatusCard.vue';
import JobStatusBadge from '@/Components/Workers/JobStatusBadge.vue';
import JobFormModal from '@/Components/Workers/JobFormModal.vue';

interface Worker {
    id: number;
    name: string;
    machine_id: string;
    status: 'online' | 'offline' | 'busy';
    is_online: boolean;
    version?: string;
    os_info?: string;
    max_parallel_jobs: number;
    current_jobs?: number[];
    last_heartbeat_at?: string;
}

interface Project {
    id: number;
    name: string;
    slug: string;
}

interface Job {
    id: number;
    title: string;
    type: string;
    status: string;
    priority: string;
    created_at: string;
    started_at?: string;
    completed_at?: string;
    duration_seconds?: number;
    project?: { id: number; name: string; slug: string };
    creator?: { id: number; name: string };
    worker?: { id: number; name: string; machine_id: string };
}

interface Stats {
    total_jobs: number;
    pending: number;
    queued: number;
    running: number;
    done: number;
    failed: number;
}

const props = defineProps<{
    workers: Worker[];
    jobs: { data: Job[]; meta?: any };
    projects: Project[];
    stats: Stats;
    filters: { status?: string; project?: string };
}>();

const showJobForm = ref(false);
const statusFilter = ref(props.filters.status || '');
const projectFilter = ref(props.filters.project || '');

const onlineWorkers = computed(() => props.workers.filter(w => w.is_online));

function applyFilters() {
    const params: Record<string, string> = {};
    if (statusFilter.value) params.status = statusFilter.value;
    if (projectFilter.value) params.project = projectFilter.value;
    router.get(route('workers.index'), params, { preserveState: true });
}

function approveJob(id: number) {
    router.post(route('workers.jobs.approve', id), {}, { preserveScroll: true });
}

function cancelJob(id: number) {
    if (confirm('Cancel this job?')) {
        router.post(route('workers.jobs.cancel', id), {}, { preserveScroll: true });
    }
}

function retryJob(id: number) {
    router.post(route('workers.jobs.retry', id), {}, { preserveScroll: true });
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}

function priorityClass(priority: string): string {
    const classes: Record<string, string> = {
        low: 'text-gray-500',
        medium: 'text-blue-600 dark:text-blue-400',
        high: 'text-orange-600 dark:text-orange-400',
        critical: 'text-red-600 dark:text-red-400 font-bold',
    };
    return classes[priority] || '';
}
</script>

<template>
    <Head title="Workers" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Workers
                </h2>
                <button
                    @click="showJobForm = true"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Job
                </button>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Worker Status Panel -->
                <div class="mb-6">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Workers ({{ onlineWorkers.length }}/{{ workers.length }} online)
                    </h3>
                    <div v-if="workers.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <WorkerStatusCard v-for="w in workers" :key="w.id" :worker="w" />
                    </div>
                    <div v-else class="rounded-lg bg-white p-6 text-center shadow dark:bg-gray-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No workers registered yet. Start the Python worker to connect.
                        </p>
                    </div>
                </div>

                <!-- Stats Row -->
                <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div class="rounded-lg bg-white p-4 text-center shadow dark:bg-gray-800">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_jobs }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Total</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 text-center shadow dark:bg-gray-800">
                        <div class="text-2xl font-bold text-amber-600">{{ stats.pending }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Pending</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 text-center shadow dark:bg-gray-800">
                        <div class="text-2xl font-bold text-yellow-600">{{ stats.queued }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Queued</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 text-center shadow dark:bg-gray-800">
                        <div class="text-2xl font-bold text-indigo-600">{{ stats.running }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Running</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 text-center shadow dark:bg-gray-800">
                        <div class="text-2xl font-bold text-green-600">{{ stats.done }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Done</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 text-center shadow dark:bg-gray-800">
                        <div class="text-2xl font-bold text-red-600">{{ stats.failed }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Failed</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="mb-4 flex items-center gap-4">
                    <select
                        v-model="statusFilter"
                        @change="applyFilters"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                    >
                        <option value="">All Statuses</option>
                        <option value="pending_approval">Pending Approval</option>
                        <option value="queued">Queued</option>
                        <option value="running">Running</option>
                        <option value="done">Done</option>
                        <option value="failed">Failed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>

                    <select
                        v-model="projectFilter"
                        @change="applyFilters"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                    >
                        <option value="">All Projects</option>
                        <option v-for="p in projects" :key="p.id" :value="p.slug">{{ p.name }}</option>
                    </select>
                </div>

                <!-- Jobs List -->
                <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <div v-if="jobs.data.length > 0">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Job</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Project</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Worker</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Created</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="job in jobs.data"
                                    :key="job.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-750"
                                >
                                    <td class="px-6 py-4">
                                        <Link
                                            :href="route('workers.jobs.show', job.id)"
                                            class="font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                                        >
                                            {{ job.title }}
                                        </Link>
                                        <div class="flex items-center gap-2 mt-1">
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ job.type.replace(/_/g, ' ') }}</span>
                                            <span :class="['text-xs', priorityClass(job.priority)]">{{ job.priority }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <JobStatusBadge :status="job.status" />
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        <Link
                                            v-if="job.project"
                                            :href="route('projects.show', job.project.slug)"
                                            class="hover:text-indigo-600 dark:hover:text-indigo-400"
                                        >
                                            {{ job.project.name }}
                                        </Link>
                                        <span v-else>-</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ job.worker?.name || '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ formatDate(job.created_at) }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                v-if="job.status === 'pending_approval'"
                                                @click="approveJob(job.id)"
                                                class="text-xs font-medium text-green-600 hover:text-green-800 dark:text-green-400"
                                            >
                                                Approve
                                            </button>
                                            <button
                                                v-if="!['done', 'failed', 'cancelled'].includes(job.status)"
                                                @click="cancelJob(job.id)"
                                                class="text-xs font-medium text-red-600 hover:text-red-800 dark:text-red-400"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                v-if="['failed', 'cancelled'].includes(job.status)"
                                                @click="retryJob(job.id)"
                                                class="text-xs font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                            >
                                                Retry
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No jobs yet</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create a new job to get started.</p>
                        <button
                            @click="showJobForm = true"
                            class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            Create First Job
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <JobFormModal
            :show="showJobForm"
            :projects="projects"
            @close="showJobForm = false"
        />
    </AuthenticatedLayout>
</template>
