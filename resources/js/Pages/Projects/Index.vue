<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

interface Project {
    id: number;
    name: string;
    slug: string;
    description?: string;
    status: 'active' | 'paused' | 'completed' | 'archived';
    priority: 'low' | 'medium' | 'high' | 'critical';
    health_score: number;
    last_activity_at?: string;
    events_count: number;
    documents_count: number;
    tasks_count: number;
}

interface Props {
    projects: {
        data: Project[];
        links: any;
        meta: any;
    };
    filters: {
        search?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const searchQuery = ref(props.filters.search || '');
const activeStatus = ref(props.filters.status || 'all');
const showCreateModal = ref(false);

const createForm = useForm({
    name: '',
    description: '',
    status: 'active',
    priority: 'medium',
    category: '',
    repository_url: '',
    tech_stack: '',
});

const submitProject = () => {
    createForm.post(route('projects.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const statusOptions = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'paused', label: 'Paused' },
    { value: 'completed', label: 'Completed' },
    { value: 'archived', label: 'Archived' },
];

const filterProjects = (status: string) => {
    activeStatus.value = status;
    const params: any = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (status !== 'all') params.status = status;

    router.get(route('projects.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const searchProjects = () => {
    const params: any = {};
    if (searchQuery.value) params.search = searchQuery.value;
    if (activeStatus.value !== 'all') params.status = activeStatus.value;

    router.get(route('projects.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const getStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        paused: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        archived: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const getPriorityColor = (priority: string) => {
    const colors: Record<string, string> = {
        low: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        medium: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        high: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        critical: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };
    return colors[priority] || 'bg-gray-100 text-gray-800';
};

const getHealthColor = (score: number) => {
    if (score >= 80) return 'text-green-600';
    if (score >= 60) return 'text-yellow-600';
    if (score >= 40) return 'text-orange-600';
    return 'text-red-600';
};

const formatDate = (date?: string) => {
    if (!date) return 'Never';
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Projects" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6 flex items-center justify-between">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100">Projects</h1>
                    <button
                        @click="showCreateModal = true"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        + Neues Projekt
                    </button>
                </div>

                <!-- Search and Filters -->
                <div class="mb-6 space-y-4">
                    <!-- Search Input -->
                    <div class="flex gap-2">
                        <input
                            v-model="searchQuery"
                            @keyup.enter="searchProjects"
                            type="text"
                            placeholder="Search projects..."
                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-100"
                        />
                        <button
                            @click="searchProjects"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            Search
                        </button>
                    </div>

                    <!-- Status Filter Buttons -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in statusOptions"
                            :key="option.value"
                            @click="filterProjects(option.value)"
                            :class="[
                                'px-4 py-2 rounded-md text-sm font-medium transition-colors',
                                activeStatus === option.value
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700 dark:hover:bg-gray-700'
                            ]"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <!-- Projects Grid -->
                <div v-if="projects.data.length > 0" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="project in projects.data"
                        :key="project.id"
                        :href="route('projects.show', project.slug)"
                        class="block bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition-shadow p-6"
                    >
                        <!-- Project Header -->
                        <div class="mb-4">
                            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">
                                {{ project.name }}
                            </h3>
                            <div class="flex gap-2 mb-3">
                                <span :class="['px-2 py-1 text-xs font-medium rounded', getStatusColor(project.status)]">
                                    {{ project.status }}
                                </span>
                                <span :class="['px-2 py-1 text-xs font-medium rounded', getPriorityColor(project.priority)]">
                                    {{ project.priority }}
                                </span>
                            </div>
                            <p v-if="project.description" class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
                                {{ project.description }}
                            </p>
                        </div>

                        <!-- Health Score -->
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Health Score</span>
                                <span :class="['text-sm font-semibold', getHealthColor(project.health_score)]">
                                    {{ project.health_score }}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div
                                    :class="['h-2 rounded-full transition-all', getHealthColor(project.health_score).replace('text-', 'bg-')]"
                                    :style="{ width: `${project.health_score}%` }"
                                ></div>
                            </div>
                        </div>

                        <!-- Stats -->
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ project.events_count }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Events</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ project.documents_count }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Docs</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ project.tasks_count }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Tasks</div>
                            </div>
                        </div>

                        <!-- Last Activity -->
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Last activity: {{ formatDate(project.last_activity_at) }}
                        </div>
                    </Link>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No projects found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ searchQuery || activeStatus !== 'all' ? 'Try adjusting your filters' : 'Get started by creating a new project.' }}
                    </p>
                </div>

                <!-- Pagination -->
                <div v-if="projects.data.length > 0 && projects.links.length > 3" class="mt-6">
                    <div class="flex flex-wrap gap-1">
                        <component
                            v-for="(link, index) in projects.links"
                            :key="index"
                            :is="link.url ? Link : 'span'"
                            :href="link.url"
                            preserve-state
                            preserve-scroll
                            :class="[
                                'px-3 py-2 text-sm rounded-md',
                                link.active
                                    ? 'bg-indigo-600 text-white'
                                    : link.url
                                    ? 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700'
                                    : 'bg-gray-100 text-gray-400 dark:bg-gray-900 dark:text-gray-600 cursor-not-allowed'
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Project Modal -->
        <teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto">
                <div class="fixed inset-0 bg-black bg-opacity-50" @click="showCreateModal = false"></div>
                <div class="relative w-full max-w-lg rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
                    <h2 class="mb-4 text-xl font-semibold text-gray-900 dark:text-gray-100">Neues Projekt</h2>
                    <form @submit.prevent="submitProject" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name *</label>
                            <input v-model="createForm.name" type="text" required
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                            <p v-if="createForm.errors.name" class="mt-1 text-sm text-red-600">{{ createForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Beschreibung</label>
                            <textarea v-model="createForm.description" rows="3"
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                <select v-model="createForm.status"
                                    class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                                    <option value="active">Active</option>
                                    <option value="paused">Paused</option>
                                    <option value="completed">Completed</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Prioritaet</label>
                                <select v-model="createForm.priority"
                                    class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kategorie</label>
                            <input v-model="createForm.category" type="text" placeholder="z.B. Kundenprojekt, Internes Tool..."
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Repository URL</label>
                            <input v-model="createForm.repository_url" type="url" placeholder="https://github.com/..."
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tech Stack</label>
                            <input v-model="createForm.tech_stack" type="text" placeholder="PHP, Laravel, Vue.js (kommagetrennt)"
                                class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100" />
                        </div>
                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" @click="showCreateModal = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-600">
                                Abbrechen
                            </button>
                            <button type="submit" :disabled="createForm.processing"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                Projekt erstellen
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>
    </AuthenticatedLayout>
</template>
