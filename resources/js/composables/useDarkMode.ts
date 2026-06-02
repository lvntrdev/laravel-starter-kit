// resources/js/composables/useDarkMode.ts

/**
 * Composable for managing dark mode.
 * Toggles `.dark` class on document.documentElement which is
 * the selector PrimeVue is configured to use (darkModeSelector: '.dark').
 * Persists preference in localStorage.
 */
export function useDarkMode() {
    const STORAGE_KEY = 'admin-dark-mode';

    /** Whether dark mode is active */
    const isDark = useLocalStorage(STORAGE_KEY, false);

    /**
     * Apply or remove the .dark class on <html>.
     */
    function applyDarkClass(dark: boolean) {
        if (typeof document !== 'undefined') {
            document.documentElement.classList.toggle('dark', dark);
        }
    }

    /**
     * Toggle dark mode on/off.
     */
    function toggleDark() {
        isDark.value = !isDark.value;
    }

    // Watch and sync
    watch(isDark, (val) => applyDarkClass(val), { immediate: false });

    // Apply on mount
    onMounted(() => {
        applyDarkClass(isDark.value);
    });

    return {
        isDark,
        toggleDark,
    };
}
