<!-- resources/js/components/Skeleton/SkeletonBox.vue -->
<script setup lang="ts">
    withDefaults(
        defineProps<{
            width?: string;
            height?: string;
            rounded?: string;
        }>(),
        {
            width: '100%',
            height: '1rem',
            rounded: 'rounded',
        },
    );
</script>

<template>
    <div class="animate-pulse bg-surface-200 dark:bg-surface-700" :class="rounded" :style="{ width, height }" />
</template>
