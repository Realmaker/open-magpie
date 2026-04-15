<script setup lang="ts">
import { computed } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';

const props = defineProps<{
    content: string;
}>();

const renderedHtml = computed(() => {
    if (!props.content) return '';
    const rawHtml = marked.parse(props.content, { async: false }) as string;
    return DOMPurify.sanitize(rawHtml);
});
</script>

<template>
    <div class="markdown-content" v-html="renderedHtml" />
</template>

<style scoped>
.markdown-content :deep(h1) { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; margin-top: 1.5rem; }
.markdown-content :deep(h2) { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; margin-top: 1.25rem; }
.markdown-content :deep(h3) { font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem; margin-top: 1rem; }
.markdown-content :deep(p) { margin-bottom: 0.75rem; line-height: 1.625; }
.markdown-content :deep(ul) { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 0.75rem; }
.markdown-content :deep(ol) { list-style-type: decimal; padding-left: 1.5rem; margin-bottom: 0.75rem; }
.markdown-content :deep(li) { margin-bottom: 0.25rem; }
.markdown-content :deep(code) { background-color: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.875rem; }
.markdown-content :deep(pre) { background-color: #1f2937; color: #e5e7eb; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; margin-bottom: 1rem; }
.markdown-content :deep(pre code) { background-color: transparent; padding: 0; color: inherit; }
.markdown-content :deep(blockquote) { border-left: 3px solid #6366f1; padding-left: 1rem; color: #6b7280; margin-bottom: 0.75rem; }
.markdown-content :deep(table) { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
.markdown-content :deep(th) { background-color: #f9fafb; padding: 0.5rem; border: 1px solid #e5e7eb; font-weight: 600; text-align: left; }
.markdown-content :deep(td) { padding: 0.5rem; border: 1px solid #e5e7eb; }
.markdown-content :deep(a) { color: #4f46e5; text-decoration: underline; }
.markdown-content :deep(hr) { border-top: 1px solid #e5e7eb; margin: 1.5rem 0; }
.markdown-content :deep(img) { max-width: 100%; border-radius: 0.5rem; }
</style>
