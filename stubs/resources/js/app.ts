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
        // Tek eager glob — SSR'da sync resolve sart, client'ta Promise.resolve ile sarmalanir.
        // Dual static+dynamic glob Vite "dynamic import will not move module into another chunk"
        // uyarisi cikariyor; lang JSON'lari kucuk oldugu icin tek bundle'a almak maliyetsiz.
        const langs = import.meta.glob<Record<string, string>>('../../lang/*.json', { eager: true });
        const resolveLang = (lang: string) => langs[`../../lang/php_${lang}.json`];
        app.use(i18nVue, {
            resolve: ssr ? resolveLang : (lang: string) => Promise.resolve(resolveLang(lang)),
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
