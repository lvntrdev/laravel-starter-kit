/// <reference types="vitest" />
import { defineConfig, mergeConfig } from 'vitest/config';
import viteConfig from './vite.config';

// Vitest'in kendi config'i: `vite.config.ts`'teki tüm resolve/alias (@/composables,
// @/plugins, @/theme/preset, @lvntr/components sembolik-link fallback'leri) ve
// plugin pipeline'ı (vue(), AutoImport — testlerin explicit import etmediği
// `computed`/`ref` gibi otomatik importlar için gerekli, vs.) unit testler için
// de aynen geçerlidir; bu yüzden onu tekrar yazmak yerine `mergeConfig` ile
// üstüne yalnız vitest'e özgü `test` alanını bindiriyoruz. `root`/`include`
// burada açıkça tanımlanır çünkü spec dosyalarının tamamı bu paketin kendisinde
// (Lvntr-Starter-Kit bileşen kütüphanesi, `resources/js/...`) yaşar ve stub'lara
// `vendor/lvntr/laravel-starter-kit` sembolik linki üzerinden ulaşılır (composer
// path-repository / sk:install sonrası consumer düzeni) — CI de bu vendor-symlink
// düzenini kullanır, o yüzden ikinci pattern gerekli.
export default mergeConfig(
    viteConfig,
    defineConfig({
        test: {
            environment: 'jsdom',
            globals: true,
            root: __dirname,
            include: [
                'resources/js/**/*.{test,spec}.ts',
                'vendor/lvntr/laravel-starter-kit/resources/js/**/*.{test,spec}.ts',
            ],
            // Inertia paketleri inline işlenir: aksi halde `@inertiajs/vue3`
            // node_modules'ten externalize edilerek yüklenir ve `@inertiajs/core`
            // için kurulan `vi.mock` ona HİÇ ulaşmaz — SkForm testleri gerçek
            // `useForm`'u (gerçek dirty-tracking semantiği) kullanabilsin diye
            // yalnız transport (core router) taklit edilir, bu da inline gerektirir.
            server: {
                deps: { inline: [/@inertiajs\//] },
            },
        },
    }),
);
