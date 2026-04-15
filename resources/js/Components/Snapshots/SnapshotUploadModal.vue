<script setup lang="ts">
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps<{
    show: boolean;
    projectSlug: string;
}>();

const emit = defineEmits(['close']);

const form = useForm({
    file: null as File | null,
    change_note: '',
});

const fileInput = ref<HTMLInputElement>();
const fileInfo = ref('');

watch(() => props.show, (val) => {
    if (val) {
        form.reset();
        form.clearErrors();
        fileInfo.value = '';
    }
});

const onFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    if (file) {
        form.file = file;
        const sizeMB = (file.size / 1048576).toFixed(1);
        fileInfo.value = `${file.name} (${sizeMB} MB)`;
    }
};

const submit = () => {
    form.post(route('projects.snapshots.store', { slug: props.projectSlug }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            emit('close');
            form.reset();
        },
    });
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="emit('close')">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-1">
                Snapshot hochladen
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                ZIP-Datei mit den Projektdateien hochladen (max 50 MB).
            </p>

            <form @submit.prevent="submit">
                <!-- File -->
                <div class="mb-4">
                    <InputLabel value="ZIP-Datei" />
                    <div class="mt-1">
                        <label
                            class="flex items-center justify-center w-full h-24 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-indigo-400 dark:hover:border-indigo-500 transition-colors"
                            :class="{ 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20': form.file }"
                        >
                            <div class="text-center">
                                <svg v-if="!form.file" class="mx-auto w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ fileInfo || 'Klick oder Datei hierhin ziehen' }}
                                </p>
                            </div>
                            <input
                                ref="fileInput"
                                type="file"
                                accept=".zip"
                                class="hidden"
                                @change="onFileChange"
                            />
                        </label>
                    </div>
                    <InputError :message="form.errors.file" class="mt-1" />
                </div>

                <!-- Change Note -->
                <div class="mb-6">
                    <InputLabel for="change-note" value="Aenderungsnotiz (optional)" />
                    <TextInput
                        id="change-note"
                        v-model="form.change_note"
                        type="text"
                        placeholder="z.B. Auth-System implementiert"
                        class="w-full mt-1"
                    />
                    <InputError :message="form.errors.change_note" class="mt-1" />
                </div>

                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900"
                    >
                        Abbrechen
                    </button>
                    <PrimaryButton :disabled="form.processing || !form.file">
                        <span v-if="form.processing">Wird hochgeladen...</span>
                        <span v-else>Hochladen</span>
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </Modal>
</template>
