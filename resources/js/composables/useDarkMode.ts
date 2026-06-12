// resources/js/composables/useDarkMode.ts

import { migrateLegacyAppearanceStorage, useAppearanceDefaults } from '@/composables/useAppearanceDefaults';

/**
 * Composable for managing dark mode.
 * Toggles `.dark` class on document.documentElement which is
 * the selector PrimeVue is configured to use (darkModeSelector: '.dark').
 * Persists preference in localStorage.
 *
 * Two-layer model: when the user has NO personal choice yet (no localStorage
 * key), the initial value comes from the admin GLOBAL DEFAULT
 * (`appearance.dark_mode_default`) instead of a hardcoded `false`. Once the user
 * toggles, the localStorage value is written and from then on it WINS over the
 * global default. SSR-safe: the DOM is only touched in `onMounted`.
 */
export function useDarkMode() {
    const STORAGE_KEY = 'admin-dark-mode';

    const { defaultDarkMode } = useAppearanceDefaults();

    // Clear legacy auto-persisted defaults once so a stale stored value can't mask
    // the admin global default (must run BEFORE the localStorage read below).
    migrateLegacyAppearanceStorage();

    // Has the user made an explicit choice already? Read raw localStorage BEFORE
    // `useLocalStorage`, which writes its initial value on first access and would
    // otherwise mask the "no override yet" state. SSR-safe (guarded).
    const hasUserChoice =
        typeof window !== 'undefined' && window.localStorage.getItem(STORAGE_KEY) !== null;

    // Seed from the global default only when the user hasn't chosen; otherwise the
    // stored value is loaded by useLocalStorage and the seed is ignored.
    const initial = hasUserChoice ? false : defaultDarkMode.value;

    // `writeDefaults: false` — do NOT persist the seeded global default; only an
    // explicit toggleDark (user choice) writes to storage, keeping the global
    // default live across reloads until the user actually toggles.
    /** Whether dark mode is active (user override, or global default until the user picks). */
    const isDark = useLocalStorage(STORAGE_KEY, initial, { writeDefaults: false });

    /**
     * Apply or remove the .dark class on <html>.
     */
    function applyDarkClass(dark: boolean) {
        if (typeof document !== 'undefined') {
            document.documentElement.classList.toggle('dark', dark);
        }
    }

    /**
     * Toggle dark mode on/off. The first toggle persists the user override.
     */
    function toggleDark() {
        isDark.value = !isDark.value;
    }

    // Watch and sync
    watch(isDark, (val) => applyDarkClass(val), { immediate: false });

    // Apply on mount (resolves the global-default-vs-override value once — no flash).
    onMounted(() => {
        applyDarkClass(isDark.value);
    });

    return {
        isDark,
        toggleDark,
    };
}
