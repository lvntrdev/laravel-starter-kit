<!-- resources/js/components/Admin/AdminHeader.vue -->
<script setup lang="ts">
    import { usePage, router } from '@inertiajs/vue3';
    import type { MenuItem } from 'primevue/menuitem';
    import type { User } from '@/types';
    import { ACCENT_COLORS, ACCENT_SWATCH } from '@/composables/useAccentColor';
    import type { AccentColor, SidebarStyle } from '@/composables/useAccentColor';
    import { trans } from 'laravel-vue-i18n';
    import locale from '@/routes/locale';

    interface Props {
        collapsed: boolean;
        isMobile: boolean;
        isDark: boolean;
        accent: AccentColor;
        sidebarStyle: SidebarStyle;
    }

    defineProps<Props>();

    const emit = defineEmits<{
        toggleSidebar: [];
        toggleDark: [];
        setAccent: [color: AccentColor];
        setSidebarStyle: [style: SidebarStyle];
    }>();

    const page = usePage();
    const user = computed(() => page.props.auth?.user as User | undefined);
    const role = computed(() => page.props.auth?.role ?? '');
    const isLocal = computed(() => page.props.appEnv === 'local');
    const isDebug = computed(() => page.props.appDebug === true);

    const currentLocale = computed(() => (page.props.locale as string) ?? 'en');
    const availableLocales = computed(() => (page.props.availableLocales as Record<string, string>) ?? {});
    const showLocaleSwitcher = computed(() => Object.keys(availableLocales.value).length > 1);

    const localeMenuRef = ref();

    const localeMenuItems = computed<MenuItem[]>(() =>
        Object.entries(availableLocales.value).map(([code, label]) => ({
            label,
            code,
            active: code === currentLocale.value,
            command: () => switchLocale(code),
        })),
    );

    function toggleLocaleMenu(event: Event): void {
        localeMenuRef.value?.toggle(event);
    }

    function switchLocale(code: string): void {
        if (code === currentLocale.value) {
            return;
        }

        router.post(
            locale.update.url(),
            { locale: code },
            {
                preserveScroll: true,
                onSuccess: () => window.location.reload(),
            },
        );
    }

    const initials = computed(() => {
        if (!user.value) return '';
        const first = (user.value.first_name ?? '').charAt(0);
        const last = (user.value.last_name ?? '').charAt(0);
        return (first + last).toUpperCase();
    });

    const userMenuRef = ref();

    const userMenuItems = computed<MenuItem[]>(() => [
        {
            label: trans('sk-menu.profile'),
            icon: 'pi pi-user',
            command: () => router.visit('/profile'),
        },
        { separator: true },
        {
            label: trans('sk-menu.logout'),
            icon: 'pi pi-sign-out',
            danger: true,
            command: () => router.post('/logout'),
        },
    ]);

    function toggleUserMenu(event: Event): void {
        userMenuRef.value?.toggle(event);
    }

    // Appearance popover — dark mode + accent color picker.
    const appearanceRef = ref();

    function toggleAppearance(event: Event): void {
        appearanceRef.value?.toggle(event);
    }

    function selectAccent(color: AccentColor): void {
        emit('setAccent', color);
    }

    function selectSidebarStyle(style: SidebarStyle): void {
        emit('setSidebarStyle', style);
    }
</script>

