<script setup lang="ts">
    import { TB } from '@lvntr/components/TabBuilder/core';
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import { usePage } from '@inertiajs/vue3';
    import PasswordTab from './components/PasswordTab.vue';
    import ProfileInfoTab from './components/ProfileInfoTab.vue';
    import SessionsTab from './components/SessionsTab.vue';
    import TwoFactorTab from './components/TwoFactorTab.vue';

    interface Props {
        twoFactorEnabled: boolean;
        twoFactorConfirmed: boolean;
    }

    const props = defineProps<Props>();

    const page = usePage<{ features: { two_factor: boolean } }>();

    const tabConfig = TB.tabs()
        .vertical()
        .addTabs(
            TB.item()
                .key('general')
                .label('sk-profile.tabs.general')
                .description('sk-profile.tab_descriptions.general')
                .icon('pi pi-user')
                .iconColor('blue'),
            TB.item()
                .key('password')
                .label('sk-profile.tabs.password')
                .description('sk-profile.tab_descriptions.password')
                .icon('pi pi-lock')
                .iconColor('amber'),
            TB.item()
                .key('security')
                .label('sk-profile.tabs.security')
                .description('sk-profile.tab_descriptions.security')
                .icon('pi pi-shield')
                .iconColor('emerald')
                .visible(page.props.features.two_factor),
            TB.item()
                .key('sessions')
                .label('sk-profile.tabs.sessions')
                .description('sk-profile.tab_descriptions.sessions')
                .icon('pi pi-desktop')
                .iconColor('purple'),
        )
        .build();
</script>

<template>
    <AdminLayout :title="$t('sk-profile.title')" :subtitle="$t('sk-profile.subtitle')">
        <SkTabs :config="tabConfig">
            <template #general>
                <ProfileInfoTab />
            </template>

            <template #password>
                <PasswordTab />
            </template>

            <template #security>
                <TwoFactorTab
                    :two-factor-enabled="props.twoFactorEnabled"
                    :two-factor-confirmed="props.twoFactorConfirmed"
                />
            </template>

            <template #sessions>
                <SessionsTab />
            </template>
        </SkTabs>
    </AdminLayout>
</template>
