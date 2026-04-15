<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    links: PaginationLink[];
}>();
</script>

<template>
    <nav v-if="links.length > 3" class="flex items-center justify-center gap-1 mt-6">
        <template v-for="(link, index) in links" :key="index">
            <Link
                v-if="link.url"
                :href="link.url"
                class="px-3 py-1.5 text-sm rounded-md transition-colors"
                :class="link.active
                    ? 'bg-indigo-600 text-white font-medium'
                    : 'text-gray-600 hover:bg-gray-100'"
                v-html="link.label"
                preserve-scroll
            />
            <span
                v-else
                class="px-3 py-1.5 text-sm text-gray-400"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
