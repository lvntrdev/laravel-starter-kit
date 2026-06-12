#!/usr/bin/env node
/**
 * sk-theme-build — theme resolver
 *
 * Generates `resources/css/theme/_active.css`: a single ordered list of
 * `@import` statements that wire up the ACTIVE theme.
 *
 * This script ships INSIDE the kit package (vendor-resident at
 * `resources/js/theme/`), so it must NOT resolve theme files relative to its own
 * location (`__dirname` = the vendor dir). It resolves them relative to the
 * CONSUMER project root, which is `process.cwd()` — Vite and node both run from
 * the project root. The optional `root` option lets a caller pin the base
 * explicitly (vite.config does this); when omitted, cwd is used.
 *
 * Two entry points keep this robust against npm config:
 *   - Explicit chain in package.json `dev`/`build`
 *     (`node vendor/lvntr/laravel-starter-kit/resources/js/theme/sk-theme-build.mjs && vite …`)
 *     — survives `ignore-scripts=true` because it is a literal command, not an npm hook.
 *   - The Vite plugin in `vite-plugin-sk-theme.mjs` (imports `buildActiveTheme`)
 *     — runs even if `vite build` is invoked directly, with no npm involvement at all.
 * Run directly (e.g. the `theme:build` script) it resolves the theme and logs a
 * one-line summary.
 *
 * Model: full-replacement + fallback (NOT layered diff).
 *   - The active theme is chosen at build time. Precedence: explicit arg →
 *     `.sk-active-theme` marker file (the DB-backed admin choice, written by the
 *     PHP side) → `VITE_SK_THEME` env → `main` default. See `resolveThemeName`.
 *   - For every slot found under `main/`, the resolver emits
 *     `<active>/<slot>` IF that file exists, otherwise `main/<slot>`.
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

import { existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { dirname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Marker file (relative to the consumer project root) written by the PHP side
 * (`ThemeResolver::writeMarker`) when an admin saves the appearance theme. The
 * node build resolver reads it so the DB-selected theme becomes the build source
 * of truth WITHOUT node ever touching the DB — the file is the PHP→node contract.
 * Must stay byte-for-byte in sync with `ThemeResolver::MARKER_RELATIVE_PATH`.
 */
export const ACTIVE_THEME_MARKER = 'resources/css/theme/.sk-active-theme';

/**
 * Read the active-theme marker file under `projectRoot`, returning the trimmed
 * value or `null` when absent/empty/unreadable. This is the SECOND precedence
 * source in `resolveThemeName` (after an explicit arg, before `VITE_SK_THEME`).
 *
 * Reading is best-effort and NEVER throws: a missing or unreadable marker simply
 * yields `null` so resolution falls through to `VITE_SK_THEME`/`main`. Slug
 * VALIDATION is deliberately left to `resolveThemeName` so a tampered/garbage
 * marker produces the same loud, explicit build error as a mistyped env var
 * (rather than a silent fallback that hides the misconfiguration).
 *
 * @param {string} projectRoot CONSUMER project root (cwd / options.root), NOT
 *   the vendor-resident script's own directory.
 * @returns {string | null} trimmed marker value, or null when there is none.
 */
