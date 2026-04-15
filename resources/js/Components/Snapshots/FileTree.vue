<script setup lang="ts">
import { ref } from 'vue';

interface TreeNode {
    name: string;
    type: 'file' | 'dir';
    size?: number;
    children?: TreeNode[];
}

defineProps<{
    nodes: TreeNode[];
    depth?: number;
}>();

const expanded = ref<Record<string, boolean>>({});

const toggle = (name: string) => {
    expanded.value[name] = !expanded.value[name];
};

const isExpanded = (name: string) => expanded.value[name] ?? false;

const formatSize = (bytes?: number) => {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
};

const getFileIcon = (name: string) => {
    const ext = name.split('.').pop()?.toLowerCase();
    const icons: Record<string, string> = {
        php: 'text-purple-500',
        vue: 'text-green-500',
        ts: 'text-blue-500',
        js: 'text-yellow-500',
        json: 'text-yellow-600',
        css: 'text-pink-500',
        md: 'text-gray-500',
        sql: 'text-orange-500',
        env: 'text-red-500',
        yml: 'text-indigo-500',
        yaml: 'text-indigo-500',
    };
    return icons[ext || ''] || 'text-gray-400';
};
</script>

<template>
    <ul :class="['text-sm', depth ? '' : '']">
        <li v-for="node in nodes" :key="node.name" class="select-none">
            <!-- Directory -->
            <div
                v-if="node.type === 'dir'"
                @click="toggle(node.name)"
                class="flex items-center gap-1.5 py-0.5 px-1 rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                :style="{ paddingLeft: (depth || 0) * 16 + 'px' }"
            >
                <svg
                    class="w-3.5 h-3.5 text-gray-400 transition-transform shrink-0"
                    :class="{ 'rotate-90': isExpanded(node.name) }"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <svg class="w-4 h-4 text-yellow-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                </svg>
                <span class="text-gray-700 dark:text-gray-300 truncate">{{ node.name }}</span>
                <span v-if="node.children" class="text-xs text-gray-400 ml-auto shrink-0">{{ node.children.length }}</span>
            </div>

            <!-- Directory children -->
            <FileTree
                v-if="node.type === 'dir' && isExpanded(node.name) && node.children"
                :nodes="node.children"
                :depth="(depth || 0) + 1"
            />

            <!-- File -->
            <div
                v-else-if="node.type === 'file'"
                class="flex items-center gap-1.5 py-0.5 px-1 rounded hover:bg-gray-50 dark:hover:bg-gray-750"
                :style="{ paddingLeft: ((depth || 0) * 16) + 20 + 'px' }"
            >
                <svg :class="['w-3.5 h-3.5 shrink-0', getFileIcon(node.name)]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span class="text-gray-600 dark:text-gray-400 truncate">{{ node.name }}</span>
                <span v-if="node.size" class="text-xs text-gray-400 ml-auto shrink-0">{{ formatSize(node.size) }}</span>
            </div>
        </li>
    </ul>
</template>
