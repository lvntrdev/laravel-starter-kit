/**
 * vite-plugin-sk-theme — the npm-independent half of the theme resolver.
 *
 * Two responsibilities, both keyed off the same `VITE_SK_THEME` active theme:
 *
 *  1. CSS: generate `resources/css/theme/_active.css` from inside Vite's
 *     lifecycle. The explicit `node …/sk-theme-build.mjs && vite …` chain
 *     in package.json already covers `npm run dev/build`; this plugin
 *     additionally guarantees `_active.css` exists even when `vite`/`vite build`
 *     is invoked DIRECTLY (no npm, any `ignore-scripts` setting) — e.g.
 *     `npx vite build`, a custom CI step, or an editor task that calls Vite
 *     straight. `_active.css` is a REAL on-disk import (`theme.css` → `@import
 *     './_active.css'`), not a virtual module, so it must be written BEFORE Vite
 *     resolves the CSS module graph. We do that in `configResolved` (earliest
 *     reliable hook — runs once config is known, before any module is resolved)
 *     and again in `buildStart` (belt-and-suspenders for incremental/SSR
 *     passes). Both call the idempotent `buildActiveTheme()` writer.
 *
 *  2. PrimeVue preset: the exported `resolveActivePreset()` helper maps the
 *     `@/theme/preset` import to a theme-specific preset at BUILD TIME. It is
 *     wired in vite.config.ts as an alias `customResolver` — NOT a plugin
 *     `resolveId` hook. Vite's alias plugin expands `@/theme/preset` to the base
 *     file BEFORE user `pre` plugins run, so only an alias-level resolver
 *     intercepts it (the same reason `@/composables/*` is wired that way). The
 *     symmetric counterpart to the CSS override for the styled-mode PrimeVue
 *     palette, with NO generated artifact (pure JS module resolution).
 */

import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { buildActiveTheme, readThemeMarker, resolveThemeName } from './sk-theme-build.mjs';

/**
 * Resolve the active PrimeVue preset module for the given theme.
 *
 * Mirror of `buildActiveTheme`'s root logic so the resolution is consistent:
 * the CONSUMER project root is `options.root ?? process.cwd()` (NOT relative to
 * this vendor-resident plugin's own location), and the JS theme dir is
 * `<root>/resources/js/theme` — the same directory the `@` alias would have
 * pointed `@/theme/preset` at. Returns an ABSOLUTE path:
 *
 *   - active theme is not `main` AND `<active>/preset.ts` exists
 *       → that override file,
 *   - otherwise → the base `theme/preset.ts` (in place, consumer-customizable).
 *
 * @param {object} [options]
 * @param {string} [options.root]  CONSUMER project root. Defaults to
 *   `process.cwd()`.
 * @param {string} [options.theme] Active theme name.
 * @returns {string} absolute path to the resolved preset module.
 */
export function resolveActivePreset({ root, theme } = {}) {
    const projectRoot = root ?? process.cwd();
    const themeDir = join(projectRoot, 'resources', 'js', 'theme');
    const basePreset = join(themeDir, 'preset.ts');

    // Pass projectRoot so the preset alias follows the SAME marker file the CSS
    // resolver does — otherwise a marker-selected theme would swap CSS slots but
    // keep the `main` PrimeVue preset, splitting the styled-mode palette from the
    // stylesheet. Precedence is identical: explicit → marker → VITE_SK_THEME → main.
    const activeTheme = resolveThemeName(theme, projectRoot);
    if (activeTheme !== 'main') {
        const override = join(themeDir, activeTheme, 'preset.ts');
        if (existsSync(override)) {
            return override;
        }
    }

    return basePreset;
}

/**
 * @param {object} [options]
 * @param {string} [options.root]  CONSUMER project root. Defaults to the
 *   resolver's own default (`process.cwd()`).
 * @param {string} [options.theme] Active theme name. Defaults to
 *   `process.env.VITE_SK_THEME || 'main'`.
 * @returns {import('vite').Plugin}
 */
export default function skTheme(options = {}) {
    // The active theme, resolved once `configResolved` fires. An explicit
    // `skTheme({ theme })` option always wins. Below that the precedence is the
    // resolver's own: `.sk-active-theme` marker (the DB-backed admin choice) →
    // `VITE_SK_THEME` → `main`. We only LIFT `VITE_SK_THEME` out of Vite's loaded
    // env (`config.env`) into `resolvedTheme` when there is NO marker — because
    // Vite loads `.env` into `import.meta.env`/`config.env` but NOT into
    // `process.env`, so a `.env`-only value would otherwise be invisible to
    // `resolveThemeName` (which reads `process.env.VITE_SK_THEME`). Lifting it
    // unconditionally would make the env beat the marker and invert precedence,
    // so the marker check gates the lift. When a marker exists we leave
    // `resolvedTheme` null and let `resolveThemeName` read the marker directly.
    let resolvedTheme = options.theme;

    const generate = () => {
        try {
            buildActiveTheme({ ...options, theme: resolvedTheme });
        } catch (err) {
            // Surface the missing-base-theme error loudly; Vite would otherwise
            // fail later with an opaque "Can't resolve './_active.css'".
            console.error(err instanceof Error ? err.message : String(err));
            throw err;
        }
    };

    return {
        name: 'sk-theme',
        // Run before other plugins so `_active.css` exists before Vite resolves
        // the CSS module graph (the explicit package.json dev/build chain is the
        // other, npm-side half). Preset resolution does NOT live here — it is an
        // alias customResolver in vite.config.ts (see resolveActivePreset's note).
        enforce: 'pre',
        // Earliest reliable point: config is known, no module resolved yet.
        // `config.env` holds the VITE_-prefixed vars Vite loaded from `.env`.
        configResolved(config) {
            // Lift the Vite-loaded VITE_SK_THEME only when neither an explicit
            // option nor a marker file decides the theme — so the marker keeps
            // precedence over a `.env` value (see the constructor note above).
            if (resolvedTheme == null && config?.env?.VITE_SK_THEME) {
                const projectRoot = options.root ?? config?.root ?? process.cwd();
                if (readThemeMarker(projectRoot) === null) {
                    resolvedTheme = config.env.VITE_SK_THEME;
                }
            }
            generate();
        },
        // Re-assert on every build start (incremental rebuilds, the SSR pass).
        buildStart() {
            generate();
        },
    };
}
