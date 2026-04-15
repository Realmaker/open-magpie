<script setup lang="ts">
interface Worker {
    id: number;
    name: string;
    machine_id: string;
    status: 'online' | 'offline' | 'busy';
    is_online: boolean;
    version?: string;
    os_info?: string;
    max_parallel_jobs: number;
    current_jobs?: number[];
    last_heartbeat_at?: string;
}

defineProps<{
    worker: Worker;
}>();

function relativeTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now.getTime() - date.getTime()) / 1000);
    if (seconds < 60) return 'just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    return `${Math.floor(seconds / 86400)}d ago`;
}
</script>

<template>
    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
        <div class="mb-2 flex items-center justify-between">
            <h4 class="font-semibold text-gray-900 dark:text-gray-100">{{ worker.name }}</h4>
            <span
                class="inline-flex h-3 w-3 rounded-full"
                :class="{
                    'bg-green-500': worker.is_online && worker.status === 'online',
                    'bg-yellow-500 animate-pulse': worker.is_online && worker.status === 'busy',
                    'bg-gray-400': !worker.is_online,
                }"
                :title="worker.is_online ? worker.status : 'offline'"
            ></span>
        </div>
        <div class="space-y-1 text-xs text-gray-500 dark:text-gray-400">
            <div>{{ worker.machine_id }}</div>
            <div v-if="worker.os_info">{{ worker.os_info }}</div>
            <div v-if="worker.version">v{{ worker.version }}</div>
            <div>Max Jobs: {{ worker.max_parallel_jobs }}</div>
            <div v-if="worker.last_heartbeat_at">
                Heartbeat: {{ relativeTime(worker.last_heartbeat_at) }}
            </div>
        </div>
    </div>
</template>
