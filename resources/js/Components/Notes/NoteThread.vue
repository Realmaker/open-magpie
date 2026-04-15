<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import MarkdownRenderer from '@/Components/MarkdownRenderer.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

interface User {
    id: number;
    name: string;
}

interface Note {
    id: number;
    content: string;
    source: string;
    user_id: number;
    user?: User;
    parent_id?: number | null;
    replies?: Note[];
    created_at: string;
    updated_at: string;
}

const props = defineProps<{
    notes: Note[];
    projectSlug: string;
    currentUserId?: number;
}>();

const replyingTo = ref<number | null>(null);
const replyContent = ref('');
const replyProcessing = ref(false);
const editingNote = ref<number | null>(null);
const editContent = ref('');
const editProcessing = ref(false);
const showDeleteConfirm = ref(false);
const deleteTarget = ref<Note | null>(null);
const deleteProcessing = ref(false);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const startReply = (noteId: number) => {
    replyingTo.value = noteId;
    replyContent.value = '';
};

const cancelReply = () => {
    replyingTo.value = null;
    replyContent.value = '';
};

const submitReply = (parentId: number) => {
    if (!replyContent.value.trim()) return;
    replyProcessing.value = true;

    router.post(route('projects.notes.store', { slug: props.projectSlug }), {
        content: replyContent.value,
        parent_id: parentId,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            replyingTo.value = null;
            replyContent.value = '';
        },
        onFinish: () => {
            replyProcessing.value = false;
        },
    });
};

const startEdit = (note: Note) => {
    editingNote.value = note.id;
    editContent.value = note.content;
};

const cancelEdit = () => {
    editingNote.value = null;
    editContent.value = '';
};

const submitEdit = (noteId: number) => {
    if (!editContent.value.trim()) return;
    editProcessing.value = true;

    router.patch(route('notes.update', { id: noteId }), {
        content: editContent.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            editingNote.value = null;
            editContent.value = '';
        },
        onFinish: () => {
            editProcessing.value = false;
        },
    });
};

const confirmDelete = (note: Note) => {
    deleteTarget.value = note;
    showDeleteConfirm.value = true;
};

const executeDelete = () => {
    if (!deleteTarget.value) return;
    deleteProcessing.value = true;

    router.delete(route('notes.destroy', { id: deleteTarget.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            deleteProcessing.value = false;
            showDeleteConfirm.value = false;
            deleteTarget.value = null;
        },
    });
};

const getSourceBadge = (source: string) => {
    const badges: Record<string, string> = {
        manual: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        voice: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        ai: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
    };
    return badges[source] || badges.manual;
};
</script>

<template>
    <div class="space-y-4">
        <div
            v-for="note in notes"
            :key="note.id"
            class="border border-gray-200 dark:border-gray-700 rounded-lg"
        >
            <!-- Note Header -->
            <div class="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-900 rounded-t-lg">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-sm font-medium">
                        {{ note.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                    </div>
                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ note.user?.name ?? 'Unknown' }}
                    </span>
                    <span v-if="note.source !== 'manual'" :class="['px-1.5 py-0.5 text-xs font-medium rounded', getSourceBadge(note.source)]">
                        {{ note.source }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ formatDate(note.created_at) }}
                    </span>
                </div>
                <div v-if="currentUserId === note.user_id" class="flex items-center gap-1">
                    <button
                        @click="startEdit(note)"
                        class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 p-1"
                        title="Edit"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                    <button
                        @click="confirmDelete(note)"
                        class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 p-1"
                        title="Delete"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Note Content -->
            <div class="px-4 py-3">
                <div v-if="editingNote === note.id">
                    <textarea
                        v-model="editContent"
                        rows="3"
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    />
                    <div class="flex justify-end gap-2 mt-2">
                        <button @click="cancelEdit" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                            Cancel
                        </button>
                        <button
                            @click="submitEdit(note.id)"
                            :disabled="editProcessing"
                            class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 font-medium"
                        >
                            {{ editProcessing ? 'Saving...' : 'Save' }}
                        </button>
                    </div>
                </div>
                <div v-else class="prose prose-sm dark:prose-invert max-w-none">
                    <MarkdownRenderer :content="note.content" />
                </div>
            </div>

            <!-- Replies -->
            <div v-if="note.replies && note.replies.length > 0" class="border-t border-gray-200 dark:border-gray-700">
                <div
                    v-for="reply in note.replies"
                    :key="reply.id"
                    class="px-4 py-3 ml-6 border-l-2 border-indigo-200 dark:border-indigo-800"
                >
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-6 h-6 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-300 text-xs font-medium">
                            {{ reply.user?.name?.charAt(0)?.toUpperCase() ?? '?' }}
                        </div>
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ reply.user?.name ?? 'Unknown' }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ formatDate(reply.created_at) }}
                        </span>
                        <div v-if="currentUserId === reply.user_id" class="flex items-center gap-1 ml-auto">
                            <button
                                @click="confirmDelete(reply)"
                                class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 p-1"
                                title="Delete"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="prose prose-sm dark:prose-invert max-w-none ml-8">
                        <MarkdownRenderer :content="reply.content" />
                    </div>
                </div>
            </div>

            <!-- Reply Action -->
            <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700">
                <div v-if="replyingTo === note.id">
                    <textarea
                        v-model="replyContent"
                        rows="2"
                        placeholder="Write a reply..."
                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    />
                    <div class="flex justify-end gap-2 mt-2">
                        <button @click="cancelReply" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                            Cancel
                        </button>
                        <button
                            @click="submitReply(note.id)"
                            :disabled="replyProcessing || !replyContent.trim()"
                            class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 font-medium disabled:opacity-50"
                        >
                            {{ replyProcessing ? 'Posting...' : 'Reply' }}
                        </button>
                    </div>
                </div>
                <button
                    v-else
                    @click="startReply(note.id)"
                    class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400"
                >
                    Reply
                </button>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="notes.length === 0" class="text-center py-8">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No notes yet</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Start a conversation about this project.</p>
        </div>

        <!-- Delete Confirm -->
        <ConfirmDialog
            :show="showDeleteConfirm"
            title="Delete Note"
            message="Are you sure you want to delete this note? This cannot be undone."
            confirm-label="Delete"
            :processing="deleteProcessing"
            @confirm="executeDelete"
            @cancel="showDeleteConfirm = false; deleteTarget = null"
        />
    </div>
</template>
