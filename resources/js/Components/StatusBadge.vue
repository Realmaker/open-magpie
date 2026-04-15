<script setup lang="ts">
const props = defineProps<{
    label: string;
    color?: 'green' | 'yellow' | 'blue' | 'red' | 'gray' | 'purple' | 'indigo' | 'orange' | 'pink';
}>();

const colorClasses: Record<string, string> = {
    green: 'bg-green-100 text-green-800',
    yellow: 'bg-yellow-100 text-yellow-800',
    blue: 'bg-blue-100 text-blue-800',
    red: 'bg-red-100 text-red-800',
    gray: 'bg-gray-100 text-gray-800',
    purple: 'bg-purple-100 text-purple-800',
    indigo: 'bg-indigo-100 text-indigo-800',
    orange: 'bg-orange-100 text-orange-800',
    pink: 'bg-pink-100 text-pink-800',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="colorClasses[color ?? 'gray']"
    >
        {{ label }}
    </span>
</template>
