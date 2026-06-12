// resources/js/composables/useAppearanceDefaults.ts

import { usePage } from '@inertiajs/vue3';
import type { Appearance } from '@/types';

/**
 * Reads the global appearance defaults shared by `HandleInertiaRequests` on
 * every Inertia response (including the login / installer screens).
 *
 * This is the GLOBAL DEFAULT layer of the two-layer appearance model:
 *   - admin sets a global default (accent / dark / sidebar / theme + logo/favicon),
 *   - each user may OVERRIDE it via the header popover (persisted in localStorage).
 *
 * `useAccentColor` / `useDarkMode` consume these defaults to seed their initial
 * value when the user has made no personal choice yet; once the user picks, the
 * localStorage value wins. Layouts use it to apply the look on boot and to wire
 * the shared logo / favicon URLs.
 *
 * SSR-safe: it only reads Inertia page props (never the DOM), so it can run in
 * `setup` on the server. DOM application stays in the consumers' `onMounted`.
 */

/** Neutral fallback used when the `appearance` prop is absent (e.g. a partial reload). */
const FALLBACK: Appearance = {
    theme: 'main',
    available_themes: ['main'],
    accent_color: 'default',
    dark_mode_default: false,
    sidebar_style: 'colored',
    logo_light_url: null,
    logo_dark_url: null,
    favicon_url: null,
};

/** localStorage keys for the per-user appearance overrides (header popover). */
const ACCENT_KEY = 'admin-accent-color';
const DARK_KEY = 'admin-dark-mode';
const SIDEBAR_KEY = 'admin-sidebar-style';
/** Set once after the legacy-storage cleanup has run for this browser. */
const LEGACY_MIGRATED_KEY = 'admin-appearance-migrated';

/**
 * One-time cleanup of legacy auto-persisted appearance values.
 *
 * Before the admin global-default layer existed, the appearance composables used
 * `useLocalStorage(key, default)` with VueUse's default `writeDefaults: true`,
 * which EAGERLY wrote the hardcoded default (`'default'` accent / `false` dark /
 * `'colored'` sidebar) into every user's localStorage on first load — even for
 * users who never opened the header popover. That stored value is
 * indistinguishable from an explicit choice and masks the new global default
 * forever (the reported "accent never follows the admin setting" bug).
 *
 * The global-default feature is brand new, so no user has yet *explicitly* chosen
 * a value equal to the old default in order to override a global — a stored value
 * equal to the old hardcoded default can therefore only be legacy noise. Remove
 * exactly those once so the global default can seed; any non-default stored value
 * (a real colour / dark / light choice) is preserved. Guarded by a flag so it
 * runs a single time per browser and never touches a value the user sets AFTER
 * the migration. SSR-safe (no-op without `window`).
 */
export function migrateLegacyAppearanceStorage(): void {
    if (typeof window === 'undefined') {
        return;
    }
    const ls = window.localStorage;
    if (ls.getItem(LEGACY_MIGRATED_KEY) !== null) {
        return;
    }
    if (ls.getItem(ACCENT_KEY) === 'default') {
        ls.removeItem(ACCENT_KEY);
    }
    if (ls.getItem(DARK_KEY) === 'false') {
        ls.removeItem(DARK_KEY);
    }
    if (ls.getItem(SIDEBAR_KEY) === 'colored') {
        ls.removeItem(SIDEBAR_KEY);
    }
    ls.setItem(LEGACY_MIGRATED_KEY, '1');
}

export function useAppearanceDefaults() {
    const page = usePage();

    /** The full shared appearance object, falling back to neutral defaults. */
    const appearance = computed<Appearance>(() => {
        return (page.props.appearance as Appearance | undefined) ?? FALLBACK;
    });

    /** Global default accent color name (`'default'` = kit primary). */
    const defaultAccent = computed(() => appearance.value.accent_color ?? FALLBACK.accent_color);

    /** Global default dark-mode flag. */
    const defaultDarkMode = computed(() => appearance.value.dark_mode_default ?? FALLBACK.dark_mode_default);

    /** Global default sidebar surface treatment. */
    const defaultSidebarStyle = computed(() => appearance.value.sidebar_style ?? FALLBACK.sidebar_style);

    /**
     * Resolved light-variant logo URL. The header/sidebar logo uses this; falls
     * back to the legacy `appLogo` (general.logo) prop server-side already, so
     * `null` here genuinely means "no logo, show the text/icon fallback".
     */
    const logoLightUrl = computed(() => appearance.value.logo_light_url);

    /** Resolved dark-variant logo URL (for dark surfaces / dark mode). */
    const logoDarkUrl = computed(() => appearance.value.logo_dark_url);

    /** Resolved favicon URL for the `<link rel="icon">`. */
    const faviconUrl = computed(() => appearance.value.favicon_url);

    /**
     * Point the document favicon at the admin-set URL (when present). Updates the
     * existing `<link rel="icon">` in place, or creates one, so the browser tab
     * reflects the global default. SSR-safe — no-op when `document` is absent or
     * no favicon URL is configured (the blade default `/favicon.ico` stays).
     * Call inside `onMounted`.
     */
    function applyFavicon(): void {
        if (typeof document === 'undefined') {
            return;
        }
        const url = faviconUrl.value;
        if (!url) {
            return;
        }

        // The blade root ships multiple `<link rel="icon">` variants (ico + svg).
        // Drop the extras so the admin-set icon is the single one the browser
        // resolves, then point the first (or a fresh) link at the configured URL.
        const links = Array.from(document.querySelectorAll<HTMLLinkElement>('link[rel="icon"]'));
        let link = links.shift() ?? null;
        links.forEach((extra) => extra.remove());

        if (!link) {
            link = document.createElement('link');
            link.rel = 'icon';
            document.head.appendChild(link);
        }
        link.href = url;
        // Clear any sizes/type that pinned the blade default (e.g. svg variant)
        // so the admin-set icon is the one the browser resolves.
        link.removeAttribute('sizes');
        link.removeAttribute('type');
    }

    return {
        appearance,
        defaultAccent,
        defaultDarkMode,
        defaultSidebarStyle,
        logoLightUrl,
        logoDarkUrl,
        faviconUrl,
        applyFavicon,
    };
}
