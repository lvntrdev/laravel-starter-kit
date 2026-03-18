// resources/js/composables/useSidebar.ts

/**
 * Composable for managing admin sidebar state.
 * Persists collapsed state in localStorage so it survives page refreshes.
 * Handles mobile open/close separately from desktop collapsed state.
 */
export function useSidebar() {
    const STORAGE_KEY = 'admin-sidebar-collapsed';
    const MOBILE_BREAKPOINT = 1024;

    /** Whether sidebar is collapsed on desktop */
    const isCollapsed = useLocalStorage(STORAGE_KEY, false);

    /** Whether sidebar is open on mobile (overlay mode) */
    const isMobileOpen = ref(false);

    /** Whether we are on a mobile viewport */
    const isMobile = ref(false);

    /**
     * Toggle sidebar collapsed state on desktop.
     */
    function toggle() {
        if (isMobile.value) {
            isMobileOpen.value = !isMobileOpen.value;
        } else {
            isCollapsed.value = !isCollapsed.value;
        }
    }

    /**
     * Open sidebar on mobile.
     */
    function openMobile() {
        isMobileOpen.value = true;
    }

    /**
     * Close sidebar on mobile.
     */
    function closeMobile() {
        isMobileOpen.value = false;
    }

    /**
     * Check viewport and update mobile state.
     */
    function checkViewport() {
        if (typeof window !== 'undefined') {
            isMobile.value = window.innerWidth < MOBILE_BREAKPOINT;
            if (!isMobile.value) {
                isMobileOpen.value = false;
            }
        }
    }

    onMounted(() => {
        checkViewport();
        window.addEventListener('resize', checkViewport);
    });

    onUnmounted(() => {
        window.removeEventListener('resize', checkViewport);
    });

    return {
        isCollapsed,
        isMobileOpen,
        isMobile,
        toggle,
        openMobile,
        closeMobile,
    };
}
