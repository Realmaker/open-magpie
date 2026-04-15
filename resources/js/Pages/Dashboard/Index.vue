<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface Project {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    status: 'active' | 'paused' | 'completed' | 'archived';
    priority: string;
    health_score: number;
    last_activity_at: string | null;
    events_count: number;
    documents_count: number;
    tasks_count: number;
}

interface Event {
    id: number;
    type: string;
    title: string;
    created_at: string;
    project: {
        id: number;
        name: string;
        slug: string;
    };
}

interface Stats {
    projects: number;
    openTasks: number;
    eventsToday: number;
    documents: number;
    completedTasks: number;
    staleProjects: number;
}

interface WorkerStats {
    workersOnline: number;
    jobsRunning: number;
    jobsPending: number;
}

defineProps<{
    projects: Project[];
    recentEvents: Event[];
    stats: Stats;
    workerStats?: WorkerStats;
}>();

// Helper function to format relative time
function relativeTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)} days ago`;
    return date.toLocaleDateString();
}

// Helper function to get status badge classes
function statusBadgeClass(status: string): string {
    const classes = {
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        archived: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    };
    return classes[status as keyof typeof classes] || classes.archived;
}

// Helper function to get health score classes
function healthScoreClass(score: number): string {
    if (score > 70) return 'text-green-600 dark:text-green-400';
    if (score >= 40) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
}

// Helper function to get event type badge classes
function eventTypeBadge(type: string): { class: string; label: string } {
    const badges: Record<string, { class: string; label: string }> = {
        changelog: { class: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200', label: 'Changelog' },
        documentation: { class: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200', label: 'Docs' },
        decision: { class: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200', label: 'Decision' },
        milestone: { class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', label: 'Milestone' },
        note: { class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200', label: 'Note' },
        task_update: { class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200', label: 'Task' },
        session_summary: { class: 'bg-violet-100 text-violet-800 dark:bg-violet-900 dark:text-violet-200', label: 'Session' },
        deployment: { class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200', label: 'Deploy' },
        issue: { class: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200', label: 'Issue' },
        review: { class: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200', label: 'Review' },
    };
    return badges[type] || { class: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200', label: type };
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <!-- Stats Overview -->
                <div class="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <!-- Total Projects -->
                    <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Total Projects
                                        </dt>
                                        <dd class="text-3xl font-semibold text-gray-900 dark:text-white">
                                            {{ stats.projects }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Open Tasks -->
                    <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Open Tasks
                                        </dt>
                                        <dd class="text-3xl font-semibold text-gray-900 dark:text-white">
                                            {{ stats.openTasks }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Events Today -->
                    <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Events Today
                                        </dt>
                                        <dd class="text-3xl font-semibold text-gray-900 dark:text-white">
                                            {{ stats.eventsToday }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Stats Row -->
                <div class="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <!-- Documents -->
                    <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Documents
                                        </dt>
                                        <dd class="text-3xl font-semibold text-gray-900 dark:text-white">
                                            {{ stats.documents }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Completed Tasks -->
                    <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-8 w-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Completed Tasks
                                        </dt>
                                        <dd class="text-3xl font-semibold text-gray-900 dark:text-white">
                                            {{ stats.completedTasks }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stale Projects -->
                    <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                        <div class="p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg
                                        class="h-8 w-8"
                                        :class="stats.staleProjects > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500'"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="truncate text-sm font-medium text-gray-500 dark:text-gray-400">
                                            Stale Projects
                                        </dt>
                                        <dd
                                            class="text-3xl font-semibold"
                                            :class="stats.staleProjects > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white'"
                                        >
                                            {{ stats.staleProjects }}
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Worker Stats (if available) -->
                <div v-if="workerStats" class="mb-8">
                    <Link
                        :href="route('workers.index')"
                        class="block overflow-hidden rounded-lg bg-white shadow transition hover:shadow-lg dark:bg-gray-800"
                    >
                        <div class="flex items-center justify-between p-4">
                            <div class="flex items-center gap-6">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex h-3 w-3 rounded-full"
                                        :class="workerStats.workersOnline > 0 ? 'bg-green-500' : 'bg-gray-400'"
                                    ></span>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ workerStats.workersOnline }} Worker{{ workerStats.workersOnline !== 1 ? 's' : '' }} online
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ workerStats.jobsRunning }} running
                                </div>
                                <div v-if="workerStats.jobsPending > 0" class="text-sm text-amber-600 dark:text-amber-400">
                                    {{ workerStats.jobsPending }} pending approval
                                </div>
                            </div>
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </Link>
                </div>

                <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                    <!-- Active Projects Grid -->
                    <div class="lg:col-span-2">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Active Projects
                            </h3>
                            <Link
                                :href="route('projects.index')"
                                class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                            >
                                View all
                            </Link>
                        </div>

                        <div v-if="projects.length > 0" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <Link
                                v-for="project in projects"
                                :key="project.id"
                                :href="route('projects.show', project.slug)"
                                class="block rounded-lg bg-white p-6 shadow transition hover:shadow-lg dark:bg-gray-800 dark:hover:bg-gray-750"
                            >
                                <div class="mb-3 flex items-start justify-between">
                                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ project.name }}
                                    </h4>
                                    <span
                                        :class="statusBadgeClass(project.status)"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                                    >
                                        {{ project.status }}
                                    </span>
                                </div>

                                <p v-if="project.description" class="mb-3 line-clamp-2 text-sm text-gray-600 dark:text-gray-400">
                                    {{ project.description }}
                                </p>

                                <div class="mb-3 flex items-center">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Health Score:</span>
                                    <span :class="healthScoreClass(project.health_score)" class="ml-2 text-lg font-bold">
                                        {{ project.health_score }}
                                    </span>
                                </div>

                                <div class="mb-3 text-sm text-gray-500 dark:text-gray-400">
                                    <span v-if="project.last_activity_at">
                                        Last activity: {{ relativeTime(project.last_activity_at) }}
                                    </span>
                                    <span v-else>No recent activity</span>
                                </div>

                                <div class="flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="flex items-center">
                                        <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                        {{ project.events_count }} events
                                    </span>
                                    <span class="flex items-center">
                                        <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        {{ project.documents_count }} docs
                                    </span>
                                    <span class="flex items-center">
                                        <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        {{ project.tasks_count }} tasks
                                    </span>
                                </div>
                            </Link>
                        </div>

                        <div v-else class="rounded-lg bg-white p-8 text-center shadow dark:bg-gray-800">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No projects</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new project.</p>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="lg:col-span-1">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                Recent Activity
                            </h3>
                        </div>

                        <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                            <ul v-if="recentEvents.length > 0" class="divide-y divide-gray-200 dark:divide-gray-700">
                                <li v-for="event in recentEvents" :key="event.id" class="p-4 transition hover:bg-gray-50 dark:hover:bg-gray-750">
                                    <Link :href="route('projects.show', event.project.slug)" class="block">
                                        <div class="mb-2 flex items-center justify-between">
                                            <span
                                                :class="eventTypeBadge(event.type).class"
                                                class="inline-flex rounded px-2 py-1 text-xs font-semibold"
                                            >
                                                {{ eventTypeBadge(event.type).label }}
                                            </span>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ relativeTime(event.created_at) }}
                                            </span>
                                        </div>
                                        <p class="mb-1 line-clamp-2 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ event.title }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ event.project.name }}
                                        </p>
                                    </Link>
                                </li>
                            </ul>

                            <div v-else class="p-8 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No recent activity</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
