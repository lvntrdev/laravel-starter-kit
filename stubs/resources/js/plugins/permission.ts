// resources/js/plugins/permission.ts
import type { App, Directive } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Check if the current user has the given permission(s).
 * Accepts a single permission string or an array (all must match).
 */
export function useCan(): {
    can: (permission: string | string[]) => boolean;
    hasRole: (role: string | string[]) => boolean;
    hasAnyRole: (roles: string[]) => boolean;
    hasAnyPermission: (permissions: string[]) => boolean;
} {
    const page = usePage();

    const userPermissions = computed<string[]>(
        () => (page.props.auth as { permissions?: string[] })?.permissions ?? [],
    );
    const userRoles = computed<string[]>(() => (page.props.auth as { roles?: string[] })?.roles ?? []);

    function can(permission: string | string[]): boolean {
        const perms = Array.isArray(permission) ? permission : [permission];
        return perms.every((p) => userPermissions.value.includes(p));
    }

    function hasAnyPermission(permissions: string[]): boolean {
        return permissions.some((p) => userPermissions.value.includes(p));
    }

    function hasRole(role: string | string[]): boolean {
        const roles = Array.isArray(role) ? role : [role];
        return roles.every((r) => userRoles.value.includes(r));
    }

    function hasAnyRole(roles: string[]): boolean {
        return roles.some((r) => userRoles.value.includes(r));
    }

    return { can, hasRole, hasAnyRole, hasAnyPermission };
}

/**
 * v-can directive — hides/removes element if user lacks the permission.
 *
 * Usage:
 *   v-can="'create'"            — single permission
 *   v-can="['create','update']" — all must match
 *   v-can:any="['create','update']" — any must match
 *   v-role="'admin'"            — single role
 *   v-role:any="['admin','system_admin']" — any role
 */
function getPermissions(page: ReturnType<typeof usePage>): string[] {
    return (page.props.auth as { permissions?: string[] })?.permissions ?? [];
}

function getRoles(page: ReturnType<typeof usePage>): string[] {
    return (page.props.auth as { roles?: string[] })?.roles ?? [];
}

/**
 * Remove element from DOM entirely and leave a comment placeholder.
 * Restores the element when permission/role is granted on update.
 */
interface PermissionEl extends HTMLElement {
    _v_permission_anchor?: Comment;
    _v_permission_parent?: ParentNode;
}

function removeElement(el: PermissionEl): void {
    if (!el._v_permission_anchor) {
        el._v_permission_anchor = document.createComment('');
    }
    const parent = el.parentNode;
    if (parent) {
        el._v_permission_parent = parent;
        parent.replaceChild(el._v_permission_anchor, el);
    }
}

function restoreElement(el: PermissionEl): void {
    const anchor = el._v_permission_anchor;
    if (anchor?.parentNode) {
        anchor.parentNode.replaceChild(el, anchor);
    }
}

const vCan: Directive<PermissionEl, string | string[]> = {
    mounted(el, binding) {
        const page = usePage();
        const permissions = getPermissions(page);
        const perms = Array.isArray(binding.value) ? binding.value : [binding.value];

        const hasPermission =
            binding.arg === 'any'
                ? perms.some((p) => permissions.includes(p))
                : perms.every((p) => permissions.includes(p));

        if (!hasPermission) {
            removeElement(el);
        }
    },
    updated(el, binding) {
        const page = usePage();
        const permissions = getPermissions(page);
        const perms = Array.isArray(binding.value) ? binding.value : [binding.value];

        const hasPermission =
            binding.arg === 'any'
                ? perms.some((p) => permissions.includes(p))
                : perms.every((p) => permissions.includes(p));

        if (hasPermission) {
            restoreElement(el);
        } else {
            removeElement(el);
        }
    },
};

const vRole: Directive<PermissionEl, string | string[]> = {
    mounted(el, binding) {
        const page = usePage();
        const roles = getRoles(page);
        const requiredRoles = Array.isArray(binding.value) ? binding.value : [binding.value];

        const hasRole =
            binding.arg === 'any'
                ? requiredRoles.some((r) => roles.includes(r))
                : requiredRoles.every((r) => roles.includes(r));

        if (!hasRole) {
            removeElement(el);
        }
    },
    updated(el, binding) {
        const page = usePage();
        const roles = getRoles(page);
        const requiredRoles = Array.isArray(binding.value) ? binding.value : [binding.value];

        const hasRole =
            binding.arg === 'any'
                ? requiredRoles.some((r) => roles.includes(r))
                : requiredRoles.every((r) => roles.includes(r));

        if (hasRole) {
            restoreElement(el);
        } else {
            removeElement(el);
        }
    },
};

/**
 * Vue plugin that registers v-can, v-role directives and
 * provides $can, $hasRole global properties.
 */
export const PermissionPlugin = {
    install(app: App): void {
        app.directive('can', vCan);
        app.directive('role', vRole);
    },
};
