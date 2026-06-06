// resources/js/plugins/permission.ts
import type { App, Directive } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedPageProps } from '@/types';

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
    return (page.props.auth as SharedPageProps['auth'])?.permissions ?? [];
}

// Roles come from Inertia's `auth.role_names` (role slugs) — NOT `auth.roles`,
// which the backend (HandleInertiaRequests::share) never shares. Casting to the
// canonical SharedPageProps['auth'] instead of an ad-hoc `{ roles?: string[] }`
// makes a wrong key a compile error rather than a silently-empty array.
function getRoles(page: ReturnType<typeof usePage>): string[] {
    return (page.props.auth as SharedPageProps['auth'])?.role_names ?? [];
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
