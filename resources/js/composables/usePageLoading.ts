// resources/js/composables/usePageLoading.ts

/**
 * Composable for tracking Inertia page navigation loading state.
 * Uses native browser events (inertia:start/finish) for SSR safety.
 * Includes a configurable delay to avoid flashing skeleton on fast navigations.
 */
export function usePageLoading(delay = 150) {
    const isNavigating = ref(false);
    const isLoading = ref(false);

    let timeout: ReturnType<typeof setTimeout> | null = null;

    function clearDelay() {
        if (timeout) {
            clearTimeout(timeout);
            timeout = null;
        }
    }

    function onStart() {
        clearDelay();
        isNavigating.value = true;

        if (delay > 0) {
            timeout = setTimeout(() => {
                isLoading.value = true;
            }, delay);
        } else {
            isLoading.value = true;
        }
    }

    function onFinish() {
        clearDelay();
        isNavigating.value = false;
        isLoading.value = false;
    }

    onMounted(() => {
        document.addEventListener('inertia:start', onStart);
        document.addEventListener('inertia:finish', onFinish);
    });

    onUnmounted(() => {
        clearDelay();
        document.removeEventListener('inertia:start', onStart);
        document.removeEventListener('inertia:finish', onFinish);
    });

    return {
        /** True when skeleton should be displayed (after delay) */
        isLoading: readonly(isLoading),
        /** True immediately when navigation starts */
        isNavigating: readonly(isNavigating),
    };
}
