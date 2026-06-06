#!/usr/bin/env node
/**
 * sk-theme-build — theme resolver
 *
 * Generates `resources/css/theme/_active.css`: a single ordered list of
 * `@import` statements that wire up the ACTIVE theme.
 *
 * Two entry points keep this robust against npm config:
 *   - Explicit chain in package.json `dev`/`build` (`node scripts/sk-theme-build.mjs && vite …`)
 *     — survives `ignore-scripts=true` because it is a literal command, not an npm hook.
 *   - The Vite plugin in `scripts/vite-plugin-sk-theme.mjs` (imports `buildActiveTheme`)
 *     — runs even if `vite build` is invoked directly, with no npm involvement at all.
 * Run directly (`node scripts/sk-theme-build.mjs`, e.g. the `theme:build` script) it
 * resolves the theme and logs a one-line summary.
 *
 * Model: full-replacement + fallback (NOT layered diff).
 *   - The active theme is chosen at build time via `VITE_SK_THEME` (default `main`).
 *   - For every slot found under `themes/main/`, the resolver emits
 *     `themes/<active>/<slot>` IF that file exists, otherwise `themes/main/<slot>`.
 *   - So a `custom` theme that ships only `components/datatable.css` overrides
 *     just the datatable; every other slot falls back to `main`. EVERY cascade
 *     layer is a slot now — fonts, base, auth and utilities included.
 *
 * `main` is the source of truth for the slot LIST and the import ORDER. A custom
 * theme cannot add slots that `main` does not have (those would never be loaded);
 * it can only replace existing ones. This keeps a single bundle, no runtime switch.
 *
 * Order (must match the historical theme.css cascade for byte-identical `main`):
 *   tokens → fonts → _base → layout/* → components/* → _auth → utilities
 * (utilities is unlayered and emitted LAST so it keeps winning the cascade.)
 *
 * The generated `_active.css` is a build artifact: gitignored, not hash-tracked
 * by `sk:update`. Open it to see exactly which files the active theme resolved to.
 */

