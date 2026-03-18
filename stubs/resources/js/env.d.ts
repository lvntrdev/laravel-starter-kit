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
        };
    }
}
