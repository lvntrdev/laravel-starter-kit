/// <reference types="vite/client" />

import type { FlashMessages, SharedPageProps } from '@/types';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            // The auth shape is owned by SharedPageProps in types/index.ts — the
            // single source of truth that mirrors HandleInertiaRequests::share()
            // (user, role, role_names, permissions). Referencing it here keeps the
            // global usePage() type from drifting away from the backend contract.
            // The previous local `roles: string[]` WAS such a drift: the backend
            // never shares `auth.roles`, so it silently broke v-role and the header
            // role label for every authenticated user.
            auth: SharedPageProps['auth'];
            flash: FlashMessages;
            fileManagerSettings: {
                enable_trash: boolean;
            };
        };
    }
}

// Cloudflare Turnstile widget (loaded from external CDN script)
interface TurnstileInstance {
    render: (container: string | HTMLElement, options: Record<string, unknown>) => string;
    reset: (widgetId: string) => void;
    remove: (widgetId: string) => void;
    getResponse: (widgetId: string) => string | undefined;
}

declare global {
    interface Window {
        turnstile?: TurnstileInstance;
    }
}

// Wayfinder-generated route modules — actual types provided by build; this
// declaration is a fallback so typecheck passes without a running PHP process.
declare module '@/routes/*' {
    const routes: Readonly<
        Record<
            string,
            {
                url: (...args: unknown[]) => string;
                action: string;
                method: string;
            }
        >
    >;
    export default routes;
}
