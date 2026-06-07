#!/usr/bin/env node
/**
 * sk-lang-build — vendor kit-translation compiler
 *
 * Compiles the kit's namespace-less `sk-*.php` translation files into
 * pre-built `php_{locale}.json` bundles that ship INSIDE the package
 * (vendor-resident at `resources/js/lang/`) and are consumed by the
 * frontend i18n setup.
 *
 * Why pre-compiled JSON (not on-the-fly PHP→JSON in the consumer build):
 *   - The consumer does NOT install this npm package; it consumes the JS
 *     library through the Composer `vendor/lvntr/laravel-starter-kit/`
 *     symlink (the `@lvntr` Vite alias). So the kit's translations must
 *     already be JSON on disk in the vendor dir — there is no kit-side
 *     PHP→JSON step in the consumer's `vite build`.
 *   - The `laravel-vue-i18n` Vite plugin (`i18n()`) DOES compile PHP→JSON,
 *     but only for the consumer's own `lang/` (the writable override layer)
 *     and it merges last-path-wins — putting the vendor in its
 *     `additionalLangPaths` would let the vendor OVERRIDE the consumer's
 *     `app/lang` edits, the opposite of the required app-wins precedence.
 *     So the kit bundle is kept as a SEPARATE static source that `app.ts`
 *     merges with app-last-spread (app wins, vendor is the fallback).
 *
 * This is the i18n sibling of `resources/js/theme/sk-theme-build.mjs`: a
 * vendor-resident build helper the KIT MAINTAINER runs to regenerate the
 * shipped artifact whenever a `sk-*.php` file changes. The output JSON is
 * COMMITTED and shipped (composer `resources/` + npm `files`), so the
 * consumer never re-runs this.
 *
 * Source : resources/lang/{en,tr}/sk-*.php       (vendor PHP, composer-shipped)
 * Output : resources/js/lang/php_{locale}.json    (vendor JSON, committed)
 *
 * Key shape is byte-identical to what `laravel-vue-i18n`'s own loader would
 * emit for these files — it reuses that loader's `parse()` and dots-syntax —
 * so `$t('sk-common.id')` resolves exactly as before the relocation. Only
 * `sk-*.php` files are read; the vestigial `starter-kit::`-namespaced files
 * that also live under `resources/lang/en/` are intentionally skipped.
 */

import { existsSync, mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const __dirname = dirname(fileURLToPath(import.meta.url));
// Package repo root: resources/js/lang → ../../..
const repoRoot = join(__dirname, '..', '..', '..');

// Reuse laravel-vue-i18n's PHP-array parser (php-parser based) so the JSON
// key shape is identical to the plugin's own output. It is resolved from the
// stubs toolchain where the dependency is installed.
const require = createRequire(import.meta.url);
function loadParser() {
    const candidates = [
        join(repoRoot, 'stubs', 'node_modules', 'laravel-vue-i18n', 'dist', 'loader.mjs'),
        join(repoRoot, 'node_modules', 'laravel-vue-i18n', 'dist', 'loader.mjs'),
    ];
    for (const c of candidates) {
        if (existsSync(c)) {
            return c;
        }
    }
    throw new Error(
        '[sk-lang-build] laravel-vue-i18n loader not found. Run `npm ci` in stubs/ first.\n' +
            `Looked in:\n  ${candidates.join('\n  ')}`,
    );
}

const LOCALES = ['en', 'tr'];

async function build() {
    const { parse } = await import(loadParser());

    const outDir = join(repoRoot, 'resources', 'js', 'lang');
    mkdirSync(outDir, { recursive: true });

    const summary = [];

    for (const locale of LOCALES) {
        const langDir = join(repoRoot, 'resources', 'lang', locale);
        if (!existsSync(langDir)) {
            throw new Error(`[sk-lang-build] missing lang dir: ${langDir}`);
        }

        // Only the kit's namespace-less sk-*.php files. The vestigial
        // starter-kit::-namespaced files (admin.php, auth.php, …) are skipped.
        const files = readdirSync(langDir)
            .filter((f) => f.startsWith('sk-') && f.endsWith('.php'))
            .sort();

        const translations = {};
        for (const file of files) {
            const group = file.replace(/\.php$/, ''); // e.g. sk-common
            const parsed = parse(readFileSync(join(langDir, file)).toString());
            // parse() returns dots-syntax keys relative to the file (e.g.
            // `id`, `placeholder.select_role`). Prefix with the group name to
            // match `$t('sk-common.id')`.
            for (const [key, value] of Object.entries(parsed)) {
                translations[`${group}.${key}`] = value;
            }
        }

        const outFile = join(outDir, `php_${locale}.json`);
        // Stable key order for deterministic, diff-friendly output.
        const ordered = Object.fromEntries(Object.keys(translations).sort().map((k) => [k, translations[k]]));
        writeFileSync(outFile, JSON.stringify(ordered, null, 0) + '\n', 'utf8');
        summary.push(`${locale}: ${files.length} files → ${Object.keys(translations).length} keys`);
    }

    // eslint-disable-next-line no-console
    console.log(`[sk-lang-build] → resources/js/lang/php_{en,tr}.json (${summary.join('; ')})`);
}

build().catch((err) => {
    // eslint-disable-next-line no-console
    console.error(err instanceof Error ? err.message : String(err));
    process.exit(1);
});
