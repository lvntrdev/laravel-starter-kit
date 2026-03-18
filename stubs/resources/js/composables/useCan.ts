// resources/js/composables/useCan.ts
import { usePage } from '@inertiajs/vue3';
import type { SharedPageProps } from '@/types';

/**
 * Composable for checking user permissions and roles in templates.
 *
 * Reads from Inertia shared `auth.permissions` and `auth.role`.
 *
 * Usage:
 *   const { can, hasRole } = useCan();
 *   can('users.create')  // true/false
 *   hasRole('admin')     // true/false
 */
export function useCan() {
    const page = usePage<SharedPageProps>();

    /**
     * Check if the authenticated user has the given permission.
     */
    function can(permission: string): boolean {
        return page.props.auth.permissions?.includes(permission) ?? false;
    }

    /**
     * Check if the authenticated user has ANY of the given permissions.
     */
    function canAny(permissions: string[]): boolean {
        return permissions.some((p) => can(p));
    }

    /**
     * Check if the authenticated user has the given role.
     */
    function hasRole(role: string): boolean {
        return page.props.auth.role_names?.includes(role) ?? false;
    }

    return { can, canAny, hasRole };
}
