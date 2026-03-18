<!-- resources/js/components/Skeleton/SkeletonCard.vue -->
<template>
    <div class="rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-5">
        <slot>
            <div class="flex items-center justify-between">
                <div class="flex-1 space-y-2">
                    <SkeletonBox width="40%" height="0.75rem" />
                    <SkeletonBox width="60%" height="1.5rem" />
                </div>
                <SkeletonBox width="2.5rem" height="2.5rem" rounded="rounded-full" />
            </div>
        </slot>
    </div>
</template>
