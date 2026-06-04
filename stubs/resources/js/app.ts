import '../css/app.css';
import 'primeicons/primeicons.css';
import { createInertiaApp, usePage } from '@inertiajs/vue3';
import { createApp, createSSRApp, h } from 'vue';
import axios from 'axios';
import { i18nVue } from 'laravel-vue-i18n';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import Tooltip from 'primevue/tooltip';
import { resolvePreset } from '@/theme/presets';
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
    // We use `setup` (not `withApp`) so the per-request Inertia page props are
    // available *before* the app is constructed. Inertia only populates the
    // reactive `usePage()` store inside the App component's own setup, which
    // runs after `withApp` — so `withApp` cannot see the request's theme on the
    // server. `setup` receives `props.initialPage`, which carries the shared
    // `theme` prop for THIS request on both server and client. That lets the
    // server render the correct preset's CSS up front → no FOUC, and the client
    // hydrates from the identical serialized page → no flash on hydration.
    setup({ el, App, props, plugin }) {
        // `el === null` only on the SSR server; on the client it is the mount node.
        const ssr = el === null;

        // Initial PrimeVue preset, resolved per-request from the shared `theme`
        // prop. `resolvePreset` never returns undefined: an unknown/missing name
        // collapses to the default preset, so the first paint is always valid.
        // Runtime, deploy-less theme swaps after hydration go through
        // `useTheme().setTheme()` (client-only `usePreset`); this is just the seed.
        const initialPreset = resolvePreset(props.initialPage.props.theme as string | undefined);

        // Tek eager glob — SSR'da sync resolve sart, client'ta Promise.resolve ile sarmalanir.
        // Dual static+dynamic glob Vite "dynamic import will not move module into another chunk"
        // uyarisi cikariyor; lang JSON'lari kucuk oldugu icin tek bundle'a almak maliyetsiz.
        const langs = import.meta.glob<Record<string, string>>('../../lang/*.json', { eager: true });
        const resolveLang = (lang: string) => langs[`../../lang/php_${lang}.json`];

        const app = (ssr || el?.hasAttribute('data-server-rendered'))
            ? createSSRApp({ render: () => h(App, props) })
            : createApp({ render: () => h(App, props) });

        app.use(plugin)
            .use(i18nVue, {
                resolve: ssr ? resolveLang : (lang: string) => Promise.resolve(resolveLang(lang)),
            })
            .use(PrimeVue, {
                theme: {
                    preset: initialPreset,
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

        if (!ssr && el) {
            app.mount(el);
        }

        return app;
    },
});
