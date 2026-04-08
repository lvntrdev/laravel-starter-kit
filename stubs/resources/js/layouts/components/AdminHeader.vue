<!-- resources/js/components/Admin/AdminHeader.vue -->
<script setup lang="ts">
    import { usePage, router } from '@inertiajs/vue3';
    import type { MenuItem } from 'primevue/menuitem';
    import type { User } from '@/types';
    import { trans } from 'laravel-vue-i18n';

    interface Props {
        collapsed: boolean;
        isMobile: boolean;
        isDark: boolean;
    }

    defineProps<Props>();

    const emit = defineEmits<{
        toggleSidebar: [];
        toggleDark: [];
    }>();

    const page = usePage();
    const user = computed(() => page.props.auth?.user as User | undefined);
    const role = computed(() => (page.props.auth?.role as string) ?? '');
    const isLocal = computed(() => page.props.appEnv === 'local');

    const initials = computed(() => {
        if (!user.value) return '';
        const first = (user.value.first_name ?? '').charAt(0);
        const last = (user.value.last_name ?? '').charAt(0);
        return (first + last).toUpperCase();
    });

    const userMenuRef = ref();

    const userMenuItems = computed<MenuItem[]>(() => [
        {
            label: trans('admin.menu.profile'),
            icon: 'pi pi-user',
            command: () => router.visit('/profile'),
        },
        { separator: true },
        {
            label: trans('admin.menu.logout'),
            icon: 'pi pi-sign-out',
            command: () => router.post('/logout'),
        },
    ]);

    function toggleUserMenu(event: Event): void {
        userMenuRef.value?.toggle(event);
    }
</script>

<template>
    <header class="admin-header">
        <div class="admin-header__left">
            <button
                class="admin-header__btn"
                :title="
                    isMobile
                        ? $t('admin.layout.open_menu')
                        : collapsed
                            ? $t('admin.layout.expand_menu')
                            : $t('admin.layout.collapse_menu')
                "
                @click="emit('toggleSidebar')"
            >
                <i :class="isMobile ? 'pi pi-bars' : collapsed ? 'pi pi-align-left' : 'pi pi-align-right'" />
            </button>

            <span v-if="isLocal" class="admin-header__tag admin-header__tag--dev"> Dev Mode </span>
        </div>

        <div class="admin-header__right">
            <button
                class="admin-header__btn"
                :title="isDark ? $t('admin.layout.light_mode') : $t('admin.layout.dark_mode')"
                @click="emit('toggleDark')"
            >
                <i :class="isDark ? 'pi pi-sun' : 'pi pi-moon'" />
            </button>

            <button class="admin-header__btn" :title="$t('admin.layout.notifications')">
                <i class="pi pi-bell" />
            </button>

            <!-- User Profile -->
            <button v-if="user" class="admin-header__user" @click="toggleUserMenu">
                <div class="admin-header__user-info">
                    <span class="admin-header__user-name">{{ user.full_name }}</span>
                    <span v-if="role" class="admin-header__user-role">
                        {{ role }}
                    </span>
                </div>
                <img v-if="user.avatar_url" :src="user.avatar_url" alt="Avatar" class="admin-header__avatar">
                <div v-else class="admin-header__avatar-placeholder">
                    {{ initials }}
                </div>
            </button>

            <Menu ref="userMenuRef" :model="userMenuItems" :popup="true" />
        </div>
    </header>
</template>
