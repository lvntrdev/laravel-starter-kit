/// <reference types="vitest" />
import { defineConfig } from 'vitest/config';
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

// NOTE (stubs perspective): When this file is published to a consumer project
// via `php artisan sk:install`, __dirname resolves to the consumer project root.
// All alias paths below therefore point to the vendor directory of the consumer.
// preserveSymlinks is enabled so that Composer path-repository symlinks (used
// during local package development) are followed correctly without breaking HMR.
// For Levent's own sibling-dev workflow the alias is the same — vendor/lvntr/...
// is a symlink to ../starter-kit-package thanks to the Composer path repository.

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

    test: {
        environment: 'jsdom',
        globals: true,
    },
});
