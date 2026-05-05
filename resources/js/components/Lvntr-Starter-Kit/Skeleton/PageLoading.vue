<!-- resources/js/components/Skeleton/PageLoading.vue -->
<script setup lang="ts">
    import { usePageLoading } from '@/composables/usePageLoading';

    withDefaults(
        defineProps<{
            delay?: number;
        }>(),
        {
            delay: 150,
        },
    );

    const { isLoading } = usePageLoading();
</script>

<template>
    <div class="relative">
        <!-- Skeleton fallback -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isLoading" class="absolute inset-0 z-10">
                <slot name="skeleton">
                    <!-- Default skeleton: title + stats cards + table -->
                    <div class="space-y-6">
                        <div class="space-y-2">
                            <SkeletonBox width="12rem" height="1.75rem" />
                            <SkeletonBox width="16rem" height="0.875rem" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <SkeletonCard v-for="i in 4" :key="i" />
                        </div>

                        <SkeletonTable :rows="5" :columns="4" />
                    </div>
                </slot>
            </div>
        </Transition>

        <!-- Actual content -->
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-show="!isLoading">
                <slot />
            </div>
        </Transition>
    </div>
</template>
