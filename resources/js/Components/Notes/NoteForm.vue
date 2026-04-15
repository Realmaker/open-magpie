<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps<{
    projectSlug: string;
    parentId?: number | null;
    placeholder?: string;
}>();

const emit = defineEmits<{
    posted: [];
}>();

const form = useForm({
    content: '',
    parent_id: props.parentId ?? null,
});

const submit = () => {
    form.post(route('projects.notes.store', { slug: props.projectSlug }), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('content');
            emit('posted');
        },
    });
};
</script>

<template>
    <form @submit.prevent="submit" class="space-y-3">
        <textarea
            v-model="form.content"
            rows="3"
            :placeholder="placeholder ?? 'Write a note... (Markdown supported)'"
            class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
            required
        />
        <InputError :message="form.errors.content" />
        <div class="flex justify-end">
            <PrimaryButton type="submit" :disabled="form.processing || !form.content.trim()">
                {{ form.processing ? 'Posting...' : 'Post Note' }}
            </PrimaryButton>
        </div>
    </form>
</template>
