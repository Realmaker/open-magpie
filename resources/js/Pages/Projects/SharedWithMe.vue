<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface Project {
    id: number;
    name: string;
    slug: string;
    description?: string;
    status: string;
    priority: string;
    health_score: number;
    last_activity_at?: string;
    events_count: number;
    documents_count: number;
    tasks_count: number;
}

interface Share {
    id: number;
    permission: 'viewer' | 'editor' | 'admin';
    accepted_at: string;
    project: Project;
    shared_by?: { id: number; name: string; email: string };
}

const props = defineProps<{
    shares: Share[];
}>();

const permissionLabel = (p: string) => {
    const labels: Record<string, string> = { viewer: 'Betrachter', editor: 'Bearbeiter', admin: 'Administrator' };
    return labels[p] || p;
};

const permissionColor = (p: string) => {
    const colors: Record<string, string> = {
        viewer: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        editor: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
        admin: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
    };
    return colors[p] || '';
};

const statusColor = (s: string) => {
    const colors: Record<string, string> = {
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        archived: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    };
    return colors[s] || 'bg-gray-100 text-gray-800';
};

const getHealthColor = (score: number) => {
    if (score >= 80) return 'text-green-600';
    if (score >= 60) return 'text-yellow-600';
    if (score >= 40) return 'text-orange-600';
    return 'text-red-600';
};

const formatDate = (d: string) => {
    return new Date(d).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
};
</script>

<template>
    <Head title="Geteilte Projekte" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Geteilte Projekte
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div v-if="shares.length > 0" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="share in shares"
                        :key="share.id"
                        :href="route('projects.show', share.project.slug)"
                        class="block bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition-shadow p-5"
                    >
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-3">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 truncate mr-3">
                                {{ share.project.name }}
                            </h3>
                            <span :class="['shrink-0 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', permissionColor(share.permission)]">
                                {{ permissionLabel(share.permission) }}
                            </span>
                        </div>

                        <!-- Badges -->
                        <div class="flex gap-2 mb-3">
                            <span :class="['px-2 py-0.5 text-xs font-medium rounded', statusColor(share.project.status)]">
                                {{ share.project.status }}
                            </span>
                        </div>

                        <!-- Description -->
                        <p v-if="share.project.description" class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 mb-3">
                            {{ share.project.description }}
                        </p>

                        <!-- Shared by -->
                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-3">
                            Geteilt von <span class="font-medium">{{ share.shared_by?.name }}</span>
                        </div>

                        <!-- Stats -->
                        <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 pt-3 border-t border-gray-100 dark:border-gray-700">
                            <div class="flex gap-4">
                                <span>{{ share.project.events_count }} Events</span>
                                <span>{{ share.project.documents_count }} Docs</span>
                                <span>{{ share.project.tasks_count }} Tasks</span>
                            </div>
                            <span :class="['font-bold text-sm', getHealthColor(share.project.health_score)]">
                                {{ share.project.health_score }}%
                            </span>
                        </div>
                    </Link>
                </div>

                <!-- Empty State -->
                <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Keine geteilten Projekte</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Wenn jemand ein Projekt mit dir teilt, erscheint es hier.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
