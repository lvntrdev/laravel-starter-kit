// resources/js/composables/useBasePath.ts

import { usePage } from '@inertiajs/vue3';

/**
 * Prefix a root-relative app path with the sub-path the app is deployed under
 * (e.g. `https://host/admin/`), derived at runtime by comparing Inertia's
 * app-relative page URL with `window.location.pathname`.
 * Absolute and document-relative URLs are returned unchanged. Root-relative
 * app paths are always prefixed when a deploy sub-path exists, so callers must
 * pass client-constructed app paths and apply this exactly once.
 *
 * Deliberately NOT `import.meta.env.BASE_URL`: in a production Laravel/Vite
 * build that is the ASSET base (`/build/`, or a CDN URL), not the application
 * URL prefix — prefixing API calls with it would break every root deployment.
 *
 * For a root deploy the two paths are identical, so the path is returned
 * unchanged and there is no regression for the common case. Only needed for
 * raw `XMLHttpRequest` / `fetch` calls that build their own URL; Inertia
 * navigation already honours the base.
 */
export function withBasePath(path: string): string {
    if (!path.startsWith('/') || path.startsWith('//')) return path;

    const base = appBasePath();
    if (base === '') return path;

    return `${base}${path}`;
}

/**
 * The deploy sub-path (no trailing slash), or `''` for a root deploy.
 *
 * Inertia's `page.url` is app-relative (rooted at the deploy sub-path) while
 * `location.pathname` is the full browser path — the base is whatever prefix
 * remains after stripping the page's path portion off the browser path.
 */
function appBasePath(): string {
    if (typeof window === 'undefined') return '';

    try {
        const pagePath = (usePage().url ?? '/').split('?')[0] || '/';
        const locationPath = window.location.pathname || '/';

        if (pagePath === '/') {
            return locationPath.replace(/\/+$/, '');
        }

        if (locationPath.endsWith(pagePath)) {
            return locationPath.slice(0, locationPath.length - pagePath.length).replace(/\/+$/, '');
        }
    } catch {
        // usePage() outside a mounted Inertia app (tests, isolated mounts) —
        // treat as a root deploy.
    }

    return '';
}
