<script setup lang="ts">
defineProps<{
    status: string;
}>();

function badgeClass(status: string): string {
    const classes: Record<string, string> = {
        pending_approval: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
        approved: 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200',
        queued: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        claimed: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        running: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 animate-pulse',
        done: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    };
    return classes[status] || classes.cancelled;
}

function formatStatus(status: string): string {
    return status.replace(/_/g, ' ');
}
</script>

<template>
    <span
        :class="['inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize', badgeClass(status)]"
    >
        {{ formatStatus(status) }}
    </span>
</template>
