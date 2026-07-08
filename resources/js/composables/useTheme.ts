// resources/js/composables/useTheme.ts

import { usePage } from '@inertiajs/vue3';
import type { Appearance } from '@/types';

/**
 * Composable for applying the admin-wide runtime theme.
 *
 * Runtime kit themes (`main`, `aura`) are both always present in the CSS bundle;
 * the active one is selected by toggling `data-sk-theme` on <html>. The value
 * comes from the admin global `appearance.theme` (DB-backed, no per-user override).
 *
 * Theme model:
 *   - `main`  → the default kit theme; `data-sk-theme` attribute is REMOVED
 *               (unscoped rules apply, no attribute overhead).
 *   - `aura`  → runtime theme; `data-sk-theme="aura"` set on <html>; CSS rules
 *               scoped to `html[data-sk-theme='aura']` take over.
 *   - custom  → build-time slot-override themes; NOT handled here; attribute is
 *               removed so the build-time `_active.css` import governs layout.
 *
 * FOUC strategy: the first-paint flash is killed by a synchronous inline script
 * in the root blade (`app.blade.php`) that sets `data-sk-theme` from the same
 * server `appearance.theme` default BEFORE paint. This composable only touches
 * the DOM in `onMounted` and applies the identical value, so it runs
 * idempotently — a re-apply that never flips what the inline script already set.
 * (Kept because it also reacts to live Inertia prop changes, e.g. after saving
 * the Görünüm tab, which the one-shot inline script cannot.)
 *
 * Inertia partial-reload guard: when the `appearance` prop is absent in a partial
 * reload the composable does NOTHING (does not fall back to FALLBACK object),
 * preserving whatever the DOM already has. This prevents the fallback `theme:'main'`
 * from accidentally clearing a live `data-sk-theme="aura"` attribute mid-session.
 */

/** The default runtime theme list used as a guard when the server doesn't send it. */
const DEFAULT_RUNTIME_THEMES: string[] = ['main', 'aura'];

export function useTheme() {
    const page = usePage();

    /**
     * The resolved runtime theme name from the Inertia shared props.
     * Returns `undefined` when the `appearance` prop is absent (partial reload) —
     * intentional: the composable must not touch the DOM in that case.
     */
    const theme = computed<string | undefined>(() => {
        const appearance = page.props.appearance as Appearance | undefined;
        // Prop absent → partial reload; guard: leave the DOM untouched.
        if (appearance === undefined) {
            return undefined;
        }
        return appearance.theme;
    });

    /**
     * The set of runtime (instant) themes. Falls back to the kit default list
     * when the server doesn't include `runtime_themes` in the payload
     * (e.g. the global share strip it outside the settings page).
     */
    const runtimeThemes = computed<string[]>(() => {
        const appearance = page.props.appearance as Appearance | undefined;
        return appearance?.runtime_themes ?? DEFAULT_RUNTIME_THEMES;
    });

    /**
     * Apply or remove the `data-sk-theme` attribute on <html>.
     *
     * - `main` or unknown runtime theme → remove attribute (main = default, no attr).
     * - other runtime themes (e.g. `aura`) → set `data-sk-theme`.
     * - custom/build-time themes → remove attribute (build-time `_active.css` governs).
     * - `undefined` (partial reload, prop absent) → NO-OP; DOM untouched.
     */
    function applyTheme(value: string | undefined): void {
        if (typeof document === 'undefined') {
            // SSR-safe: never touch DOM on the server.
            return;
        }
        if (value === undefined) {
            // Partial reload — appearance prop absent; preserve current DOM state.
            return;
        }

        const isRuntime = runtimeThemes.value.includes(value);

        if (isRuntime && value !== 'main') {
            document.documentElement.dataset.skTheme = value;
        } else {
            // main, unknown, or custom (build-time) → clear the attribute.
            delete document.documentElement.dataset.skTheme;
        }
    }

    /**
     * Run `fn` (a DOM mutation) with all CSS transitions globally suppressed for
     * the single frame in which it commits, then re-enable them on the next frame.
     *
     * Used only for the FIRST theme application on mount: that happens AFTER the
     * initial paint, so flipping `data-sk-theme` from absent → a runtime theme
     * whose layout differs from `main` (e.g. `aura` repositions the sidebar and
     * insets the main panel) would otherwise ANIMATE via the layout's
     * `transition-[width,transform] duration-300` rules — a visible ~300ms slide
     * on every load/refresh. Snapping it in with transitions off removes the
     * slide; the layout still lands in the same place.
     */
    function withTransitionsSuppressed(fn: () => void): void {
        if (typeof document === 'undefined') {
            fn();
            return;
        }
        const style = document.createElement('style');
        style.appendChild(document.createTextNode('*,*::before,*::after{transition:none !important}'));
        document.head.appendChild(style);
        fn();
        // Force a synchronous reflow so the new layout commits while transitions
        // are still off, then drop the override on the next frame so genuine
        // interactions (sidebar toggle, live theme switch) keep their animations.
        void document.documentElement.offsetHeight;
        requestAnimationFrame(() => style.remove());
    }

    // React to Inertia navigations / prop updates (e.g. after save on Settings page).
    watch(theme, (val) => applyTheme(val), { immediate: false });

    // Apply on mount — resolves the initial value once the DOM is available.
    // Suppress transitions so the first paint's `main` layout snaps to the active
    // runtime theme instead of sliding into place.
    onMounted(() => {
        withTransitionsSuppressed(() => applyTheme(theme.value));
    });

    return {
        theme,
        runtimeThemes,
        applyTheme,
    };
}
