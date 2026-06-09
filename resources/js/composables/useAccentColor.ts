// resources/js/composables/useAccentColor.ts

import { updatePrimaryPalette } from '@primevue/themes';

/**
 * Composable for the admin accent color.
 *
 * Selecting a color updates BOTH in a single action:
 *   1. the PrimeVue primary palette (buttons, links, focus rings, active states…)
 *      via `updatePrimaryPalette`, and
 *   2. the admin sidebar surface (LIGHT mode only), by toggling the
 *      `data-sk-accent` marker on <html> — the deep tint is themed in CSS
 *      (tokens.css → [data-sk-accent]:not(.dark)). In DARK mode the sidebar stays
 *      the neutral dark surface; only the primary/active item/buttons carry the
 *      accent there.
 *
 * Independently, the sidebar STYLE (`colored` | `light`) decides — in LIGHT mode
 * only — whether the sidebar surface is the deep accent tint or a white/light
 * surface. It toggles the `data-sk-sidebar` marker on <html>.
 *
 * Both choices are persisted in localStorage and re-applied on load — mirroring
 * the `useDarkMode` pattern (apply in onMounted; never touch the DOM during SSR).
 */

// Selectable color names. Values come from TAILWIND_PALETTES below.
// The last four (taupe/mauve/mist/olive) are custom muted tones — see the
// "CUSTOM MUTED TONES" block in TAILWIND_PALETTES.
export const ACCENT_COLORS = [
    'slate', 'gray', 'zinc', 'neutral', 'stone',
    'red', 'orange', 'amber', 'yellow', 'lime', 'green',
    'emerald', 'teal', 'cyan', 'sky', 'blue', 'indigo',
    'violet', 'purple', 'fuchsia', 'pink', 'rose',
    'taupe', 'mauve', 'mist', 'olive',
] as const;

export type AccentColor = (typeof ACCENT_COLORS)[number] | 'default';

/** Sidebar surface treatment (LIGHT mode only — dark mode always stays dark). */
export const SIDEBAR_STYLES = ['colored', 'light'] as const;
export type SidebarStyle = (typeof SIDEBAR_STYLES)[number];

/**
 * The REAL Tailwind v4 default palettes (oklch), copied verbatim from
 * `tailwindcss/theme.css`. Inlined on purpose:
 *   - Tailwind tree-shakes unused `--color-*` variables, so `var(--color-indigo-500)`
 *     is NOT reliably present at runtime; and
 *   - the kit's PrimeVue preset extends Aura, whose `{indigo.x}` primitives are
 *     Tailwind v3 hex — not the v4 oklch tones used across the rest of the UI.
 * Inlining the v4 oklch palettes guarantees the accent colors match the app's
 * genuine Tailwind v4 tones.
 */
