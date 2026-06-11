<script setup lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import { TB } from '@lvntr/components/TabBuilder/core';
    import ApiClientsManageTab from './components/ApiClientsManageTab.vue';
    import ApiClientsTab from './components/ApiClientsTab.vue';
    import ApiTokensManageTab from './components/ApiTokensManageTab.vue';
    import ContentLanguagesTab from './components/ContentLanguagesTab.vue';
    import FileManagerTab from './components/FileManagerTab.vue';
    import GeneralTab from './components/GeneralTab.vue';
    import MailTab from './components/MailTab.vue';
    import SecurityTab from './components/SecurityTab.vue';
    import StorageTab from './components/StorageTab.vue';
    import SystemHealthTab from './components/SystemHealthTab.vue';

    interface ScopeOption {
        id: string;
        description: string;
    }

    interface DoctorReport {
        version: number;
        generated_at: string;
        summary: { ok: number; warn: number; fail: number; total: number };
        checks: Array<{ name: string; status: 'ok' | 'warn' | 'fail'; message: string; hint: string }>;
    }

    interface Props {
        settings: {
            general: {
                app_name: string;
                tagline: string | null;
                admin_email: string;
                support_email: string | null;
                timezone: string;
                languages: string[];
                default_language: string;
                currency: string;
                date_format: string;
                logo_url: string | null;
                welcome_message: string | null;
            };
            auth: {
                registration: boolean;
                email_verification: boolean;
                two_factor: boolean;
                password_reset: boolean;
                login_throttle: boolean;
                password_min_length: number;
                password_expiry_days: number;
                password_require_mixed_case: boolean;
                password_require_numbers: boolean;
                password_require_symbols: boolean;
            };
            mail: {
                mailer: string;
                host: string | null;
                port: number | null;
                username: string | null;
                password: null;
                password_is_set: boolean;
                encryption: string | null;
                from_address: string;
                from_name: string;
                reply_to: string | null;
                send_limit: number;
            };
            storage: {
                media_disk: string;
                spaces_key: string | null;
                spaces_secret: null;
                spaces_secret_is_set: boolean;
                spaces_region: string | null;
                spaces_bucket: string | null;
                spaces_endpoint: string | null;
                spaces_url: string | null;
                aws_key: string | null;
                aws_secret: null;
                aws_secret_is_set: boolean;
                aws_region: string | null;
                aws_bucket: string | null;
                aws_url: string | null;
                aws_endpoint: string | null;
            };
            file_manager: {
                max_size_mb: number;
                storage_quota_gb: number;
                accepted_mimes: string[];
                allow_video: boolean;
                allow_audio: boolean;
            };
            turnstile: {
                enabled: boolean;
                site_key: string | null;
                secret_key: null;
                secret_key_is_set: boolean;
            };
            postman: {
                workspace_id: string | null;
                collection_id: string | null;
                api_key: null;
                api_key_is_set: boolean;
            };
            apidog: {
                project_id: string | null;
                access_token: null;
                access_token_is_set: boolean;
            };
        };
        timezones: string[];
        availableLanguages: Record<string, string>;
        availableScopes: ScopeOption[];
        healthReport: DoctorReport | null;
    }

    const props = defineProps<Props>();

    const tabConfig = TB.tabs()
        .vertical()
        .addTabs(
            TB.item()
                .key('general')
                .label('sk-setting.tabs.general')
                .description('sk-setting.tab_descriptions.general')
                .icon('pi pi-cog')
                .iconColor('blue'),
            TB.item()
                .key('auth')
                .label('sk-setting.tabs.auth')
                .description('sk-setting.tab_descriptions.auth')
                .icon('pi pi-id-card')
                .iconColor('amber'),
            TB.item()
                .key('mail')
                .label('sk-setting.tabs.mail')
                .description('sk-setting.tab_descriptions.mail')
                .icon('pi pi-envelope')
                .iconColor('emerald'),
            TB.item()
                .key('storage')
                .label('sk-setting.tabs.storage')
                .description('sk-setting.tab_descriptions.storage')
                .icon('pi pi-database')
                .iconColor('purple'),
            TB.item()
                .key('file_manager')
                .label('sk-setting.tabs.file_manager')
                .description('sk-setting.tab_descriptions.file_manager')
                .icon('pi pi-folder')
                .iconColor('teal'),
            TB.item()
                .key('content_languages')
                .label('sk-setting.tabs.content_languages')
                .description('sk-setting.tab_descriptions.content_languages')
                .icon('pi pi-language')
                .iconColor('green'),
            TB.item()
                .key('api_integrations')
                .label('sk-setting.tabs.api_integrations')
                .description('sk-setting.tab_descriptions.api_integrations')
                .icon('pi pi-code')
                .iconColor('indigo'),
            TB.item()
                .key('api_clients')
                .label('sk-setting.tabs.api_clients')
                .description('sk-setting.tab_descriptions.api_clients')
                .icon('pi pi-id-card')
                .iconColor('cyan')
                .role('system_admin'),
            TB.item()
                .key('api_tokens')
                .label('sk-setting.tabs.api_tokens')
                .description('sk-setting.tab_descriptions.api_tokens')
                .icon('pi pi-key')
                .iconColor('orange')
                .role('system_admin'),
            TB.item()
                .key('system_health')
                .label('sk-setting.tabs.system_health')
                .description('sk-setting.tab_descriptions.system_health')
                .icon('pi pi-heart-fill')
                .iconColor('rose')
                .role('system_admin'),
        )
        .build();
</script>

<template>
    <AdminLayout :title="$t('sk-setting.title')" :subtitle="$t('sk-setting.subtitle')">
        <SkTabs :config="tabConfig">
            <template #general>
                <GeneralTab
                    :settings="props.settings.general"
                    :timezones="props.timezones"
                    :available-languages="props.availableLanguages"
                />
            </template>

            <template #auth>
                <SecurityTab :auth-settings="props.settings.auth" :turnstile-settings="props.settings.turnstile" />
            </template>

            <template #mail>
                <MailTab :settings="props.settings.mail" />
            </template>

            <template #storage>
                <StorageTab :settings="props.settings.storage" />
            </template>

            <template #file_manager>
                <FileManagerTab :settings="props.settings.file_manager" />
            </template>

            <template #content_languages>
                <ContentLanguagesTab />
            </template>

            <template #api_integrations>
                <ApiClientsTab :postman="props.settings.postman" :apidog="props.settings.apidog" />
            </template>

            <template #api_clients>
                <ApiClientsManageTab />
            </template>

            <template #api_tokens>
                <ApiTokensManageTab :available-scopes="props.availableScopes" />
            </template>

            <template #system_health>
                <SystemHealthTab :report="props.healthReport" />
            </template>
        </SkTabs>
    </AdminLayout>
</template>
