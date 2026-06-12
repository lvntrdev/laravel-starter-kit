// resources/js/composables/useAdminMenu.ts
import { useMenuBuilder } from '@/composables/useMenuBuilder';
import activityLogs from '@/routes/activity-logs';
import dashboard from '@/routes/dashboard';
import files from '@/routes/files';
import logs from '@/routes/logs';
import roles from '@/routes/roles';
import settings from '@/routes/settings';
import users from '@/routes/users';
import type { MenuItem } from '@/types';

export function useAdminMenu() {
    const allItems: MenuItem[] = [
        {
            title: 'sk-menu.dashboard',
            icon: 'pi pi-home',
            href: dashboard.index.url(),
        },
        {
            title: 'sk-menu.user_management',
            section: true,
        },
        {
            title: 'sk-menu.users',
            icon: 'pi pi-users',
            href: users.index.url(),
            permission: 'users.read',
        },
        {
            title: 'sk-menu.roles_permissions',
            icon: 'pi pi-shield',
            href: roles.index.url(),
            permission: 'roles.read',
        },
        {
            title: 'sk-menu.files',
            icon: 'pi pi-folder',
            href: files.index.url(),
            permission: 'files.read',
        },
        {
            title: 'sk-menu.system',
            section: true,
        },
        {
            title: 'sk-menu.activity_logs',
            icon: 'pi pi-history',
            href: activityLogs.index.url(),
            permission: 'activity-logs.read',
        },
        {
            title: 'sk-menu.logs',
            icon: 'pi pi-file',
            href: logs.index.url(),
            role: 'system_admin',
        },
        {
            title: 'sk-menu.settings',
            icon: 'pi pi-cog',
            href: settings.index.url(),
            permission: 'settings.read',
        },
        {
            // Vendor-resident gelistirici showcase'i (SkComponents/Show) —
            // sekmeli tek sayfa; rota paketten mount edilir (/sk-components).
            title: 'sk-menu.components',
            icon: 'pi pi-objects-column',
            href: '/sk-components',
            role: 'system_admin',
        },
    ];

    return useMenuBuilder(allItems);
}
