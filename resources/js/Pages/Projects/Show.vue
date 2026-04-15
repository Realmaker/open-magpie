<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MarkdownRenderer from '@/Components/MarkdownRenderer.vue';
import DocumentViewerModal from '@/Components/Documents/DocumentViewerModal.vue';
import DocumentFormModal from '@/Components/Documents/DocumentFormModal.vue';
import TaskFormModal from '@/Components/Tasks/TaskFormModal.vue';
import TaskDetailModal from '@/Components/Tasks/TaskDetailModal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import NoteThread from '@/Components/Notes/NoteThread.vue';
import NoteForm from '@/Components/Notes/NoteForm.vue';
import SummaryBadge from '@/Components/Ai/SummaryBadge.vue';
import JobStatusBadge from '@/Components/Workers/JobStatusBadge.vue';
import ShareModal from '@/Components/Shares/ShareModal.vue';
import PermissionBadge from '@/Components/Shares/PermissionBadge.vue';
import FileTree from '@/Components/Snapshots/FileTree.vue';
import SnapshotUploadModal from '@/Components/Snapshots/SnapshotUploadModal.vue';

interface Project {
    id: number;
    name: string;
    slug: string;
    description?: string;
    status: 'active' | 'paused' | 'completed' | 'archived';
    priority: 'low' | 'medium' | 'high' | 'critical';
    category?: string;
    health_score: number;
    tech_stack?: string[];
    repository_url?: string;
    last_activity_at?: string;
    events_count: number;
    documents_count: number;
    tasks_count: number;
}

interface Event {
    id: number;
    type: string;
    title: string;
    content: string;
    summary?: string;
    created_at: string;
    user?: {
        name: string;
    };
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
}

interface Document {
    id: number;
    title: string;
    slug: string;
    category: string;
    content?: string;
    current_version: number;
    updated_at: string;
}

interface Note {
    id: number;
    content: string;
    source: string;
    user_id: number;
    user?: { id: number; name: string };
    parent_id?: number | null;
    replies?: Note[];
    created_at: string;
    updated_at: string;
}

interface WorkerJob {
    id: number;
    title: string;
    type: string;
    status: string;
    priority: string;
    created_at: string;
    completed_at?: string;
    duration_seconds?: number;
    creator?: { id: number; name: string };
    worker?: { id: number; name: string; machine_id: string };
}

interface Share {
    id: number;
    shared_with_email: string;
    permission: 'viewer' | 'editor' | 'admin';
    accepted_at: string | null;
    expires_at: string | null;
    is_pending: boolean;
    is_expired: boolean;
    created_at: string;
    shared_by?: { id: number; name: string; email: string };
    shared_with_user?: { id: number; name: string; email: string } | null;
}

interface SnapshotVersion {
    id: number;
    version: number;
    human_size: string;
    file_count: number;
    change_note?: string;
    source: string;
    uploaded_by?: string;
    created_at: string;
}

interface LatestSnapshot {
    version: number;
    file_tree: any[];
    file_count: number;
    human_size: string;
    change_note?: string;
    created_at: string;
}

interface Props {
    project: Project;
    events: Event[];
    tasks: Task[];
    documents: Document[];
    notes: Note[];
    workerJobs?: WorkerJob[];
    userPermission?: 'owner' | 'admin' | 'editor' | 'viewer';
    shares?: Share[];
    latestSnapshot?: LatestSnapshot | null;
    snapshotVersions?: SnapshotVersion[];
    installNotes?: string | null;
}

const props = defineProps<Props>();
const page = usePage();
const currentUserId = computed(() => (page.props.auth as any)?.user?.id);

const permission = computed(() => props.userPermission || 'owner');
const canEdit = computed(() => ['owner', 'admin', 'editor'].includes(permission.value));
const canAdmin = computed(() => ['owner', 'admin'].includes(permission.value));
const canDelete = computed(() => ['owner', 'admin'].includes(permission.value));
const canShare = computed(() => ['owner', 'admin'].includes(permission.value));
const isShared = computed(() => permission.value !== 'owner');