<template>
    <header class="admin-header">
        <div class="admin-header__left">
            <button
                class="admin-header__btn"
                :title="
                    isMobile
                        ? $t('sk-layout.open_menu')
                        : collapsed
                            ? $t('sk-layout.expand_menu')
                            : $t('sk-layout.collapse_menu')
                "
                @click="emit('toggleSidebar')"
            >
                <i :class="isMobile ? 'pi pi-bars' : collapsed ? 'pi pi-align-left' : 'pi pi-align-right'" />
            </button>

            <span v-if="isLocal" class="admin-header__tag admin-header__tag--dev"> Dev Mode </span>
            <span v-if="isDebug" class="admin-header__tag admin-header__tag--debug"> Debug Mode </span>
        </div>

        <div class="admin-header__right">
            <!-- Language Switcher (only when more than one language is active) -->
            <template v-if="showLocaleSwitcher">
                <button
                    class="admin-header__btn admin-header__btn--locale"
                    :title="availableLocales[currentLocale]"
                    @click="toggleLocaleMenu"
                >
                    <i class="pi pi-globe" />
                    <span class="admin-header__locale-code">{{ currentLocale.toUpperCase() }}</span>
                </button>
                <Menu ref="localeMenuRef" class="sk-locale-menu" :model="localeMenuItems" :popup="true">
                    <template #start>
                        <div class="sk-locale-menu__label">
                            {{ $t('sk-layout.language') }}
                        </div>
                    </template>
                    <template #item="{ item, props }">
                        <a
                            v-bind="props.action"
                            class="sk-locale-menu__item"
                            :class="{ 'sk-locale-menu__item--active': (item as any).active }"
                        >
                            <span class="sk-locale-menu__code">{{ (item as any).code?.toUpperCase() }}</span>
                            <span class="sk-locale-menu__name">{{ item.label }}</span>
                            <i v-if="(item as any).active" class="pi pi-check sk-locale-menu__check" />
                        </a>
                    </template>
                </Menu>
            </template>

            <!-- Appearance: dark mode + accent color picker -->
            <button
                class="admin-header__btn"
                :title="$t('sk-layout.appearance')"
                @click="toggleAppearance"
            >
                <i class="pi pi-palette" />
            </button>
            <Popover ref="appearanceRef">
                <div class="w-[360px] max-w-[90vw]">
                    <!-- Dark mode -->
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex flex-col">
                            <span class="text-sm font-semibold text-surface-800 dark:text-surface-0">
                                {{ $t('sk-layout.dark_mode') }}
                            </span>
                            <span class="text-xs text-surface-500">
                                {{ $t('sk-layout.dark_mode_hint') }}
                            </span>
                        </div>
                        <ToggleSwitch :model-value="isDark" @update:model-value="emit('toggleDark')" />
                    </div>

                    <hr class="my-4 border-surface-200 dark:border-surface-700">

                    <!-- Sidebar style: colored vs light (light mode only) -->
                    <div class="mb-3 flex flex-col">
                        <span class="text-xs font-semibold uppercase tracking-wide text-surface-500">
                            {{ $t('sk-layout.sidebar_style') }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="flex items-center gap-2.5 rounded-xl border px-3.5 py-3 text-left transition"
                            :class="
                                sidebarStyle === 'colored'
                                    ? 'border-primary-500 bg-primary-50 dark:border-primary-500 dark:bg-primary-500/10'
                                    : 'border-surface-200 hover:border-surface-300 dark:border-surface-700 dark:hover:border-surface-600'
                            "
                            @click="selectSidebarStyle('colored')"
                        >
                            <span
                                class="h-5 w-5 shrink-0 rounded-md shadow-sm ring-1 ring-inset ring-black/10 dark:ring-white/15"
                                style="background: var(--p-primary-color)"
                            />
                            <span
                                class="text-sm font-medium"
                                :class="sidebarStyle === 'colored' ? 'text-surface-800 dark:text-surface-0' : 'text-surface-500'"
                            >
                                {{ $t('sk-layout.sidebar_style_colored') }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="flex items-center gap-2.5 rounded-xl border px-3.5 py-3 text-left transition"
                            :class="
                                sidebarStyle === 'light'
                                    ? 'border-primary-500 bg-primary-50 dark:border-primary-500 dark:bg-primary-500/10'
                                    : 'border-surface-200 hover:border-surface-300 dark:border-surface-700 dark:hover:border-surface-600'
                            "
                            @click="selectSidebarStyle('light')"
                        >
                            <span class="h-5 w-5 shrink-0 rounded-md border border-surface-300 bg-surface-0 shadow-sm dark:border-surface-500" />
                            <span
                                class="text-sm font-medium"
                                :class="sidebarStyle === 'light' ? 'text-surface-800 dark:text-surface-0' : 'text-surface-500'"
                            >
                                {{ $t('sk-layout.sidebar_style_light') }}
                            </span>
                        </button>
                    </div>

                    <!-- Sidebar color theme -->
                    <div class="mb-3 mt-5 flex flex-col">
                        <span class="text-xs font-semibold uppercase tracking-wide text-surface-500">
                            {{ $t('sk-layout.accent_color') }}
                        </span>
                    </div>

                    <div class="grid grid-cols-6 gap-x-2 gap-y-3">
                        <!-- Default -->
                        <button
                            type="button"
                            class="flex flex-col items-center gap-1"
                            :title="$t('sk-layout.color_default')"
                            @click="selectAccent('default')"
                        >
                            <span
                                class="flex h-12 w-full items-center justify-center rounded-lg border border-surface-200 bg-surface-0 text-surface-400 transition dark:border-surface-700 dark:bg-surface-800"
                                :class="{ 'ring-2 ring-primary-500 ring-offset-1 ring-offset-surface-0 dark:ring-offset-surface-900': accent === 'default' }"
                            >
                                <i class="pi pi-ban" />
                            </span>
                            <span class="w-full truncate text-center text-[0.7rem] text-surface-500">
                                {{ $t('sk-layout.color_default') }}
                            </span>
                        </button>

                        <!-- Colors -->
                        <button
                            v-for="color in ACCENT_COLORS"
                            :key="color"
                            type="button"
                            class="flex flex-col items-center gap-1"
                            :title="color"
                            @click="selectAccent(color)"
                        >
                            <span
                                class="flex h-12 w-full items-center justify-center rounded-lg text-white shadow-sm transition"
                                :style="{ background: ACCENT_SWATCH[color] }"
                                :class="{ 'ring-2 ring-surface-900 ring-offset-1 ring-offset-surface-0 dark:ring-surface-0 dark:ring-offset-surface-900': accent === color }"
                            >
                                <i v-if="accent === color" class="pi pi-check text-sm" />
                            </span>
                            <span class="w-full truncate text-center text-[0.7rem] capitalize text-surface-500">
                                {{ color }}
                            </span>
                        </button>
                    </div>
                </div>
            </Popover>

            <button class="admin-header__btn" :title="$t('sk-layout.notifications')">
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

            <Menu ref="userMenuRef" class="sk-user-menu" :model="userMenuItems" :popup="true">
                <template #start>
                    <div v-if="user" class="sk-user-menu__header">
                        <img v-if="user.avatar_url" :src="user.avatar_url" alt="Avatar" class="sk-user-menu__avatar">
                        <div v-else class="sk-user-menu__avatar sk-user-menu__avatar--placeholder">
                            {{ initials }}
                        </div>
                        <div class="sk-user-menu__identity">
                            <div class="sk-user-menu__name">
                                {{ user.full_name }}
                            </div>
                            <div class="sk-user-menu__email">
                                {{ user.email }}
                            </div>
                            <div v-if="role" class="sk-user-menu__role">
                                <i class="pi pi-shield" />
                                <span>{{ role }}</span>
                            </div>
                        </div>
                    </div>
                </template>
                <template #item="{ item, props }">
                    <a
                        v-bind="props.action"
                        class="sk-user-menu__item"
                        :class="{ 'sk-user-menu__item--danger': (item as any).danger }"
                    >
                        <span class="sk-user-menu__item-icon">
                            <i :class="item.icon" />
                        </span>
                        <span class="sk-user-menu__item-label">{{ item.label }}</span>
                        <i class="pi pi-arrow-up-right sk-user-menu__item-arrow" />
                    </a>
                </template>
            </Menu>
        </div>
    </header>
</template>
