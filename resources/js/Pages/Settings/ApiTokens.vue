<script setup lang="ts">
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';

interface Token {
    id: number;
    name: string;
    last_four: string;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
}

defineProps<{
    tokens: Token[];
}>();

const page = usePage();
const newToken = computed(() => (page.props as any).flash?.newToken as string | undefined);
const copied = ref(false);

const form = useForm({
    name: '',
});

const submit = () => {
    form.post(route('settings.api-tokens.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            copied.value = false;
        },
    });
};

const deleteToken = (tokenId: number, tokenName: string) => {
    if (confirm(`Are you sure you want to delete the token "${tokenName}"? This action cannot be undone.`)) {
        router.delete(route('settings.api-tokens.destroy', tokenId), {
            preserveScroll: true,
        });
    }
};

const copyToken = async () => {
    if (newToken.value) {
        try {
            await navigator.clipboard.writeText(newToken.value);
            copied.value = true;
            setTimeout(() => {
                copied.value = false;
            }, 2000);
        } catch (err) {
            console.error('Failed to copy token:', err);
        }
    }
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};
</script>

<template>
    <Head title="API Tokens" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                API Tokens
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Header Description -->
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900">
                                Manage API Tokens
                            </h3>
                            <p class="mt-1 text-sm text-gray-600">
                                API tokens allow external applications like Claude Code to interact with your projects.
                                Keep your tokens secure and never share them publicly.
                            </p>
                        </div>

                        <!-- New Token Display (if just created) -->
                        <div v-if="newToken" class="mb-6 rounded-md bg-green-50 p-4 border border-green-200">
                            <div class="flex items-start">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3 flex-1">
                                    <h3 class="text-sm font-medium text-green-800">
                                        Token Created Successfully
                                    </h3>
                                    <div class="mt-2 text-sm text-green-700">
                                        <p class="font-semibold mb-2">
                                            Make sure to copy your token now. You won't be able to see it again!
                                        </p>
                                        <div class="flex items-center gap-2 mt-3">
                                            <code class="flex-1 block rounded bg-white px-3 py-2 text-sm font-mono border border-green-300 select-all">
                                                {{ newToken }}
                                            </code>
                                            <button
                                                @click="copyToken"
                                                type="button"
                                                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                            >
                                                <svg v-if="!copied" class="h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                </svg>
                                                <svg v-else class="h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                {{ copied ? 'Copied!' : 'Copy' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Create Token Form -->
                        <div class="mb-8">
                            <h4 class="text-md font-medium text-gray-900 mb-4">
                                Create New Token
                            </h4>
                            <form @submit.prevent="submit" class="flex gap-4 items-end">
                                <div class="flex-1">
                                    <InputLabel for="name" value="Token Name" />
                                    <TextInput
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        class="mt-1 block w-full"
                                        placeholder="e.g., Claude Code Desktop"
                                        required
                                        autofocus
                                    />
                                    <InputError :message="form.errors.name" class="mt-2" />
                                </div>
                                <PrimaryButton
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                >
                                    Create Token
                                </PrimaryButton>
                            </form>
                        </div>

                        <!-- Existing Tokens -->
                        <div>
                            <h4 class="text-md font-medium text-gray-900 mb-4">
                                Existing Tokens
                            </h4>

                            <div v-if="tokens.length === 0" class="text-center py-8 text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                </svg>
                                <p class="mt-2">No API tokens yet. Create one to get started.</p>
                            </div>

                            <div v-else class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Name
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Token
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Last Used
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Created
                                            </th>
                                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="token in tokens" :key="token.id" class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ token.name }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <code class="text-sm text-gray-600 font-mono">
                                                    ••••{{ token.last_four }}
                                                </code>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ formatDate(token.last_used_at) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ formatDate(token.created_at) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <DangerButton
                                                    @click="deleteToken(token.id, token.name)"
                                                    type="button"
                                                    class="text-xs"
                                                >
                                                    Delete
                                                </DangerButton>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