export function readThemeMarker(projectRoot) {
    const markerPath = join(projectRoot, ACTIVE_THEME_MARKER);
    if (!existsSync(markerPath)) {
        return null;
    }
    try {
        const value = readFileSync(markerPath, 'utf8').trim();
        return value === '' ? null : value;
    } catch {
        return null;
    }
}

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
 * Resolve and validate the active theme name. Precedence (highest first):
 *
 *   1. explicit `theme` arg (only the build/Vite plugin passes one),
 *   2. the `.sk-active-theme` marker file under the project root — the DB-backed
 *      admin choice written by the PHP side; this makes the DB the build source
 *      of truth without node ever touching the DB,
 *   3. `process.env.VITE_SK_THEME` — the legacy `.env` flow, still honored when
 *      no marker is present (consumers who never adopted the admin appearance
 *      tab keep working unchanged),
 *   4. the `main` default.
 *
 * The result is used verbatim as a path segment under `theme/` (both for the
 * CSS slots here and the PrimeVue preset in vite-plugin-sk-theme.mjs), so it
 * MUST NOT be able to escape that directory. We accept only a conservative slug
 * — letters, digits, `-`, `_` — which structurally cannot contain `/`, `\`,
 * `.`, a null byte, or whitespace, so `../`-style traversal is impossible by
 * construction (no separate "is it still under root?" assert is needed — the
 * allowlist already makes an escaping segment unrepresentable). Identical to the
 * PHP-side rule in `ThemeResolver::isValidName`.
 *
 * An invalid name THROWS rather than silently falling back to `main`: a mistyped
 * `VITE_SK_THEME` OR a tampered marker should be a loud build error, not a
 * confusing zero-override "stock" build that looks like the theme simply did
 * nothing. (The marker is slug-validated by PHP on write, so a bad value here
 * means out-of-band tampering — exactly the case we want to surface loudly.)
 *
 * @param {string} [theme] Explicit theme name. When omitted, falls through to
 *   the marker file, then `process.env.VITE_SK_THEME`, then `main`.
 * @param {string} [root] CONSUMER project root used to locate the marker file.
 *   Defaults to `process.cwd()` — the directory Vite/node runs from. NOT relative
 *   to this vendor-resident script's own location.
 * @returns {string} the validated theme name.
 */
export function resolveThemeName(theme, root) {
    const explicit = theme != null ? String(theme).trim() : '';
    const marker = explicit === '' ? readThemeMarker(root ?? process.cwd()) : null;
    const env = explicit === '' && (marker === null || marker === '') ? process.env.VITE_SK_THEME : null;

    const name = (explicit || marker || env || 'main').trim() || 'main';
    if (!/^[A-Za-z0-9_-]+$/.test(name)) {
        throw new Error(
            `[sk-theme-build] invalid theme name "${name}": only letters, digits, "-" and "_" are allowed ` +
                '(no path separators, dots or spaces). Source: explicit arg, ' +
                `${ACTIVE_THEME_MARKER} marker, or VITE_SK_THEME.`,
        );
    }
    return name;
}

/**
 * Resolve the active theme and write `resources/css/theme/_active.css`.
 *
 * @param {object} [options]
 * @param {string} [options.root]  CONSUMER project root. Defaults to
 *   `process.cwd()` — the directory Vite/node runs from in the consumer app.
 *   This is deliberately NOT relative to the script's own location: the script
 *   is vendor-resident, so `__dirname` would point at the kit package, not the
 *   consumer's `resources/css/theme/`.
 * @param {string} [options.theme] Active theme name. Defaults to
 *   `process.env.VITE_SK_THEME || 'main'`.
 * @returns {{ outPath: string, slotCount: number, overriddenCount: number }}
 */
export function buildActiveTheme({ root, theme } = {}) {
    // Consumer project root (where resources/css/theme/ lives). cwd, NOT the
    // vendor-resident script's own directory.
    const projectRoot = root ?? process.cwd();
    const themeDir = join(projectRoot, 'resources', 'css', 'theme');
    const mainDir = join(themeDir, 'main');

    // Resolve against THIS projectRoot so the marker file is read from the same
    // base as the theme dir (matters when a caller pins `options.root` instead of
    // relying on cwd — e.g. vite.config).
    const activeTheme = resolveThemeName(theme, projectRoot);
    const activeDir = join(themeDir, activeTheme);

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
    lines.push(' * Produced by the kit theme resolver (sk-theme-build.mjs, dev/build chain + Vite plugin).');
    lines.push(` * Active theme: ${activeTheme} (.sk-active-theme marker / VITE_SK_THEME). Gitignored, not hash-tracked.`);
    lines.push(' * Each slot resolves to <active>/<slot> when present, else main/<slot>.');
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
// `node …/sk-theme-build.mjs` (the `theme:build` script and the explicit `&&`
// chain in dev/build). When imported (Vite plugin), nothing runs on import.
if (process.argv[1] && process.argv[1] === fileURLToPath(import.meta.url)) {
    try {
        const { outPath, slotCount, overriddenCount } = buildActiveTheme();
        // Log the path relative to the consumer project root (cwd), the same
        // base buildActiveTheme() resolved against above.
        const projectRoot = process.cwd();
        console.log(
            `[sk-theme-build] → ${outPath.replace(projectRoot + '/', '')} ` +
                `(${slotCount} slot${slotCount === 1 ? '' : 's'}, ${overriddenCount} override${overriddenCount === 1 ? '' : 's'})`,
        );
    } catch (err) {
        console.error(err instanceof Error ? err.message : err);
        process.exit(1);
    }
}