export const TAILWIND_PALETTES: Record<string, Record<number, string>> = {
    slate: { 50: 'oklch(98.4% 0.003 247.858)', 100: 'oklch(96.8% 0.007 247.896)', 200: 'oklch(92.9% 0.013 255.508)', 300: 'oklch(86.9% 0.022 252.894)', 400: 'oklch(70.4% 0.04 256.788)', 500: 'oklch(55.4% 0.046 257.417)', 600: 'oklch(44.6% 0.043 257.281)', 700: 'oklch(37.2% 0.044 257.287)', 800: 'oklch(27.9% 0.041 260.031)', 900: 'oklch(20.8% 0.042 265.755)', 950: 'oklch(12.9% 0.042 264.695)' },
    gray: { 50: 'oklch(98.5% 0.002 247.839)', 100: 'oklch(96.7% 0.003 264.542)', 200: 'oklch(92.8% 0.006 264.531)', 300: 'oklch(87.2% 0.01 258.338)', 400: 'oklch(70.7% 0.022 261.325)', 500: 'oklch(55.1% 0.027 264.364)', 600: 'oklch(44.6% 0.03 256.802)', 700: 'oklch(37.3% 0.034 259.733)', 800: 'oklch(27.8% 0.033 256.848)', 900: 'oklch(21% 0.034 264.665)', 950: 'oklch(13% 0.028 261.692)' },
    zinc: { 50: 'oklch(98.5% 0 0)', 100: 'oklch(96.7% 0.001 286.375)', 200: 'oklch(92% 0.004 286.32)', 300: 'oklch(87.1% 0.006 286.286)', 400: 'oklch(70.5% 0.015 286.067)', 500: 'oklch(55.2% 0.016 285.938)', 600: 'oklch(44.2% 0.017 285.786)', 700: 'oklch(37% 0.013 285.805)', 800: 'oklch(27.4% 0.006 286.033)', 900: 'oklch(21% 0.006 285.885)', 950: 'oklch(14.1% 0.005 285.823)' },
    neutral: { 50: 'oklch(98.5% 0 0)', 100: 'oklch(97% 0 0)', 200: 'oklch(92.2% 0 0)', 300: 'oklch(87% 0 0)', 400: 'oklch(70.8% 0 0)', 500: 'oklch(55.6% 0 0)', 600: 'oklch(43.9% 0 0)', 700: 'oklch(37.1% 0 0)', 800: 'oklch(26.9% 0 0)', 900: 'oklch(20.5% 0 0)', 950: 'oklch(14.5% 0 0)' },
    stone: { 50: 'oklch(98.5% 0.001 106.423)', 100: 'oklch(97% 0.001 106.424)', 200: 'oklch(92.3% 0.003 48.717)', 300: 'oklch(86.9% 0.005 56.366)', 400: 'oklch(70.9% 0.01 56.259)', 500: 'oklch(55.3% 0.013 58.071)', 600: 'oklch(44.4% 0.011 73.639)', 700: 'oklch(37.4% 0.01 67.558)', 800: 'oklch(26.8% 0.007 34.298)', 900: 'oklch(21.6% 0.006 56.043)', 950: 'oklch(14.7% 0.004 49.25)' },
    red: { 50: 'oklch(97.1% 0.013 17.38)', 100: 'oklch(93.6% 0.032 17.717)', 200: 'oklch(88.5% 0.062 18.334)', 300: 'oklch(80.8% 0.114 19.571)', 400: 'oklch(70.4% 0.191 22.216)', 500: 'oklch(63.7% 0.237 25.331)', 600: 'oklch(57.7% 0.245 27.325)', 700: 'oklch(50.5% 0.213 27.518)', 800: 'oklch(44.4% 0.177 26.899)', 900: 'oklch(39.6% 0.141 25.723)', 950: 'oklch(25.8% 0.092 26.042)' },
    orange: { 50: 'oklch(98% 0.016 73.684)', 100: 'oklch(95.4% 0.038 75.164)', 200: 'oklch(90.1% 0.076 70.697)', 300: 'oklch(83.7% 0.128 66.29)', 400: 'oklch(75% 0.183 55.934)', 500: 'oklch(70.5% 0.213 47.604)', 600: 'oklch(64.6% 0.222 41.116)', 700: 'oklch(55.3% 0.195 38.402)', 800: 'oklch(47% 0.157 37.304)', 900: 'oklch(40.8% 0.123 38.172)', 950: 'oklch(26.6% 0.079 36.259)' },
    amber: { 50: 'oklch(98.7% 0.022 95.277)', 100: 'oklch(96.2% 0.059 95.617)', 200: 'oklch(92.4% 0.12 95.746)', 300: 'oklch(87.9% 0.169 91.605)', 400: 'oklch(82.8% 0.189 84.429)', 500: 'oklch(76.9% 0.188 70.08)', 600: 'oklch(66.6% 0.179 58.318)', 700: 'oklch(55.5% 0.163 48.998)', 800: 'oklch(47.3% 0.137 46.201)', 900: 'oklch(41.4% 0.112 45.904)', 950: 'oklch(27.9% 0.077 45.635)' },
    yellow: { 50: 'oklch(98.7% 0.026 102.212)', 100: 'oklch(97.3% 0.071 103.193)', 200: 'oklch(94.5% 0.129 101.54)', 300: 'oklch(90.5% 0.182 98.111)', 400: 'oklch(85.2% 0.199 91.936)', 500: 'oklch(79.5% 0.184 86.047)', 600: 'oklch(68.1% 0.162 75.834)', 700: 'oklch(55.4% 0.135 66.442)', 800: 'oklch(47.6% 0.114 61.907)', 900: 'oklch(42.1% 0.095 57.708)', 950: 'oklch(28.6% 0.066 53.813)' },
    lime: { 50: 'oklch(98.6% 0.031 120.757)', 100: 'oklch(96.7% 0.067 122.328)', 200: 'oklch(93.8% 0.127 124.321)', 300: 'oklch(89.7% 0.196 126.665)', 400: 'oklch(84.1% 0.238 128.85)', 500: 'oklch(76.8% 0.233 130.85)', 600: 'oklch(64.8% 0.2 131.684)', 700: 'oklch(53.2% 0.157 131.589)', 800: 'oklch(45.3% 0.124 130.933)', 900: 'oklch(40.5% 0.101 131.063)', 950: 'oklch(27.4% 0.072 132.109)' },
    green: { 50: 'oklch(98.2% 0.018 155.826)', 100: 'oklch(96.2% 0.044 156.743)', 200: 'oklch(92.5% 0.084 155.995)', 300: 'oklch(87.1% 0.15 154.449)', 400: 'oklch(79.2% 0.209 151.711)', 500: 'oklch(72.3% 0.219 149.579)', 600: 'oklch(62.7% 0.194 149.214)', 700: 'oklch(52.7% 0.154 150.069)', 800: 'oklch(44.8% 0.119 151.328)', 900: 'oklch(39.3% 0.095 152.535)', 950: 'oklch(26.6% 0.065 152.934)' },
    emerald: { 50: 'oklch(97.9% 0.021 166.113)', 100: 'oklch(95% 0.052 163.051)', 200: 'oklch(90.5% 0.093 164.15)', 300: 'oklch(84.5% 0.143 164.978)', 400: 'oklch(76.5% 0.177 163.223)', 500: 'oklch(69.6% 0.17 162.48)', 600: 'oklch(59.6% 0.145 163.225)', 700: 'oklch(50.8% 0.118 165.612)', 800: 'oklch(43.2% 0.095 166.913)', 900: 'oklch(37.8% 0.077 168.94)', 950: 'oklch(26.2% 0.051 172.552)' },
    teal: { 50: 'oklch(98.4% 0.014 180.72)', 100: 'oklch(95.3% 0.051 180.801)', 200: 'oklch(91% 0.096 180.426)', 300: 'oklch(85.5% 0.138 181.071)', 400: 'oklch(77.7% 0.152 181.912)', 500: 'oklch(70.4% 0.14 182.503)', 600: 'oklch(60% 0.118 184.704)', 700: 'oklch(51.1% 0.096 186.391)', 800: 'oklch(43.7% 0.078 188.216)', 900: 'oklch(38.6% 0.063 188.416)', 950: 'oklch(27.7% 0.046 192.524)' },
    cyan: { 50: 'oklch(98.4% 0.019 200.873)', 100: 'oklch(95.6% 0.045 203.388)', 200: 'oklch(91.7% 0.08 205.041)', 300: 'oklch(86.5% 0.127 207.078)', 400: 'oklch(78.9% 0.154 211.53)', 500: 'oklch(71.5% 0.143 215.221)', 600: 'oklch(60.9% 0.126 221.723)', 700: 'oklch(52% 0.105 223.128)', 800: 'oklch(45% 0.085 224.283)', 900: 'oklch(39.8% 0.07 227.392)', 950: 'oklch(30.2% 0.056 229.695)' },
    sky: { 50: 'oklch(97.7% 0.013 236.62)', 100: 'oklch(95.1% 0.026 236.824)', 200: 'oklch(90.1% 0.058 230.902)', 300: 'oklch(82.8% 0.111 230.318)', 400: 'oklch(74.6% 0.16 232.661)', 500: 'oklch(68.5% 0.169 237.323)', 600: 'oklch(58.8% 0.158 241.966)', 700: 'oklch(50% 0.134 242.749)', 800: 'oklch(44.3% 0.11 240.79)', 900: 'oklch(39.1% 0.09 240.876)', 950: 'oklch(29.3% 0.066 243.157)' },
    blue: { 50: 'oklch(97% 0.014 254.604)', 100: 'oklch(93.2% 0.032 255.585)', 200: 'oklch(88.2% 0.059 254.128)', 300: 'oklch(80.9% 0.105 251.813)', 400: 'oklch(70.7% 0.165 254.624)', 500: 'oklch(62.3% 0.214 259.815)', 600: 'oklch(54.6% 0.245 262.881)', 700: 'oklch(48.8% 0.243 264.376)', 800: 'oklch(42.4% 0.199 265.638)', 900: 'oklch(37.9% 0.146 265.522)', 950: 'oklch(28.2% 0.091 267.935)' },
    indigo: { 50: 'oklch(96.2% 0.018 272.314)', 100: 'oklch(93% 0.034 272.788)', 200: 'oklch(87% 0.065 274.039)', 300: 'oklch(78.5% 0.115 274.713)', 400: 'oklch(67.3% 0.182 276.935)', 500: 'oklch(58.5% 0.233 277.117)', 600: 'oklch(51.1% 0.262 276.966)', 700: 'oklch(45.7% 0.24 277.023)', 800: 'oklch(39.8% 0.195 277.366)', 900: 'oklch(35.9% 0.144 278.697)', 950: 'oklch(25.7% 0.09 281.288)' },
    violet: { 50: 'oklch(96.9% 0.016 293.756)', 100: 'oklch(94.3% 0.029 294.588)', 200: 'oklch(89.4% 0.057 293.283)', 300: 'oklch(81.1% 0.111 293.571)', 400: 'oklch(70.2% 0.183 293.541)', 500: 'oklch(60.6% 0.25 292.717)', 600: 'oklch(54.1% 0.281 293.009)', 700: 'oklch(49.1% 0.27 292.581)', 800: 'oklch(43.2% 0.232 292.759)', 900: 'oklch(38% 0.189 293.745)', 950: 'oklch(28.3% 0.141 291.089)' },
    purple: { 50: 'oklch(97.7% 0.014 308.299)', 100: 'oklch(94.6% 0.033 307.174)', 200: 'oklch(90.2% 0.063 306.703)', 300: 'oklch(82.7% 0.119 306.383)', 400: 'oklch(71.4% 0.203 305.504)', 500: 'oklch(62.7% 0.265 303.9)', 600: 'oklch(55.8% 0.288 302.321)', 700: 'oklch(49.6% 0.265 301.924)', 800: 'oklch(43.8% 0.218 303.724)', 900: 'oklch(38.1% 0.176 304.987)', 950: 'oklch(29.1% 0.149 302.717)' },
    fuchsia: { 50: 'oklch(97.7% 0.017 320.058)', 100: 'oklch(95.2% 0.037 318.852)', 200: 'oklch(90.3% 0.076 319.62)', 300: 'oklch(83.3% 0.145 321.434)', 400: 'oklch(74% 0.238 322.16)', 500: 'oklch(66.7% 0.295 322.15)', 600: 'oklch(59.1% 0.293 322.896)', 700: 'oklch(51.8% 0.253 323.949)', 800: 'oklch(45.2% 0.211 324.591)', 900: 'oklch(40.1% 0.17 325.612)', 950: 'oklch(29.3% 0.136 325.661)' },
    pink: { 50: 'oklch(97.1% 0.014 343.198)', 100: 'oklch(94.8% 0.028 342.258)', 200: 'oklch(89.9% 0.061 343.231)', 300: 'oklch(82.3% 0.12 346.018)', 400: 'oklch(71.8% 0.202 349.761)', 500: 'oklch(65.6% 0.241 354.308)', 600: 'oklch(59.2% 0.249 0.584)', 700: 'oklch(52.5% 0.223 3.958)', 800: 'oklch(45.9% 0.187 3.815)', 900: 'oklch(40.8% 0.153 2.432)', 950: 'oklch(28.4% 0.109 3.907)' },
    rose: { 50: 'oklch(96.9% 0.015 12.422)', 100: 'oklch(94.1% 0.03 12.58)', 200: 'oklch(89.2% 0.058 10.001)', 300: 'oklch(81% 0.117 11.638)', 400: 'oklch(71.2% 0.194 13.428)', 500: 'oklch(64.5% 0.246 16.439)', 600: 'oklch(58.6% 0.253 17.585)', 700: 'oklch(51.4% 0.222 16.935)', 800: 'oklch(45.5% 0.188 13.697)', 900: 'oklch(41% 0.159 10.272)', 950: 'oklch(27.1% 0.105 12.094)' },

    /* ── CUSTOM MUTED TONES ──────────────────────────────────────────────
       Not part of Tailwind. Hand-tuned low-chroma "earthy" scales that read
       as a muted version of a hue, designed primarily for the deep sidebar
       tint (950) while still giving a usable primary (500). */
    taupe: { 50: 'oklch(97% 0.005 75)', 100: 'oklch(94% 0.008 74)', 200: 'oklch(88% 0.012 72)', 300: 'oklch(80% 0.016 70)', 400: 'oklch(68% 0.02 68)', 500: 'oklch(55% 0.022 66)', 600: 'oklch(46% 0.02 65)', 700: 'oklch(39% 0.018 64)', 800: 'oklch(32% 0.015 62)', 900: 'oklch(27% 0.013 60)', 950: 'oklch(19% 0.01 58)' },
    mauve: { 50: 'oklch(96.5% 0.008 340)', 100: 'oklch(93% 0.014 339)', 200: 'oklch(87% 0.024 338)', 300: 'oklch(79% 0.036 336)', 400: 'oklch(67% 0.05 334)', 500: 'oklch(56% 0.055 332)', 600: 'oklch(48% 0.052 330)', 700: 'oklch(41% 0.045 328)', 800: 'oklch(35% 0.038 326)', 900: 'oklch(30% 0.032 324)', 950: 'oklch(21% 0.024 322)' },
    mist: { 50: 'oklch(97% 0.006 240)', 100: 'oklch(94% 0.01 239)', 200: 'oklch(88% 0.018 238)', 300: 'oklch(80% 0.028 237)', 400: 'oklch(68% 0.038 236)', 500: 'oklch(56% 0.04 235)', 600: 'oklch(47% 0.038 235)', 700: 'oklch(40% 0.034 236)', 800: 'oklch(33% 0.028 237)', 900: 'oklch(28% 0.024 238)', 950: 'oklch(19% 0.018 240)' },
    olive: { 50: 'oklch(97% 0.012 110)', 100: 'oklch(94% 0.022 110)', 200: 'oklch(88% 0.04 109)', 300: 'oklch(80% 0.06 109)', 400: 'oklch(70% 0.08 108)', 500: 'oklch(58% 0.082 107)', 600: 'oklch(49% 0.075 106)', 700: 'oklch(42% 0.065 106)', 800: 'oklch(35% 0.055 107)', 900: 'oklch(30% 0.048 108)', 950: 'oklch(21% 0.036 110)' },
};

