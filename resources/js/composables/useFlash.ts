// resources/js/composables/useFlash.ts

import { usePage } from '@inertiajs/vue3';
import type { FlashMessages } from '@/types';

/**
 * Composable for accessing Inertia flash messages.
 * Flash messages are shared via HandleInertiaRequests middleware.
 *
 * Usage:
 *   const { flash, hasFlash } = useFlash();
 *   if (hasFlash.value) { ... }
 */
export function useFlash() {
    const page = usePage();

    const flash = computed<FlashMessages>(() => {
        return (page.props.flash as FlashMessages) ?? {};
    });

    const hasFlash = computed(() => {
        return !!(flash.value.success || flash.value.error || flash.value.warning || flash.value.info);
    });

    return {
        flash,
        hasFlash,
    };
}
