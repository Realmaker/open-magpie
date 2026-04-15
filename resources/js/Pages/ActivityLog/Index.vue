<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { computed } from 'vue';

interface User {
    id: number;
    name: string;
}

interface Project {
    id: number;
    name: string;
    slug: string;
}

interface ActivityLogEntry {
    id: number;
    action: string;
    subject_type: string;
    subject_id: number;
    properties?: any;
    created_at: string;
    user?: User;
    project?: Project;
}

interface Props {
    logs: {
        data: ActivityLogEntry[];
        links: any;
    };
    filters: {
        project_id?: string;
        action?: string;
    };
}

const props = defineProps<Props>();

const getActionIcon = (action: string): string => {
    const icons: Record<string, string> = {
        created: 'M12 4v16m8-8H4',
        bulk_created: 'M12 4v16m8-8H4',
        updated: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        version_created: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
        deleted: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
        status_changed: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
    };
    return icons[action] || 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
};

const getActionColor = (action: string): string => {
    const colors: Record<string, string> = {
        created: 'text-green-600 bg-green-100',
        bulk_created: 'text-green-600 bg-green-100',
        updated: 'text-blue-600 bg-blue-100',
        version_created: 'text-blue-600 bg-blue-100',
        deleted: 'text-red-600 bg-red-100',
        status_changed: 'text-yellow-600 bg-yellow-100',
    };
    return colors[action] || 'text-gray-600 bg-gray-100';
};

const getModelName = (subjectType: string): string => {
    const parts = subjectType.split('\\');
    return parts[parts.length - 1];
};

const getActionDescription = (log: ActivityLogEntry): string => {
    const modelName = getModelName(log.subject_type);
    const actionMap: Record<string, string> = {
        created: 'Created',
        bulk_created: 'Bulk created',
        updated: 'Updated',
        version_created: 'Created new version of',
        deleted: 'Deleted',
        status_changed: 'Changed status of',
    };
    const actionText = actionMap[log.action] || log.action;
    return `${actionText} ${modelName.toLowerCase()}`;
};

const formatRelativeTime = (dateString: string): string => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);

    if (diffSec < 60) return 'just now';
    if (diffMin < 60) return `${diffMin} minute${diffMin !== 1 ? 's' : ''} ago`;
    if (diffHour < 24) return `${diffHour} hour${diffHour !== 1 ? 's' : ''} ago`;
    if (diffDay < 7) return `${diffDay} day${diffDay !== 1 ? 's' : ''} ago`;

    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const formatProperties = (properties: any): string | null => {
    if (!properties || Object.keys(properties).length === 0) return null;

    const details: string[] = [];

    if (properties.old && properties.new) {
        Object.keys(properties.new).forEach(key => {
            if (properties.old[key] !== properties.new[key]) {
                details.push(`${key}: ${properties.old[key]} → ${properties.new[key]}`);
            }
        });
    }

    if (properties.count) {
        details.push(`Count: ${properties.count}`);
    }

    return details.length > 0 ? details.join(', ') : null;
};
</script>

<template>
    <Head title="Activity Log" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 border-b border-gray-200">
                        <h1 class="text-2xl font-semibold text-gray-900">Activity Log</h1>
                        <p class="mt-1 text-sm text-gray-600">
                            Chronological record of all actions performed across projects
                        </p>
                    </div>

                    <div class="p-6">
                        <div v-if="logs.data.length === 0" class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No activity yet</h3>
                            <p class="mt-1 text-sm text-gray-500">Get started by creating a project.</p>
                        </div>

                        <div v-else class="flow-root">
                            <ul role="list" class="-mb-8">
                                <li v-for="(log, logIdx) in logs.data" :key="log.id">
                                    <div class="relative pb-8">
                                        <span
                                            v-if="logIdx !== logs.data.length - 1"
                                            class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200"
                                            aria-hidden="true"
                                        ></span>

                                        <div class="relative flex items-start space-x-3">
                                            <div class="relative">
                                                <div
                                                    :class="[
                                                        getActionColor(log.action),
                                                        'h-10 w-10 rounded-full flex items-center justify-center ring-8 ring-white'
                                                    ]"
                                                >
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getActionIcon(log.action)" />
                                                    </svg>
                                                </div>
                                            </div>

                                            <div class="min-w-0 flex-1">
                                                <div>
                                                    <div class="text-sm">
                                                        <span class="font-medium text-gray-900">
                                                            {{ log.user?.name || 'System' }}
                                                        </span>
                                                        <span class="text-gray-500 ml-1">{{ getActionDescription(log) }}</span>
                                                        <span class="text-gray-400 ml-1">#{{ log.subject_id }}</span>
                                                    </div>
                                                    <div class="mt-1 flex items-center space-x-2 text-sm text-gray-500">
                                                        <span>{{ formatRelativeTime(log.created_at) }}</span>
                                                        <span v-if="log.project" class="flex items-center">
                                                            <span class="mx-2">•</span>
                                                            <Link
                                                                :href="`/projects/${log.project.slug}`"
                                                                class="text-indigo-600 hover:text-indigo-900 font-medium"
                                                            >
                                                                {{ log.project.name }}
                                                            </Link>
                                                        </span>
                                                    </div>
                                                    <div
                                                        v-if="formatProperties(log.properties)"
                                                        class="mt-2 text-xs text-gray-600 bg-gray-50 px-3 py-2 rounded-md"
                                                    >
                                                        {{ formatProperties(log.properties) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <div v-if="logs.data.length > 0" class="mt-6">
                            <Pagination :links="logs.links" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