/**
 * Swatch preview for the "sidebar color theme" grid = each palette's 900 shade.
 * The colored sidebar surface is the 950 deep tint; 900 reads as that same deep
 * tone while keeping the hue recognisable in a small swatch.
 */
export const ACCENT_SWATCH: Record<string, string> = Object.fromEntries(
    ACCENT_COLORS.map((color) => [color, TAILWIND_PALETTES[color][900]]),
);

/**
 * Kit default primary scale — Aura's stock primary (emerald), matching
 * `resources/js/theme/preset.ts` which was reset to Aura defaults (no custom
 * primary override). Uses `{emerald.x}` token references so `updatePrimaryPalette`
 * resolves them against the active Aura preset. Kept in sync manually because
 * resetting the accent to "default" needs explicit values to hand back.
 */
const DEFAULT_PRIMARY: Record<number, string> = {
    50: '{emerald.50}', 100: '{emerald.100}', 200: '{emerald.200}', 300: '{emerald.300}', 400: '{emerald.400}',
    500: '{emerald.500}', 600: '{emerald.600}', 700: '{emerald.700}', 800: '{emerald.800}', 900: '{emerald.900}', 950: '{emerald.950}',
};

export function useAccentColor() {
    const STORAGE_KEY = 'admin-accent-color';
    const SIDEBAR_STYLE_KEY = 'admin-sidebar-style';

    /** Currently selected accent color (`'default'` = kit primary + neutral sidebar). */
    const accent = useLocalStorage<AccentColor>(STORAGE_KEY, 'default');

    /**
     * Sidebar surface treatment (LIGHT mode only):
     *   - 'colored' → deep accent tint (or the neutral dark surface for 'default'),
     *   - 'light'   → a white/light surface with dark text.
     * In DARK mode this has no effect — the sidebar always stays the neutral dark
     * surface. Themed in CSS via the `data-sk-sidebar` marker (tokens.css).
     */
    const sidebarStyle = useLocalStorage<SidebarStyle>(SIDEBAR_STYLE_KEY, 'colored');

    function applyAccent(color: AccentColor): void {
        const root = typeof document !== 'undefined' ? document.documentElement : null;

        // Clear any legacy inline overrides (older builds set --admin-sidebar-*
        // inline, which froze the sidebar in dark mode since inline beats `.dark`).
        root?.style.removeProperty('--admin-sidebar-bg');
        root?.style.removeProperty('--admin-sidebar-logo-bg');
        root?.style.removeProperty('--admin-sidebar-border');

        if (color === 'default') {
            updatePrimaryPalette(DEFAULT_PRIMARY);
            root?.removeAttribute('data-sk-accent');
            return;
        }

        updatePrimaryPalette(TAILWIND_PALETTES[color]);
        // Sidebar tint is themed in CSS (tokens.css → [data-sk-accent]:not(.dark)),
        // so it colours the sidebar in LIGHT mode only; dark mode keeps the neutral
        // dark surface. Active item keeps following --p-primary-color in both modes.
        root?.setAttribute('data-sk-accent', color);
    }

    /** Set and persist the accent color. */
    function setAccent(color: AccentColor): void {
        accent.value = color;
    }

    function applySidebarStyle(style: SidebarStyle): void {
        const root = typeof document !== 'undefined' ? document.documentElement : null;
        // 'colored' is the default surface (accent tint / neutral dark), so the
        // marker is only present for the 'light' override; CSS keys off it.
        if (style === 'light') {
            root?.setAttribute('data-sk-sidebar', 'light');
        } else {
            root?.removeAttribute('data-sk-sidebar');
        }
    }

    /** Set and persist the sidebar style. */
    function setSidebarStyle(style: SidebarStyle): void {
        sidebarStyle.value = style;
    }

    // React to changes (e.g. from another tab via storage sync) and on first mount.
    watch(accent, (val) => applyAccent(val), { immediate: false });
    watch(sidebarStyle, (val) => applySidebarStyle(val), { immediate: false });
    onMounted(() => {
        applyAccent(accent.value);
        applySidebarStyle(sidebarStyle.value);
    });

    return {
        accent,
        setAccent,
        applyAccent,
        sidebarStyle,
        setSidebarStyle,
        applySidebarStyle,
    };
}
