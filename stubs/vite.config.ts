/// <reference types="vitest" />
import { defineConfig } from 'vitest/config';
import { loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import inertia from '@inertiajs/vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import i18n from 'laravel-vue-i18n/vite';
import AutoImport from 'unplugin-auto-import/vite';
import Components from 'unplugin-vue-components/vite';
import { PrimeVueResolver } from '@primevue/auto-import-resolver';
import path from 'path';
import { existsSync } from 'fs';
// @ts-expect-error — plain .mjs resolver plugin, no bundled types.
import skTheme, { resolveActivePreset } from './vendor/lvntr/laravel-starter-kit/resources/js/theme/vite-plugin-sk-theme.mjs';

// Wayfinder requires both PHP and an artisan file to generate routes.
// In CI (node-only job) or in the package repo itself, artisan does not
// exist, so we skip wayfinder to keep the build non-fatal.
function isWayfinderAvailable(): boolean {
    return existsSync(path.join(__dirname, 'artisan'));
}

// Vitest spins up a Vite server to transform component files. The
// laravel-vite-plugin refuses to start its HMR server in CI, which fails
// vitest. The Laravel/Inertia plugins are not needed for component-level
// unit tests, so skip them when running under vitest.
const isVitest = process.env.VITEST === 'true' || process.env.NODE_ENV === 'test';

// Kit composables run from the vendor package by default. A subpath import
// `@/composables/<name>` resolves to the consumer-local copy when one exists
// (published via `php artisan sk:publish --tag=composables`, or customized),
// otherwise it falls back to the vendor copy. This is wired as an alias
// `customResolver` rather than a `pre` plugin on purpose: Vite's alias plugin
// runs BEFORE user `pre` plugins, so the bare `@` alias would otherwise rewrite
// the path to the (non-existent) local file first. Bare `@/composables` is not
// matched here and falls through to the `@` alias → local barrel (index.ts),
// which always ships as a stub and re-exports useAdminMenu plus the vendor set.
function resolveComposable(name: string): string | null {
    const dirs = [
        path.resolve(__dirname, 'resources/js/composables'),
        path.resolve(__dirname, 'vendor/lvntr/laravel-starter-kit/resources/js/composables'),
    ];

    for (const dir of dirs) {
        for (const ext of ['.ts', '.js']) {
            const candidate = path.join(dir, name + ext);
            if (existsSync(candidate)) {
                return candidate;
            }
        }
    }

    return null;
}

// Kit Vue plugins (directives such as v-can / v-role) run from the vendor
// package by default, mirroring the composables resolution above. A subpath
// import `@/plugins/<name>` resolves to the consumer-local copy when one exists
// (customized, or published via `php artisan sk:publish`), otherwise it falls
// back to the vendor copy. Wired as an alias `customResolver` for the same
// ordering reason as `@/composables` above — it MUST precede the bare `@` alias.
function resolvePlugin(name: string): string | null {
    const dirs = [
        path.resolve(__dirname, 'resources/js/plugins'),
        path.resolve(__dirname, 'vendor/lvntr/laravel-starter-kit/resources/js/plugins'),
    ];

    for (const dir of dirs) {
        for (const ext of ['.ts', '.js']) {
            const candidate = path.join(dir, name + ext);
            if (existsSync(candidate)) {
                return candidate;
            }
        }
    }

    return null;
}

// NOTE (stubs perspective): When this file is published to a consumer project
// via `php artisan sk:install`, __dirname resolves to the consumer project root.
// All alias paths below therefore point to the vendor directory of the consumer.
// preserveSymlinks is enabled so that Composer path-repository symlinks (used
// during local package development) are followed correctly without breaking HMR.
// For Levent's own sibling-dev workflow the alias is the same — vendor/lvntr/...
// is a symlink to ../starter-kit-package thanks to the Composer path repository.

// Propagate `VITE_SK_THEME` from `.env` into `process.env` so the PrimeVue
// preset resolver (`resolveActivePreset`, wired as an alias customResolver
// below) honors `.env`-based theme activation too — not just `import.meta.env`.
// Vite loads `.env` into `import.meta.env`/`config.env` but NOT `process.env`;
// the sk-theme CSS plugin reads `config.env` directly, this covers the preset
// side and any direct `vite build` where the var lives only in `.env`.
const skThemeMode = process.env.NODE_ENV === 'development' ? 'development' : 'production';
const skThemeEnv = loadEnv(skThemeMode, process.cwd(), 'VITE_SK_THEME');
if (skThemeEnv.VITE_SK_THEME && !process.env.VITE_SK_THEME) {
    process.env.VITE_SK_THEME = skThemeEnv.VITE_SK_THEME;
}

export default defineConfig({
    resolve: {
        preserveSymlinks: true,
        // Array form (ordered): the @/composables fallback MUST precede `@`,
        // otherwise the broad `@` alias rewrites the path before the fallback
        // gets a chance. @lvntr/components precedes @lvntr for the same reason.
        alias: [
            {
                find: /^@\/composables\/(.+)$/,
                replacement: '$1',
                customResolver: (name: string) => resolveComposable(name),
            },
            {
                find: /^@\/plugins\/(.+)$/,
                replacement: '$1',
                customResolver: (name: string) => resolvePlugin(name),
            },
            // `@/theme/preset` → the active theme's PrimeVue preset, chosen at
            // build time by VITE_SK_THEME (override at <active>/preset.ts,
            // else the base theme/preset.ts). Wired as an alias customResolver —
            // NOT a plugin `resolveId` — for the same reason as @/composables
            // above: Vite's alias plugin expands `@/theme/preset` to the base file
            // BEFORE user `pre` plugins run, so a resolveId hook never sees the
            // bare specifier. customResolver runs DURING alias resolution and
            // wins. MUST precede the bare `@`.
            {
                find: /^@\/theme\/preset(\.ts)?$/,
                replacement: '$&',
                customResolver: () => resolveActivePreset(),
            },
            { find: '@', replacement: path.resolve(__dirname, 'resources/js') },
            // @lvntr/components/* → Lvntr-Starter-Kit UI lib from vendor.
            // Consumer's __dirname is the project root; vendor symlink is followed
            // via preserveSymlinks so HMR works for both install and sibling-dev.
            {
                find: '@lvntr/components',
                replacement: path.resolve(
                    __dirname,
                    'vendor/lvntr/laravel-starter-kit/resources/js/components/Lvntr-Starter-Kit',
                ),
            },
            // Remaining @lvntr/* imports (e.g. shared utilities) resolve to the
            // same vendor package root.
            { find: '@lvntr', replacement: path.resolve(__dirname, 'vendor/lvntr/laravel-starter-kit/resources/js') },
        ],
    },

    plugins: [
        // Generate resources/css/theme/_active.css before CSS resolution. This
        // is the npm-independent safety net (works even on a direct `vite build`,
        // any ignore-scripts config); the explicit chain in package.json's
        // dev/build scripts is kept as belt-and-suspenders.
        skTheme(),
        ...(isWayfinderAvailable() ? [wayfinder()] : []),
        ...(isVitest
            ? []
            : [
                laravel({
                    input: ['resources/css/app.css', 'resources/js/app.ts'],
                    refresh: true,
                }),
                inertia(),
            ]),

        vue(),
        tailwindcss(),
        i18n(),

        AutoImport({
            imports: ['vue', '@vueuse/core'],
            dts: 'auto-imports.d.ts',
            vueTemplate: true,
        }),

        Components({
            dirs: [
                'resources/js/components',
                // Lvntr-Starter-Kit components from vendor are auto-imported so
                // templates can use <SkForm/>, <SkDatatable/>, <AppDialog/> etc.
                // without explicit import statements.
                'vendor/lvntr/laravel-starter-kit/resources/js/components/Lvntr-Starter-Kit',
            ],
            dts: 'components.d.ts',
            resolvers: [PrimeVueResolver()],
        }),
    ],

    build: {
        rollupOptions: {
            // Silence the "Sourcemap is likely to be incorrect" warnings coming
            // from @tailwindcss/vite and @inertiajs/vite — both plugins are known
            // to skip sourcemap regeneration after their transform; build output
            // and production runtime are unaffected. Other warnings still pass through.
            onwarn(warning, warn) {
                const msg = warning.message ?? '';
                if (
                    msg.includes('Sourcemap is likely to be incorrect') &&
                    (msg.includes('tailwindcss') || msg.includes('inertiajs'))
                ) {
                    return;
                }
                // @vueuse/core dist dosyasındaki #__PURE__ yorumlarının konumu
                // Rollup tarafından yorumlanamıyor — upstream sorun, build'ı etkilemiyor.
                if (warning.code === 'INVALID_ANNOTATION' && msg.includes('@vueuse/core')) {
                    return;
                }
                // <script setup> SSR dönüşümü sırasında Vue plugin'in eklediği
                // h/resolveDirective import'ları Rollup'a "kullanılmıyor" görünür.
                if (warning.code === 'UNUSED_EXTERNAL_IMPORT' && msg.includes('"vue"')) {
                    return;
                }
                warn(warning);
            },
            output: {
                manualChunks(id) {
                    if (!id.includes('node_modules')) {
                        return;
                    }
                    if (/[\\/]node_modules[\\/](primevue|@primevue|primeicons)[\\/]/.test(id)) {
                        return 'vendor-primevue';
                    }
                    if (/[\\/]node_modules[\\/](vue|@vue)[\\/]/.test(id)) {
                        return 'vendor-vue';
                    }
                    return 'vendor';
                },
            },
        },
        // vendor chunk 900 kB+ olabiliyor; uyarı limiti buna göre ayarlandı.
        chunkSizeWarningLimit: 1000,
    },

    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
