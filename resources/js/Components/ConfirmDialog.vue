<script setup lang="ts">
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps<{
    show: boolean;
    title: string;
    message: string;
    confirmLabel?: string;
    processing?: boolean;
}>();

const emit = defineEmits<{
    confirm: [];
    cancel: [];
}>();
</script>

<template>
    <Modal :show="show" @close="emit('cancel')" max-width="md">
        <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900">{{ title }}</h3>
            <p class="mt-2 text-sm text-gray-600">{{ message }}</p>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="emit('cancel')">Cancel</SecondaryButton>
                <DangerButton @click="emit('confirm')" :disabled="processing">
                    {{ confirmLabel ?? 'Confirm' }}
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>
