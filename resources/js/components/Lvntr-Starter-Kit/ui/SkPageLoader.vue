<!-- resources/js/components/Lvntr-Starter-Kit/ui/SkPageLoader.vue -->
<script setup lang="ts">
    import { trans } from 'laravel-vue-i18n';
    import { computed } from 'vue';
    import { usePageLoading } from '@/composables/usePageLoading';

    // Full-screen animated loading overlay for Inertia page switches. Replaces
    // the default top NProgress bar with an animated background + a large
    // letter-by-letter waving word (see theme/main/components/page-loader.css).
    // `delay` mirrors Inertia's old 250ms progress delay so quick navigations
    // never flash the overlay.
    const { delay = 250 } = defineProps<{ delay?: number }>();

    const { isLoading } = usePageLoading(delay);

    // Split the localized word into characters for the staggered wave animation.
    const chars = computed(() => [...trans('sk-layout.loading')]);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isLoading" class="sk-page-loader" role="status" aria-live="polite">
                <div class="sk-page-loader__grid" aria-hidden="true"></div>
                <div class="sk-page-loader__rays" aria-hidden="true"></div>
                <div class="sk-page-loader__blob sk-page-loader__blob--1" aria-hidden="true"></div>
                <div class="sk-page-loader__blob sk-page-loader__blob--2" aria-hidden="true"></div>
                <div class="sk-page-loader__blob sk-page-loader__blob--3" aria-hidden="true"></div>
                <div class="sk-page-loader__veil" aria-hidden="true"></div>

                <div class="sk-page-loader__word">
                    <span class="sk-page-loader__shimmer">
                        <span
                            v-for="(ch, i) in chars"
                            :key="i"
                            class="sk-page-loader__ch"
                            :style="{ '--d': `${(i * 0.07).toFixed(2)}s` }"
                        >{{ ch }}</span>
                    </span>
                    <span class="sk-page-loader__dots" aria-hidden="true">
                        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
                    </span>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
