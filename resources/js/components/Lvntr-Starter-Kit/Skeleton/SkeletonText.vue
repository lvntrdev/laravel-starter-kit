<!-- resources/js/components/Skeleton/SkeletonText.vue -->
<script setup lang="ts">
    withDefaults(
        defineProps<{
            lines?: number;
            lastWidth?: string;
        }>(),
        {
            lines: 3,
            lastWidth: '60%',
        },
    );
</script>

<template>
    <div class="space-y-2">
        <template v-for="i in lines" :key="i">
            <SkeletonBox :width="i === lines && lines > 1 ? lastWidth : '100%'" height="0.875rem" />
        </template>
    </div>
</template>
