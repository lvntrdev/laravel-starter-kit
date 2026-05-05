<!-- resources/js/components/Skeleton/SkeletonTable.vue -->
<script setup lang="ts">
    withDefaults(
        defineProps<{
            rows?: number;
            columns?: number;
        }>(),
        {
            rows: 5,
            columns: 4,
        },
    );
</script>

<template>
    <div
        class="rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 overflow-hidden"
    >
        <!-- Header -->
        <div class="flex gap-4 px-5 py-3 border-b border-surface-200 dark:border-surface-700">
            <SkeletonBox
                v-for="col in columns"
                :key="`h-${col}`"
                height="0.875rem"
                :width="`${Math.floor(100 / columns)}%`"
            />
        </div>

        <!-- Rows -->
        <div
            v-for="row in rows"
            :key="`r-${row}`"
            class="flex gap-4 px-5 py-3 border-b border-surface-100 dark:border-surface-800 last:border-b-0"
        >
            <SkeletonBox
                v-for="col in columns"
                :key="`r-${row}-c-${col}`"
                height="0.875rem"
                :width="`${Math.floor(100 / columns)}%`"
            />
        </div>
    </div>
</template>
