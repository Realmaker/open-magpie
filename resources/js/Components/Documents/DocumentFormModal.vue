<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

interface DocumentData {
    id?: number;
    title: string;
    slug?: string;
    category: string;
    content?: string;
}

const props = defineProps<{
    show: boolean;
    projectSlug: string;
    document?: DocumentData | null;
}>();

const emit = defineEmits<{
    close: [];
    saved: [];
}>();

const form = useForm({
    title: '',
    category: 'documentation',
    content: '',
    change_note: '',
    source: 'manual',
});

const categories = [
    { value: 'documentation', label: 'Documentation' },
    { value: 'specification', label: 'Specification' },
    { value: 'changelog', label: 'Changelog' },
    { value: 'readme', label: 'README' },
    { value: 'architecture', label: 'Architecture' },
    { value: 'meeting_notes', label: 'Meeting Notes' },
    { value: 'guide', label: 'Guide' },
    { value: 'other', label: 'Other' },
];

watch(() => props.show, (newVal) => {
    if (newVal && props.document) {
        form.title = props.document.title;
        form.category = props.document.category;
        form.content = props.document.content ?? '';
        form.change_note = '';
    } else if (newVal) {
        form.reset();
    }
});

const submit = () => {
    if (props.document?.slug) {
        form.put(route('projects.documents.update', { slug: props.projectSlug, docSlug: props.document.slug }), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        });
    } else {
        form.post(route('projects.documents.store', { slug: props.projectSlug }), {
            preserveScroll: true,
            onSuccess: () => {
                emit('saved');
                emit('close');
            },
        });
    }
};
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="2xl">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                {{ document?.slug ? 'Update Document' : 'New Document' }}
            </h3>

            <form @submit.prevent="submit" class="space-y-4">
                <div v-if="!document?.slug">
                    <InputLabel for="doc-title" value="Title" />
                    <TextInput id="doc-title" v-model="form.title" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>

                <div v-if="!document?.slug">
                    <InputLabel for="doc-category" value="Category" />
                    <select
                        id="doc-category"
                        v-model="form.category"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    >
                        <option v-for="cat in categories" :key="cat.value" :value="cat.value">
                            {{ cat.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <InputLabel for="doc-content" value="Content (Markdown)" />
                    <textarea
                        id="doc-content"
                        v-model="form.content"
                        rows="15"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono"
                        placeholder="Write your content in Markdown..."
                    />
                    <InputError :message="form.errors.content" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="doc-change-note" value="Change Note (optional)" />
                    <TextInput
                        id="doc-change-note"
                        v-model="form.change_note"
                        class="mt-1 block w-full"
                        placeholder="What changed?"
                    />
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t">
                    <SecondaryButton @click="emit('close')">Cancel</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving...' : (document?.slug ? 'Save New Version' : 'Create Document') }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
