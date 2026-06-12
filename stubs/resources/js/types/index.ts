// resources/js/types/index.ts

import type { User } from './user';

export type { User, UserStatus } from './user';

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface FlashMessages {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
    status?: string;
}

/**
 * Global appearance defaults — the admin-controlled "what new / logged-out /
 * not-yet-personalized users see" layer. Shared on EVERY Inertia response (incl.
 * the login screen) by `HandleInertiaRequests`, so boot logic can apply the
 * admin-set look before the user makes any personal choice.
 *
 * Per-user header-popover choices (accent / dark mode / sidebar style, kept in
 * localStorage) still OVERRIDE these defaults for that user.
 */
export interface Appearance {
    /** Active build theme folder name (resolved server-side). */
    theme: string;
    /**
     * Theme folder names available to choose from. Present only in the
     * permission-gated settings payload — deliberately stripped from the
     * global (unauthenticated) Inertia share, so it is optional here.
     */
    available_themes?: string[];
    /** Default accent color name, or `'default'` for the kit primary. */
    accent_color: string;
    /** Whether dark mode is the default for users with no personal choice. */
    dark_mode_default: boolean;
    /** Default sidebar surface treatment (`colored` | `light`). */
    sidebar_style: string;
    /** Resolved public URL of the light-variant logo (falls back to legacy general.logo). */
    logo_light_url: string | null;
    /** Resolved public URL of the dark-variant logo. */
    logo_dark_url: string | null;
    /** Resolved public URL of the favicon. */
    favicon_url: string | null;
}

export interface SharedPageProps {
    auth: {
        user: User | null;
        role: string | null;
        role_names: string[];
        permissions: string[];
    };
    flash: FlashMessages;
    /**
     * Global appearance defaults shared on every page (incl. installer/login).
     * Optional only to stay tolerant of partial reloads that don't request it.
     */
    appearance?: Appearance;
    enums: Record<string, Array<{ value: string | number; label: string; severity: string }>>;
    locale: string;
    availableLocales: Record<string, string>;
    /**
     * Active content languages ({ code: name }) that drive translatable content
     * fields — distinct from `availableLocales` (admin UI translation). Optional:
     * absent on installer pages and on consumers without the content-languages
     * table, where translatable inputs fall back to `availableLocales`.
     */
    availableContentLocales?: Record<string, string>;
    [key: string]: unknown;
}

export interface MenuItem {
    title: string;
    icon?: string;
    href?: string;
    external?: boolean;
    section?: boolean;
    children?: MenuItem[];
    permission?: string;
    role?: string | string[];
}

export interface MenuContext {
    isItemActive: (item: MenuItem) => boolean;
    isGroupOpen: (item: MenuItem) => boolean;
}
