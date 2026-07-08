// resources/js/composables/useCsrf.ts

/**
 * Read the Laravel `XSRF-TOKEN` cookie for CSRF-protected requests.
 *
 * Single source of truth for the token read — `useApi`, the FileManager upload
 * XHR and the rich-text editor's image upload all funnel through here so cookie
 * parsing / URL-decoding stays consistent. Returns the decoded token, or `''`
 * when the cookie is absent (SSR / not yet set); the empty string keeps callers
 * that assign straight into a request header trivial (an empty value is falsy,
 * so `if (token)` guards still skip the header).
 */
export function getXsrfToken(): string {
    if (typeof document === 'undefined') return '';
    const match = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[2]) : '';
}