const showShareModal = ref(false);
const showSnapshotUpload = ref(false);
const editingInstallNotes = ref(false);
const installNotesText = ref(props.installNotes || '');

const saveInstallNotes = () => {
    router.patch(route('projects.install-notes.update', { slug: props.project.slug }), {
        install_notes: installNotesText.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { editingInstallNotes.value = false; },
    });
};

const activeTab = ref<'timeline' | 'tasks' | 'documents' | 'notes' | 'files' | 'worker'>('timeline');

// Task filter
const taskFilter = ref<'all' | 'open' | 'in_progress' | 'done' | 'deferred' | 'cancelled'>('all');

// Modal states
const showDocViewer = ref(false);
const showDocForm = ref(false);
const showTaskDetail = ref(false);
const showTaskForm = ref(false);
const showDeleteConfirm = ref(false);

const selectedDocument = ref<Document | null>(null);
const editingDocument = ref<Document | null>(null);
const selectedTask = ref<Task | null>(null);
const editingTask = ref<Task | null>(null);
const deleteTarget = ref<{ type: 'document' | 'task'; item: any } | null>(null);
const deleteProcessing = ref(false);

// Filtered tasks
const filteredTasks = computed(() => {
    if (taskFilter.value === 'all') return props.tasks;
    return props.tasks.filter(t => t.status === taskFilter.value);
});

const groupTasksByStatus = computed(() => ({
    open: props.tasks.filter(t => t.status === 'open'),
    in_progress: props.tasks.filter(t => t.status === 'in_progress'),
    done: props.tasks.filter(t => t.status === 'done'),
}));

// Document actions
const openDocViewer = (doc: Document) => {
    selectedDocument.value = doc;
    showDocViewer.value = true;
};

const openDocForm = (doc?: Document) => {
    editingDocument.value = doc ?? null;
    showDocForm.value = true;
};

const confirmDeleteDoc = (doc: Document) => {
    deleteTarget.value = { type: 'document', item: doc };
    showDeleteConfirm.value = true;
};

// Task actions
const openTaskDetail = (task: Task) => {
    selectedTask.value = task;
    showTaskDetail.value = true;
};

const openTaskForm = (task?: Task) => {
    editingTask.value = task ?? null;
    showTaskForm.value = true;
};

const editTaskFromDetail = () => {
    showTaskDetail.value = false;
    openTaskForm(selectedTask.value!);
};

const confirmDeleteTask = (task: Task) => {
    deleteTarget.value = { type: 'task', item: task };
    showDeleteConfirm.value = true;
};

const quickStatusChange = (task: Task, newStatus: string) => {
    router.patch(route('projects.tasks.update', { slug: props.project.slug, id: task.id }), {
        status: newStatus,
    }, { preserveScroll: true });
};

// Delete handler
const executeDelete = () => {
    if (!deleteTarget.value) return;
    deleteProcessing.value = true;

    const { type, item } = deleteTarget.value;
    const url = type === 'document'
        ? route('projects.documents.destroy', { slug: props.project.slug, docSlug: item.slug })
        : route('projects.tasks.destroy', { slug: props.project.slug, id: item.id });

    router.delete(url, {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false;
            showDeleteConfirm.value = false;
            deleteTarget.value = null;
        },
    });
};

// Helpers
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

