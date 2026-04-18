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

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
            '@lvntr': path.resolve(__dirname, 'vendor/lvntr/laravel-starter-kit/resources/js'),
        },
    },

    plugins: [
        wayfinder(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        inertia(),

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
                'vendor/lvntr/laravel-starter-kit/resources/js/components',
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
