<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';

interface Member {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'member' | 'viewer';
    joined_at: string;
    email_verified: boolean;
    last_login: string;
}

interface AvailableUser {
    id: number;
    name: string;
    email: string;
    created_at: string;
}

const props = defineProps<{
    members: Member[];
    availableUsers: AvailableUser[];
    stats: { total: number; owners: number; admins: number; members: number; viewers: number };
    teamName: string;
    currentUserId: number;
}>();

const showAddModal = ref(false);
const showRemoveConfirm = ref<Member | null>(null);
const removing = ref(false);

const addForm = useForm({
    user_id: '' as string | number,
    role: 'member' as string,
});

const roleLabels: Record<string, string> = {
    owner: 'Owner',
    admin: 'Administrator',
    member: 'Mitglied',
    viewer: 'Betrachter',
};

const roleColors: Record<string, string> = {
    owner: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300',
    admin: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
    member: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
    viewer: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
};

const changeRole = (member: Member, newRole: string) => {
    router.patch(route('settings.users.role', { id: member.id }), {
        role: newRole,
    }, { preserveScroll: true });
};

const submitAdd = () => {
    addForm.post(route('settings.users.add'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddModal.value = false;
            addForm.reset();
        },
    });
};

const confirmRemove = (member: Member) => {
    showRemoveConfirm.value = member;
};

const executeRemove = () => {
    if (!showRemoveConfirm.value) return;
    removing.value = true;
    router.delete(route('settings.users.remove', { id: showRemoveConfirm.value.id }), {
        preserveScroll: true,
        onFinish: () => {
            removing.value = false;
            showRemoveConfirm.value = null;
        },
    });
};

const formatDate = (d: string) => {
    return new Date(d).toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });
};
</script>

<template>
    <Head title="Benutzerverwaltung" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Benutzerverwaltung &mdash; {{ teamName }}
                </h2>
                <PrimaryButton v-if="availableUsers.length > 0" @click="showAddModal = true">
                    User hinzufuegen
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Stats -->
                <div class="grid grid-cols-5 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.total }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Gesamt</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                        <div class="text-2xl font-bold text-emerald-600">{{ stats.owners }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Owner</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                        <div class="text-2xl font-bold text-purple-600">{{ stats.admins }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Admins</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ stats.members }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Mitglieder</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 text-center">
                        <div class="text-2xl font-bold text-gray-600">{{ stats.viewers }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">Betrachter</div>
                    </div>
                </div>

                <!-- User List -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rolle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dabei seit</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="member in members" :key="member.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-sm font-semibold">
                                            {{ member.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ member.name }}
                                                <span v-if="member.id === currentUserId" class="text-xs text-gray-400 ml-1">(du)</span>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ member.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <!-- Owner: just badge, no change -->
                                    <span v-if="member.role === 'owner' || member.id === currentUserId"
                                        :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', roleColors[member.role]]"
                                    >
                                        {{ roleLabels[member.role] }}
                                    </span>
                                    <!-- Others: select dropdown -->
                                    <select
                                        v-else
                                        :value="member.role"
                                        @change="changeRole(member, ($event.target as HTMLSelectElement).value)"
                                        class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 py-1 pr-7"
                                    >
                                        <option value="admin">Administrator</option>
                                        <option value="member">Mitglied</option>
                                        <option value="viewer">Betrachter</option>
                                    </select>
                                </td>
                                <td class="px-6 py-4">
                                    <span v-if="member.email_verified" class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        Verifiziert
                                    </span>
                                    <span v-else class="text-xs text-yellow-600 dark:text-yellow-400">
                                        Ausstehend
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(member.joined_at) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button
                                        v-if="member.role !== 'owner' && member.id !== currentUserId"
                                        @click="confirmRemove(member)"
                                        class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-200 text-xs font-medium"
                                    >
                                        Entfernen
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add User Modal -->
        <Modal :show="showAddModal" max-width="md" @close="showAddModal = false">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    User zum Team hinzufuegen
                </h3>

                <form @submit.prevent="submitAdd">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">User</label>
                        <select
                            v-model="addForm.user_id"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            required
                        >
                            <option value="" disabled>User auswaehlen...</option>
                            <option v-for="u in availableUsers" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.email }})
                            </option>
                        </select>
                        <InputError :message="addForm.errors.user_id" class="mt-1" />
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rolle</label>
                        <select
                            v-model="addForm.role"
                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option value="admin">Administrator</option>
                            <option value="member">Mitglied</option>
                            <option value="viewer">Betrachter</option>
                        </select>
                        <InputError :message="addForm.errors.role" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            type="button"
                            @click="showAddModal = false"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100"
                        >
                            Abbrechen
                        </button>
                        <PrimaryButton :disabled="addForm.processing || !addForm.user_id">
                            Hinzufuegen
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Remove Confirm -->
        <Modal :show="!!showRemoveConfirm" max-width="sm" @close="showRemoveConfirm = null">
            <div class="p-6" v-if="showRemoveConfirm">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                    User entfernen
                </h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    <strong>{{ showRemoveConfirm.name }}</strong> ({{ showRemoveConfirm.email }}) wirklich aus dem Team entfernen?
                    Der User verliert Zugriff auf alle Team-Projekte.
                </p>
                <div class="flex justify-end gap-3">
                    <button
                        @click="showRemoveConfirm = null"
                        class="text-sm text-gray-600 dark:text-gray-400"
                    >
                        Abbrechen
                    </button>
                    <DangerButton @click="executeRemove" :disabled="removing">
                        {{ removing ? '...' : 'Entfernen' }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
