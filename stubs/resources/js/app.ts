import '../css/app.css';
import 'primeicons/primeicons.css';
import { createInertiaApp, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { i18nVue } from 'laravel-vue-i18n';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import Tooltip from 'primevue/tooltip';
import AppPreset from '@/theme/preset';
import { PermissionPlugin } from '@/plugins/permission';

// Axios defaults — send session + XSRF cookies on every request so Fortify
// endpoints that rely on the web session (2FA, sessions, password-confirm)
// stay CSRF-protected. XSRF cookie/header names match Laravel's defaults.
axios.defaults.withCredentials = true;
axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

createInertiaApp({
    pages: {
        path: './pages',
        lazy: false,
    },
    progress: {
        delay: 250,
        color: '#29d',
        includeCSS: true,
        showSpinner: false,
    },
    title: (title) => {
        const appName = (usePage().props.appName as string) || 'Laravel';
        return title ? `${title} - ${appName}` : appName;
    },
    withApp(app, { ssr }) {
        // i18n iki katmandan birlesir; app her zaman kazanir, eksik anahtar vendor'dan duser:
        //   1. VENDOR (fallback) — kit'in `sk-*` cevirileri, pakette onceden derlenmis JSON
        //      olarak gelir (`@lvntr/lang/php_{locale}.json` →
        //      vendor/lvntr/laravel-starter-kit/resources/js/lang/). Kaynak PHP
        //      `resources/lang/{en,tr}/sk-*.php`; `sk-lang-build.mjs` ile uretilir.
        //   2. APP (override) — tuketicinin `lang/` dizininden `laravel-vue-i18n` Vite
        //      plugin'inin urettigi `php_{locale}.json` (framework default + `validation.php`
        //      + tuketicinin ekledigi/ezdigi her `sk-*` anahtari).
        // Iki eager glob — SSR'da sync resolve sart, client'ta Promise.resolve ile sarmalanir.
        // lang JSON'lari kucuk oldugu icin tek bundle'a almak maliyetsiz.
        // `import: 'default'` SART: aksi halde eager glob degeri JSON modul namespace'i olur
        // ({ default: { ...ceviriler } }) ve iki modulu spread etmek ({ ...vendorModul, ...appModul })
        // yalnizca distaki `default` anahtarini ezerek TUM vendor cevirilerini dusurur — o zaman her
        // `sk-*` anahtari literal kalir. `import: 'default'` ile degerler DUZ ceviri objesidir.
        const appLangs = import.meta.glob<Record<string, string>>('../../lang/*.json', { eager: true, import: 'default' });
        const vendorLangs = import.meta.glob<Record<string, string>>('@lvntr/lang/*.json', { eager: true, import: 'default' });
        const resolveLang = (lang: string): Record<string, string> => {
            const vendor = vendorLangs[`/vendor/lvntr/laravel-starter-kit/resources/js/lang/php_${lang}.json`] ?? {};
            const appLang = appLangs[`../../lang/php_${lang}.json`] ?? {};
            // App son spread = app kazanir; app'te olmayan anahtar vendor'dan gelir (fallback).
            return { ...vendor, ...appLang };
        };
        app.use(i18nVue, {
            // laravel-vue-i18n resolve donusunun .default'unu acar (avoidExceptionOnPromise),
            // bu yuzden client'ta { default: ... } ile sarmaliyoruz. SSR sync yolu duz objeyi
            // dogrudan kullandigi icin resolveLang ciktisini oylece veriyoruz.
            resolve: ssr ? resolveLang : (lang: string) => Promise.resolve({ default: resolveLang(lang) }),
        })
            .use(PrimeVue, {
                theme: {
                    preset: AppPreset,
                    options: {
                        darkModeSelector: '.dark',
                        cssLayer: {
                            name: 'primevue',
                            order: 'tailwind-base, primevue, tailwind-utilities',
                        },
                    },
                },
            })
            .use(ConfirmationService)
            .use(ToastService)
            .use(PermissionPlugin)
            .directive('tooltip', Tooltip);
    },
});