const getEventTypeColor = (type: string) => {
    const colors: Record<string, string> = {
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
    return colors[type] || 'bg-gray-100 text-gray-800';
};

const getTaskStatusColor = (status: string) => {
    const colors: Record<string, string> = {
        open: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        in_progress: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        done: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        deferred: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const getCategoryColor = (category: string) => {
    const colors: Record<string, string> = {
        documentation: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        specification: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        changelog: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        readme: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        architecture: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
        meeting_notes: 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
        guide: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
        other: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    };
    return colors[category] || 'bg-gray-100 text-gray-800';
};

const getHealthColor = (score: number) => {
    if (score >= 80) return 'text-green-600';
    if (score >= 60) return 'text-yellow-600';
    if (score >= 40) return 'text-orange-600';
    return 'text-red-600';
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head :title="project.name" />

    <AuthenticatedLayout>
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Back Button -->
                <div class="mb-4">
                    <Link
                        :href="route('projects.index')"
                        class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Projects
                    </Link>
                </div>

                <!-- Project Header -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                                {{ project.name }}
                            </h1>
                            <div class="flex gap-2 mb-3">
                                <span :class="['px-3 py-1 text-sm font-medium rounded', getStatusColor(project.status)]">
                                    {{ project.status }}
                                </span>
                                <span :class="['px-3 py-1 text-sm font-medium rounded', getPriorityColor(project.priority)]">
                                    {{ project.priority }}
                                </span>
                                <span v-if="project.category" class="px-3 py-1 text-sm font-medium rounded bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                    {{ project.category }}
                                </span>
                                <PermissionBadge v-if="isShared" :permission="permission" />
                            </div>
                        </div>

                        <div class="flex items-start gap-4">
                            <!-- Share Button -->
                            <div v-if="canShare" class="shrink-0">
                                <button
                                    @click="showShareModal = true"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                    </svg>
                                    Teilen
                                    <span v-if="shares && shares.length > 0" class="bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 rounded-full px-1.5 py-0.5 text-xs">
                                        {{ shares.length }}
                                    </span>
                                </button>
                            </div>

                            <!-- Health Score -->
                            <div class="text-right">
                                <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Health Score</div>
                                <div :class="['text-3xl font-bold', getHealthColor(project.health_score)]">
                                    {{ project.health_score }}%
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-if="project.description" class="text-gray-600 dark:text-gray-400 mb-4">
                        {{ project.description }}
                    </p>

                    <!-- Tech Stack -->
                    <div v-if="project.tech_stack && project.tech_stack.length > 0" class="mb-4">
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">Tech Stack</div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="tech in project.tech_stack"
                                :key="tech"
                                class="px-2 py-1 text-xs font-medium rounded bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200"
                            >
                                {{ tech }}
                            </span>
                        </div>
                    </div>

                    <!-- Repository Link -->
                    <div v-if="project.repository_url" class="mb-4">
                        <a
                            :href="project.repository_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                            Repository
                        </a>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ project.events_count }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Events</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ project.documents_count }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Documents</div>
                        </div>
                        <div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ project.tasks_count }}</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Tasks</div>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="bg-white dark:bg-gray-800 rounded-t-lg shadow-md">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="flex -mb-px">
                            <button
                                @click="activeTab = 'timeline'"
                                :class="[
                                    'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
                                    activeTab === 'timeline'
                                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:border-gray-300'
                                ]"
                            >
                                Timeline
                            </button>
                            <button
                                @click="activeTab = 'tasks'"
                                :class="[
                                    'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
                                    activeTab === 'tasks'
                                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:border-gray-300'
                                ]"
                            >
                                Tasks ({{ tasks.length }})
                            </button>
                            <button
                                @click="activeTab = 'documents'"
                                :class="[
                                    'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
                                    activeTab === 'documents'
                                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:border-gray-300'
                                ]"
                            >
                                Documents ({{ documents.length }})
                            </button>
                            <button
                                @click="activeTab = 'notes'"
                                :class="[
                                    'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
                                    activeTab === 'notes'
                                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:border-gray-300'
                                ]"
                            >
                                Notes ({{ notes.length }})
                            </button>
                            <button
                                @click="activeTab = 'files'"
                                :class="[
                                    'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
                                    activeTab === 'files'
                                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:border-gray-300'
                                ]"
                            >
                                Files
                                <span v-if="latestSnapshot" class="ml-1 text-xs text-gray-400">v{{ latestSnapshot.version }}</span>
                            </button>
                            <button
                                v-if="workerJobs"
                                @click="activeTab = 'worker'"
                                :class="[
                                    'px-6 py-3 text-sm font-medium border-b-2 transition-colors',
                                    activeTab === 'worker'
                                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400'
                                        : 'border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:border-gray-300'
                                ]"
                            >
                                Worker ({{ workerJobs?.length || 0 }})
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Tab Content -->
                <div class="bg-white dark:bg-gray-800 rounded-b-lg shadow-md p-6">
                    <!-- Timeline Tab -->
                    <div v-show="activeTab === 'timeline'">
                        <div v-if="events.length > 0" class="space-y-4">
                            <div
                                v-for="event in events"
                                :key="event.id"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <span :class="['px-2 py-1 text-xs font-medium rounded', getEventTypeColor(event.type)]">
                                            {{ event.type.replace('_', ' ') }}
                                        </span>
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                            {{ event.title }}
                                        </h3>
                                    </div>
                                    <span class="text-sm text-gray-500 dark:text-gray-400 shrink-0 ml-4">
                                        {{ formatDate(event.created_at) }}
                                    </span>
                                </div>
                                <SummaryBadge v-if="event.summary" :summary="event.summary" />
                                <div class="prose prose-sm dark:prose-invert max-w-none">
                                    <MarkdownRenderer :content="event.content" />
                                </div>
                                <div v-if="event.user" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    by {{ event.user.name }}
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No events yet</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Events will appear here as the project progresses.</p>
                        </div>
                    </div>

                    <!-- Tasks Tab -->
                    <div v-show="activeTab === 'tasks'">
                        <!-- Task Toolbar -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <select
                                    v-model="taskFilter"
                                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm dark:bg-gray-900 dark:border-gray-700 dark:text-gray-200"
                                >
                                    <option value="all">All Tasks</option>
                                    <option value="open">Open</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="done">Done</option>
                                    <option value="deferred">Deferred</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ filteredTasks.length }} task{{ filteredTasks.length !== 1 ? 's' : '' }}
                                </span>
                            </div>
                            <button
                                v-if="canEdit"
                                @click="openTaskForm()"
                                class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                New Task
                            </button>
                        </div>

                        <!-- Kanban Board (when filter is 'all') -->
                        <div v-if="taskFilter === 'all' && tasks.length > 0" class="grid gap-6 md:grid-cols-3">
                            <!-- Open Tasks -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center">
                                    <span class="w-3 h-3 bg-yellow-500 rounded-full mr-2"></span>
                                    Open ({{ groupTasksByStatus.open.length }})
                                </h3>
                                <div class="space-y-3">
                                    <div
                                        v-for="task in groupTasksByStatus.open"
                                        :key="task.id"
                                        class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700 cursor-pointer hover:shadow-md transition-shadow"
                                        @click="openTaskDetail(task)"
                                    >
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">{{ task.title }}</h4>
                                        <div class="flex flex-wrap gap-1 mb-2">
                                            <span :class="['px-2 py-0.5 text-xs font-medium rounded', getPriorityColor(task.priority)]">
                                                {{ task.priority }}
                                            </span>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                {{ task.type }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div v-if="task.due_date" class="text-xs text-gray-500 dark:text-gray-400">
                                                Due: {{ formatDate(task.due_date) }}
                                            </div>
                                            <button
                                                v-if="canEdit"
                                                @click.stop="quickStatusChange(task, 'in_progress')"
                                                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400"
                                                title="Start"
                                            >
                                                Start &rarr;
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- In Progress Tasks -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center">
                                    <span class="w-3 h-3 bg-blue-500 rounded-full mr-2"></span>
                                    In Progress ({{ groupTasksByStatus.in_progress.length }})
                                </h3>
                                <div class="space-y-3">
                                    <div
                                        v-for="task in groupTasksByStatus.in_progress"
                                        :key="task.id"
                                        class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700 cursor-pointer hover:shadow-md transition-shadow"
                                        @click="openTaskDetail(task)"
                                    >
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">{{ task.title }}</h4>
                                        <div class="flex flex-wrap gap-1 mb-2">
                                            <span :class="['px-2 py-0.5 text-xs font-medium rounded', getPriorityColor(task.priority)]">
                                                {{ task.priority }}
                                            </span>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                {{ task.type }}
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <div v-if="task.due_date" class="text-xs text-gray-500 dark:text-gray-400">
                                                Due: {{ formatDate(task.due_date) }}
                                            </div>
                                            <button
                                                v-if="canEdit"
                                                @click.stop="quickStatusChange(task, 'done')"
                                                class="text-xs text-green-600 hover:text-green-800 dark:text-green-400"
                                                title="Complete"
                                            >
                                                Done &check;
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Done Tasks -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center">
                                    <span class="w-3 h-3 bg-green-500 rounded-full mr-2"></span>
                                    Done ({{ groupTasksByStatus.done.length }})
                                </h3>
                                <div class="space-y-3">
                                    <div
                                        v-for="task in groupTasksByStatus.done"
                                        :key="task.id"
                                        class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 border border-gray-200 dark:border-gray-700 opacity-75 cursor-pointer hover:shadow-md transition-shadow"
                                        @click="openTaskDetail(task)"
                                    >
                                        <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2 line-through">{{ task.title }}</h4>
                                        <div class="flex flex-wrap gap-1 mb-2">
                                            <span :class="['px-2 py-0.5 text-xs font-medium rounded', getPriorityColor(task.priority)]">
                                                {{ task.priority }}
                                            </span>
                                            <span class="px-2 py-0.5 text-xs font-medium rounded bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                {{ task.type }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filtered list view -->
                        <div v-else-if="filteredTasks.length > 0" class="space-y-3">
                            <div
                                v-for="task in filteredTasks"
                                :key="task.id"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer flex items-center justify-between"
                                @click="openTaskDetail(task)"
                            >
                                <div class="flex-1">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span :class="['px-2 py-0.5 text-xs font-medium rounded', getTaskStatusColor(task.status)]">
                                            {{ task.status.replace('_', ' ') }}
                                        </span>
                                        <span :class="['px-2 py-0.5 text-xs font-medium rounded', getPriorityColor(task.priority)]">
                                            {{ task.priority }}
                                        </span>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                            {{ task.type }}
                                        </span>
                                    </div>
                                    <h4 class="font-medium text-gray-900 dark:text-gray-100" :class="{ 'line-through opacity-60': task.status === 'done' }">
                                        {{ task.title }}
                                    </h4>
                                </div>
                                <div v-if="canEdit" class="flex items-center gap-2 ml-4">
                                    <button
                                        @click.stop="openTaskForm(task)"
                                        class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                        title="Edit"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="canDelete"
                                        @click.stop="confirmDeleteTask(task)"
                                        class="text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                        title="Delete"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No tasks yet</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create tasks to track your project work.</p>
                            <button
                                v-if="canEdit"
                                @click="openTaskForm()"
                                class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                            >
                                Create First Task
                            </button>
                        </div>
                    </div>

                    <!-- Notes Tab -->
                    <div v-show="activeTab === 'notes'">
                        <div v-if="canEdit" class="mb-6">
                            <NoteForm :project-slug="project.slug" placeholder="Write a note about this project... (Markdown supported)" />
                        </div>
                        <NoteThread
                            :notes="notes"
                            :project-slug="project.slug"
                            :current-user-id="currentUserId"
                        />
                    </div>

                    <!-- Files Tab -->
                    <div v-show="activeTab === 'files'">
                        <!-- Toolbar -->
                        <div class="flex items-center justify-between mb-6">
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                <template v-if="latestSnapshot">
                                    Version {{ latestSnapshot.version }} &middot; {{ latestSnapshot.file_count }} Dateien &middot; {{ latestSnapshot.human_size }}
                                    <span v-if="latestSnapshot.change_note" class="ml-2 text-gray-600 dark:text-gray-300">&mdash; {{ latestSnapshot.change_note }}</span>
                                </template>
                                <template v-else>Noch keine Snapshots vorhanden.</template>
                            </div>
                            <button
                                v-if="canEdit"
                                @click="showSnapshotUpload = true"
                                class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                Upload ZIP
                            </button>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-3">
                            <!-- File Tree (2/3) -->
                            <div class="lg:col-span-2">
                                <div v-if="latestSnapshot && latestSnapshot.file_tree && latestSnapshot.file_tree.length > 0"
                                     class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 max-h-[600px] overflow-y-auto">
                                    <FileTree :nodes="latestSnapshot.file_tree" />
                                </div>
                                <div v-else class="text-center py-12 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Lade einen ZIP-Snapshot hoch um die Dateistruktur zu sehen.</p>
                                </div>
                            </div>

                            <!-- Sidebar: Install Notes + Versions (1/3) -->
                            <div class="space-y-6">
                                <!-- Install Notes -->
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Installationsanleitung</h4>
                                        <button
                                            v-if="canEdit && !editingInstallNotes"
                                            @click="editingInstallNotes = true"
                                            class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                                        >
                                            Bearbeiten
                                        </button>
                                    </div>
                                    <template v-if="editingInstallNotes">
                                        <textarea
                                            v-model="installNotesText"
                                            rows="8"
                                            class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="# Installation&#10;&#10;```bash&#10;composer install&#10;npm install&#10;npm run build&#10;```"
                                        ></textarea>
                                        <div class="flex gap-2 mt-2">
                                            <button @click="saveInstallNotes" class="text-xs px-2 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700">Speichern</button>
                                            <button @click="editingInstallNotes = false" class="text-xs text-gray-500 hover:text-gray-700">Abbrechen</button>
                                        </div>
                                    </template>
                                    <template v-else-if="installNotes">
                                        <MarkdownRenderer :content="installNotes" class="text-sm" />
                                    </template>
                                    <p v-else class="text-xs text-gray-400 italic">Noch keine Anleitung hinterlegt.</p>
                                </div>

                                <!-- Version History -->
                                <div v-if="snapshotVersions && snapshotVersions.length > 0" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Versionen</h4>
                                    <div class="space-y-2 max-h-[300px] overflow-y-auto">
                                        <div
                                            v-for="snap in snapshotVersions"
                                            :key="snap.id"
                                            class="flex items-center justify-between text-xs p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-750"
                                        >
                                            <div>
                                                <span class="font-medium text-gray-700 dark:text-gray-300">v{{ snap.version }}</span>
                                                <span class="text-gray-400 ml-1">{{ snap.human_size }} &middot; {{ snap.file_count }} Dateien</span>
                                                <div v-if="snap.change_note" class="text-gray-500 dark:text-gray-400 truncate max-w-[200px]">{{ snap.change_note }}</div>
                                                <div class="text-gray-400">{{ new Date(snap.created_at).toLocaleDateString('de-DE') }} &middot; {{ snap.uploaded_by }}</div>
                                            </div>
                                            <a
                                                :href="route('projects.snapshots.download', { slug: project.slug, version: snap.version })"
                                                class="text-indigo-600 dark:text-indigo-400 hover:underline shrink-0 ml-2"
                                                title="Download"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                </svg>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Worker Tab -->
                    <div v-show="activeTab === 'worker'" v-if="workerJobs">
                        <div v-if="workerJobs.length > 0" class="space-y-3">
                            <Link
                                v-for="wj in workerJobs"
                                :key="wj.id"
                                :href="route('workers.jobs.show', wj.id)"
                                class="block border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <JobStatusBadge :status="wj.status" />
                                        <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ wj.title }}</h3>
                                    </div>
                                    <span class="text-sm text-gray-500 dark:text-gray-400 shrink-0 ml-4">
                                        {{ formatDate(wj.created_at) }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                    <span>{{ wj.type.replace(/_/g, ' ') }}</span>
                                    <span>{{ wj.priority }}</span>
                                    <span v-if="wj.creator">by {{ wj.creator.name }}</span>
                                    <span v-if="wj.worker">worker: {{ wj.worker.name }}</span>
                                    <span v-if="wj.duration_seconds">{{ wj.duration_seconds }}s</span>
                                </div>
                            </Link>
                        </div>
                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No worker jobs</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Worker jobs for this project will appear here.</p>
                        </div>
                    </div>

                    <!-- Documents Tab -->
                    <div v-show="activeTab === 'documents'">
                        <!-- Document Toolbar -->
                        <div v-if="canEdit" class="flex items-center justify-end mb-4">
                            <button
                                @click="openDocForm()"
                                class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 transition-colors"
                            >
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                New Document
                            </button>
                        </div>

                        <div v-if="documents.length > 0" class="space-y-3">
                            <div
                                v-for="document in documents"
                                :key="document.id"
                                class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer"
                                @click="openDocViewer(document)"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                                {{ document.title }}
                                            </h3>
                                            <span :class="['px-2 py-1 text-xs font-medium rounded', getCategoryColor(document.category)]">
                                                {{ document.category.replace('_', ' ') }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                            <span>Version {{ document.current_version }}</span>
                                            <span>Updated {{ formatDate(document.updated_at) }}</span>
                                        </div>
                                    </div>
                                    <div v-if="canEdit" class="flex items-center gap-2 ml-4">
                                        <button
                                            @click.stop="openDocForm(document)"
                                            class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                                            title="Edit"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button
                                            v-if="canDelete"
                                            @click.stop="confirmDeleteDoc(document)"
                                            class="text-gray-400 hover:text-red-600 dark:hover:text-red-400"
                                            title="Delete"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No documents yet</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create documentation for your project.</p>
                            <button
                                v-if="canEdit"
                                @click="openDocForm()"
                                class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                            >
                                Create First Document
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <DocumentViewerModal
            :show="showDocViewer"
            :document="selectedDocument"
            @close="showDocViewer = false"
        />

        <DocumentFormModal
            :show="showDocForm"
            :project-slug="project.slug"
            :document="editingDocument"
            @close="showDocForm = false; editingDocument = null"
            @saved="editingDocument = null"
        />

        <TaskDetailModal
            :show="showTaskDetail"
            :task="selectedTask"
            @close="showTaskDetail = false; selectedTask = null"
            @edit="editTaskFromDetail"
        />

        <TaskFormModal
            :show="showTaskForm"
            :project-slug="project.slug"
            :task="editingTask"
            @close="showTaskForm = false; editingTask = null"
            @saved="editingTask = null"
        />

        <ConfirmDialog
            :show="showDeleteConfirm"
            :title="deleteTarget?.type === 'document' ? 'Delete Document' : 'Delete Task'"
            :message="deleteTarget?.type === 'document'
                ? `Are you sure you want to delete '${deleteTarget?.item?.title}'? All versions will be lost.`
                : `Are you sure you want to delete '${deleteTarget?.item?.title}'?`"
            confirm-label="Delete"
            :processing="deleteProcessing"
            @confirm="executeDelete"
            @cancel="showDeleteConfirm = false; deleteTarget = null"
        />

        <ShareModal
            :show="showShareModal"
            :project-slug="project.slug"
            :project-name="project.name"
            :shares="shares || []"
            @close="showShareModal = false"
        />

        <SnapshotUploadModal
            :show="showSnapshotUpload"
            :project-slug="project.slug"
            @close="showSnapshotUpload = false"
        />
    </AuthenticatedLayout>
</template>
