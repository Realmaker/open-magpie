<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

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

const props = defineProps<{
    show: boolean;
    projectSlug: string;
    projectName: string;
    shares: Share[];
}>();

const emit = defineEmits(['close']);

const form = useForm({
    email: '',
    permission: 'viewer' as 'viewer' | 'editor' | 'admin',
});

const editingShare = ref<number | null>(null);
const editPermission = ref<'viewer' | 'editor' | 'admin'>('viewer');
const revoking = ref<number | null>(null);

watch(() => props.show, (val) => {
    if (val) {
        form.reset();
        form.clearErrors();
        editingShare.value = null;
    }
});

const submitShare = () => {
    form.post(route('projects.shares.store', { slug: props.projectSlug }), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.clearErrors();
        },
    });
};

const startEdit = (share: Share) => {
    editingShare.value = share.id;
    editPermission.value = share.permission;
};

const saveEdit = (share: Share) => {
    router.patch(route('projects.shares.update', { slug: props.projectSlug, id: share.id }), {
        permission: editPermission.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { editingShare.value = null; },
    });
};

const revokeShare = (share: Share) => {
    revoking.value = share.id;
    router.delete(route('projects.shares.destroy', { slug: props.projectSlug, id: share.id }), {
        preserveScroll: true,
        onFinish: () => { revoking.value = null; },
    });
};

const permissionLabel = (p: string) => {
    const labels: Record<string, string> = {
        viewer: 'Betrachter',
        editor: 'Bearbeiter',
        admin: 'Administrator',
    };
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

const formatDate = (d: string) => {
    return new Date(d).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
};
</script>

<template>
    <Modal :show="show" max-width="2xl" @close="emit('close')">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">
                Projekt teilen
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                {{ projectName }}
            </p>

            <!-- Invite Form -->
            <form @submit.prevent="submitShare" class="flex gap-3 mb-6">
                <div class="flex-1">
                    <InputLabel for="share-email" value="E-Mail" class="sr-only" />
                    <TextInput
                        id="share-email"
                        v-model="form.email"
                        type="email"
                        placeholder="email@beispiel.de"
                        class="w-full"
                        required
                    />
                    <InputError :message="form.errors.email" class="mt-1" />
                </div>
                <div>
                    <select
                        v-model="form.permission"
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm h-[42px]"
                    >
                        <option value="viewer">Betrachter</option>
                        <option value="editor">Bearbeiter</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <PrimaryButton :disabled="form.processing" class="shrink-0">
                    <span v-if="form.processing">...</span>
                    <span v-else>Einladen</span>
                </PrimaryButton>
            </form>

            <!-- Permission Legend -->
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3 mb-6 text-xs text-gray-600 dark:text-gray-400">
                <div class="grid grid-cols-3 gap-2">
                    <div><span class="font-medium">Betrachter:</span> Nur lesen</div>
                    <div><span class="font-medium">Bearbeiter:</span> Lesen + Erstellen</div>
                    <div><span class="font-medium">Admin:</span> Alles + Teilen</div>
                </div>
            </div>

            <!-- Existing Shares -->
            <div v-if="shares.length > 0">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                    Freigaben ({{ shares.length }})
                </h4>
                <div class="space-y-2">
                    <div
                        v-for="share in shares"
                        :key="share.id"
                        class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-gray-700"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <!-- Avatar -->
                            <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-sm font-medium shrink-0">
                                {{ share.shared_with_email.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                    {{ share.shared_with_user?.name || share.shared_with_email }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                    <span v-if="share.shared_with_user" class="truncate">{{ share.shared_with_email }}</span>
                                    <span v-if="share.is_expired" class="text-red-500 font-medium">Abgelaufen</span>
                                    <span v-else-if="!share.accepted_at" class="text-yellow-600 dark:text-yellow-400 font-medium">Ausstehend</span>
                                    <span v-else class="text-green-600 dark:text-green-400">Aktiv seit {{ formatDate(share.accepted_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0 ml-3">
                            <!-- Permission Badge / Edit -->
                            <template v-if="editingShare === share.id">
                                <select
                                    v-model="editPermission"
                                    class="rounded text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 py-1"
                                >
                                    <option value="viewer">Betrachter</option>
                                    <option value="editor">Bearbeiter</option>
                                    <option value="admin">Administrator</option>
                                </select>
                                <button
                                    @click="saveEdit(share)"
                                    class="text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200 text-xs font-medium"
                                >
                                    OK
                                </button>
                                <button
                                    @click="editingShare = null"
                                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-xs"
                                >
                                    Abbruch
                                </button>
                            </template>
                            <template v-else>
                                <button
                                    @click="startEdit(share)"
                                    :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium cursor-pointer hover:opacity-80', permissionColor(share.permission)]"
                                    :title="'Klick zum Aendern'"
                                >
                                    {{ permissionLabel(share.permission) }}
                                </button>
                                <button
                                    @click="revokeShare(share)"
                                    :disabled="revoking === share.id"
                                    class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200 p-1"
                                    title="Freigabe widerrufen"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-6 text-gray-500 dark:text-gray-400 text-sm">
                Dieses Projekt wurde noch nicht geteilt.
            </div>

            <!-- Close -->
            <div class="mt-6 flex justify-end">
                <button
                    @click="emit('close')"
                    class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100"
                >
                    Schliessen
                </button>
            </div>
        </div>
    </Modal>
</template>