import { existsSync, mkdirSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

/**
 * Recursively collect stylesheet files (`*.css` and `*.scss`) under `dir`,
 * returned as paths relative to `dir`, sorted for deterministic ordering.
 * Returns [] if `dir` is absent. `.scss` is included for symmetry — a custom
 * theme may ship a `.scss` partial in a layout/component slot, and the resolver
 * is extension-agnostic.
 */
function collectSlots(dir) {
    if (!existsSync(dir)) {
        return [];
    }
    /** @type {string[]} */
    const out = [];
    const walk = (current) => {
        const entries = readdirSync(current, { withFileTypes: true }).sort((a, b) =>
            a.name.localeCompare(b.name),
        );
        for (const entry of entries) {
            const full = join(current, entry.name);
            if (entry.isDirectory()) {
                walk(full);
            } else if (entry.isFile() && (entry.name.endsWith('.css') || entry.name.endsWith('.scss'))) {
                out.push(relative(dir, full).split('\\').join('/'));
            }
        }
    };
    walk(dir);
    return out;
}

/**
 * Resolve and validate the active theme name from an explicit value, then
 * `VITE_SK_THEME`, then the `main` default.
 *
 * The result is used verbatim as a path segment under `themes/` (both for the
 * CSS slots here and the PrimeVue preset in vite-plugin-sk-theme.mjs), so it
 * MUST NOT be able to escape that directory. We accept only a conservative slug
 * — letters, digits, `-`, `_` — which structurally cannot contain `/`, `\`,
 * `.`, a null byte, or whitespace, so `../`-style traversal is impossible by
 * construction (no separate "is it still under root?" assert is needed — the
 * allowlist already makes an escaping segment unrepresentable).
 *
 * An invalid name THROWS rather than silently falling back to `main`: a
 * mistyped `VITE_SK_THEME` should be a loud build error, not a confusing
 * zero-override "stock" build that looks like the theme simply did nothing.
 *
 * @param {string} [theme] Explicit theme name. Defaults to
 *   `process.env.VITE_SK_THEME`, then `main`.
 * @returns {string} the validated theme name.
 */
export function resolveThemeName(theme) {
    const name = (theme ?? process.env.VITE_SK_THEME ?? 'main').trim() || 'main';
    if (!/^[A-Za-z0-9_-]+$/.test(name)) {
        throw new Error(
            `[sk-theme-build] invalid VITE_SK_THEME "${name}": only letters, digits, "-" and "_" are allowed ` +
                '(no path separators, dots or spaces).',
        );
    }
    return name;
}

/**
 * Resolve the active theme and write `resources/css/theme/_active.css`.
 *
 * @param {object} [options]
 * @param {string} [options.root]  Project root (parent of `scripts/`). Defaults
 *   to the directory above this script — the same path the CLI has always used.
 * @param {string} [options.theme] Active theme name. Defaults to
 *   `process.env.VITE_SK_THEME || 'main'`.
 * @returns {{ outPath: string, slotCount: number, overriddenCount: number }}
 */
export function buildActiveTheme({ root, theme } = {}) {
    // Repo/project root is the parent of scripts/.
    const projectRoot = root ?? join(__dirname, '..');
    const themeDir = join(projectRoot, 'resources', 'css', 'theme');
    const themesDir = join(themeDir, 'themes');
    const mainDir = join(themesDir, 'main');

    const activeTheme = resolveThemeName(theme);
    const activeDir = join(themesDir, activeTheme);

    if (!existsSync(mainDir)) {
        throw new Error(`[sk-theme-build] missing base theme directory: ${mainDir}`);
    }

    /**
     * Resolve a single slot to its active-theme file with fallback to main.
     * `slot` is a path relative to a theme dir (e.g. `tokens.css`,
     * `layout/shell.css`, `components/datatable.css`). Returns an import path
     * relative to the theme dir (where `_active.css` lives), POSIX-style.
     */
    const resolveSlot = (slot) => {
        const override = join(activeDir, slot);
        const useOverride = activeTheme !== 'main' && existsSync(override);
        const chosenAbs = useOverride ? override : join(mainDir, slot);
        const rel = relative(themeDir, chosenAbs).split('\\').join('/');
        return { importPath: rel, overridden: useOverride };
    };

    // Discover slots from the directory tree (main is the canonical slot list).
    //
    // Every cascade layer is now an overridable slot resolved via resolveSlot()
    // with fallback to main. The order below MUST match the historical cascade:
    //   tokens → fonts → _base → layout/* → components/* → _auth → utilities
    //
    // HEAD = fixed-order leading slots (tokens, fonts, base/reset).
    // MID  = globbed layout + component partials, in sorted order.
    // TAIL = fixed-order trailing slots (auth, then unlayered utilities last —
    //        utilities wins the cascade and must stay at the very end).
    const headSlots = ['tokens.css', 'fonts.css', '_base.scss'].filter((s) => existsSync(join(mainDir, s)));
    const layoutSlots = collectSlots(join(mainDir, 'layout')).map((p) => join('layout', p).split('\\').join('/'));
    const componentSlots = collectSlots(join(mainDir, 'components')).map((p) =>
        join('components', p).split('\\').join('/'),
    );
    const tailSlots = ['_auth.scss', 'utilities.css'].filter((s) => existsSync(join(mainDir, s)));

    // Ordered slot list (must match the historical theme.css cascade).
    const orderedSlots = [...headSlots, ...layoutSlots, ...componentSlots, ...tailSlots];

    const lines = [];
    lines.push('/**');
    lines.push(' * GENERATED FILE — do not edit by hand.');
    lines.push(' * Produced by scripts/sk-theme-build.mjs (dev/build chain + Vite plugin).');
    lines.push(` * Active theme: ${activeTheme} (VITE_SK_THEME). Gitignored, not hash-tracked.`);
    lines.push(' * Each slot resolves to themes/<active>/<slot> when present, else themes/main/<slot>.');
    lines.push(' */');
    lines.push('');

    let overriddenCount = 0;

    // Emit every slot through resolveSlot() in cascade order:
    //   HEAD (tokens, fonts, _base) → layout → components → TAIL (_auth, utilities).
    for (const slot of orderedSlots) {
        const { importPath, overridden } = resolveSlot(slot);
        if (overridden) overriddenCount++;
        lines.push(`@import './${importPath}';${overridden ? ' /* override */' : ''}`);
    }

    lines.push('');

    const outPath = join(themeDir, '_active.css');
    mkdirSync(dirname(outPath), { recursive: true });
    writeFileSync(outPath, lines.join('\n'), 'utf8');

    return { outPath, slotCount: orderedSlots.length, overriddenCount };
}

// Run-as-main guard: only execute (and log) when invoked directly via
// `node scripts/sk-theme-build.mjs` (the `theme:build` script and the explicit
// `&&` chain in dev/build). When imported (Vite plugin), nothing runs on import.
if (process.argv[1] && process.argv[1] === fileURLToPath(import.meta.url)) {
    try {
        const { outPath, slotCount, overriddenCount } = buildActiveTheme();
        const projectRoot = join(__dirname, '..');
        console.log(
            `[sk-theme-build] → ${outPath.replace(projectRoot + '/', '')} ` +
                `(${slotCount} slot${slotCount === 1 ? '' : 's'}, ${overriddenCount} override${overriddenCount === 1 ? '' : 's'})`,
        );
    } catch (err) {
        console.error(err instanceof Error ? err.message : err);
        process.exit(1);
    }
}
