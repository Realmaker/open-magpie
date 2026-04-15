<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import JobStatusBadge from '@/Components/Workers/JobStatusBadge.vue';

interface Job {
    id: number;
    title: string;
    description?: string;
    prompt: string;
    type: string;
    status: string;
    priority: string;
    project_path?: string;
    working_directory?: string;
    output?: string;
    error_output?: string;
    exit_code?: number;
    duration_seconds?: number;
    result_summary?: string;
    approved_at?: string;
    claimed_at?: string;
    started_at?: string;
    completed_at?: string;
    created_at: string;
    project?: { id: number; name: string; slug: string };
    creator?: { id: number; name: string };
    approver?: { id: number; name: string };
    worker?: { id: number; name: string; machine_id: string };
}

const props = defineProps<{
    job: Job;
}>();

function approveJob() {
    router.post(route('workers.jobs.approve', props.job.id), {}, { preserveScroll: true });
}

function cancelJob() {
    if (confirm('Cancel this job?')) {
        router.post(route('workers.jobs.cancel', props.job.id), {}, { preserveScroll: true });
    }
}

function retryJob() {
    router.post(route('workers.jobs.retry', props.job.id), {}, { preserveScroll: true });
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
    });
}

function formatDuration(seconds: number): string {
    if (seconds < 60) return `${seconds}s`;
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}m ${s}s`;
}

function priorityClass(priority: string): string {
    const classes: Record<string, string> = {
        low: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        medium: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        high: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        critical: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };
    return classes[priority] || '';
}
</script>

<template>
    <Head :title="`Job: ${job.title}`" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
                <!-- Back Button -->
                <div class="mb-4">
                    <Link
                        :href="route('workers.index')"
                        class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                    >
                        <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Workers
                    </Link>
                </div>

                <!-- Header -->
                <div class="mb-6 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div class="flex items-start justify-between">
                        <div>
                            <h1 class="mb-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {{ job.title }}
                            </h1>
                            <div class="flex items-center gap-2">
                                <JobStatusBadge :status="job.status" />
                                <span :class="['rounded px-2 py-1 text-xs font-medium', priorityClass(job.priority)]">
                                    {{ job.priority }}
                                </span>
                                <span class="rounded bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                    {{ job.type.replace(/_/g, ' ') }}
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                v-if="job.status === 'pending_approval'"
                                @click="approveJob"
                                class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                            >
                                Approve
                            </button>
                            <button
                                v-if="!['done', 'failed', 'cancelled'].includes(job.status)"
                                @click="cancelJob"
                                class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
                            >
                                Cancel
                            </button>
                            <button
                                v-if="['failed', 'cancelled'].includes(job.status)"
                                @click="retryJob"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                Retry
                            </button>
                        </div>
                    </div>

                    <p v-if="job.description" class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                        {{ job.description }}
                    </p>
                </div>

                <!-- Metadata -->
                <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Project</div>
                        <Link
                            v-if="job.project"
                            :href="route('projects.show', job.project.slug)"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                        >
                            {{ job.project.name }}
                        </Link>
                        <div v-else class="text-sm text-gray-500">-</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Created by</div>
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ job.creator?.name || '-' }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Worker</div>
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ job.worker?.name || '-' }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <div class="text-xs text-gray-500 dark:text-gray-400">Duration</div>
                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ job.duration_seconds ? formatDuration(job.duration_seconds) : '-' }}
                        </div>
                    </div>
                </div>

                <!-- Timestamps -->
                <div class="mb-6 rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                    <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Timeline</h3>
                    <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <span class="text-gray-500 dark:text-gray-400">Created:</span>
                            <span class="ml-1 text-gray-900 dark:text-gray-100">{{ formatDate(job.created_at) }}</span>
                        </div>
                        <div v-if="job.approved_at">
                            <span class="text-gray-500 dark:text-gray-400">Approved:</span>
                            <span class="ml-1 text-gray-900 dark:text-gray-100">{{ formatDate(job.approved_at) }}</span>
                        </div>
                        <div v-if="job.claimed_at">
                            <span class="text-gray-500 dark:text-gray-400">Claimed:</span>
                            <span class="ml-1 text-gray-900 dark:text-gray-100">{{ formatDate(job.claimed_at) }}</span>
                        </div>
                        <div v-if="job.started_at">
                            <span class="text-gray-500 dark:text-gray-400">Started:</span>
                            <span class="ml-1 text-gray-900 dark:text-gray-100">{{ formatDate(job.started_at) }}</span>
                        </div>
                        <div v-if="job.completed_at">
                            <span class="text-gray-500 dark:text-gray-400">Completed:</span>
                            <span class="ml-1 text-gray-900 dark:text-gray-100">{{ formatDate(job.completed_at) }}</span>
                        </div>
                        <div v-if="job.exit_code !== null && job.exit_code !== undefined">
                            <span class="text-gray-500 dark:text-gray-400">Exit Code:</span>
                            <span
                                class="ml-1 font-mono"
                                :class="job.exit_code === 0 ? 'text-green-600' : 'text-red-600'"
                            >
                                {{ job.exit_code }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Result Summary -->
                <div v-if="job.result_summary" class="mb-6 rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Result Summary</h3>
                    <div class="whitespace-pre-wrap text-sm text-gray-900 dark:text-gray-100">{{ job.result_summary }}</div>
                </div>

                <!-- Prompt -->
                <div class="mb-6 rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Prompt</h3>
                    <pre class="max-h-64 overflow-auto rounded bg-gray-50 p-4 text-sm text-gray-900 dark:bg-gray-900 dark:text-gray-100">{{ job.prompt }}</pre>
                </div>

                <!-- Output -->
                <div v-if="job.output" class="mb-6 rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                    <h3 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Output</h3>
                    <pre class="max-h-96 overflow-auto rounded bg-gray-50 p-4 text-xs text-gray-900 dark:bg-gray-900 dark:text-gray-100">{{ job.output }}</pre>
                </div>

                <!-- Error Output -->
                <div v-if="job.error_output" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 shadow dark:border-red-800 dark:bg-red-900/20">
                    <h3 class="mb-2 text-sm font-semibold text-red-700 dark:text-red-400">Error Output</h3>
                    <pre class="max-h-64 overflow-auto rounded bg-white p-4 text-xs text-red-800 dark:bg-gray-900 dark:text-red-300">{{ job.error_output }}</pre>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
