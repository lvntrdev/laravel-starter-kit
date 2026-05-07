/// <reference types="vite/client" />

import type { User, FlashMessages } from '@/types';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            auth: {
                roles: string[];
                user: User | null;
            };
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
