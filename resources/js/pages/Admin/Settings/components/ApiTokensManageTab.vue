<script setup lang="ts">
    import { useCan } from '@/composables/useCan';
    import { useConfirm } from '@/composables/useConfirm';
    import { useDialog } from '@/composables/useDialog';
    import { useRefreshBus } from '@/composables/useRefreshBus';
    import { router } from '@inertiajs/vue3';
    import { DB } from '@lvntr/components/DatatableBuilder/core';
    import { trans } from 'laravel-vue-i18n';

    import CreateTokenModal from './CreateTokenModal.vue';
    import OneTimeSecretModal from './OneTimeSecretModal.vue';
    import apiTokens from '@/routes/api-tokens';

    interface ScopeOption {
        id: string;
        description: string;
    }

    interface ApiToken {
        id: string;
        name: string;
        scopes: string[];
        revoked: boolean;
        expires_at: string | null;
        created_at: string;
        user?: {
            id: string;
            full_name: string;
            email: string;
        } | null;
    }

    interface Props {
        availableScopes: ScopeOption[];
    }

    const props = defineProps<Props>();

    const { confirmDelete } = useConfirm();
    const dialog = useDialog();
    const bus = useRefreshBus();
    const { can } = useCan();

    const REFRESH_KEY = 'api-tokens-table';

    const tableBuilder = DB.table<ApiToken>()
        .route(apiTokens.dtApi.url())
        .isCard(true)
        .title('sk-api-tokens.title')
        .subtitle('sk-api-tokens.subtitle')
        .searchable(true)
        .sortable(true)
        .addColumns(
            DB.column<ApiToken>().label('sk-api-tokens.fields.name').key('name'),
            DB.column<ApiToken>()
                .label('sk-api-tokens.fields.user')
                .key('user')
                .sortable(false)
                .render((token, escape) =>
                    token.user
                        ? `<span>${escape(token.user.full_name)}<br><small class="text-surface-400">${escape(token.user.email)}</small></span>`
                        : `<span class="text-surface-400">—</span>`,
                ),
            DB.column<ApiToken>()
                .label('sk-api-tokens.fields.scopes')
                .key('scopes')
                .sortable(false)
                .render((token, escape) =>
                    token.scopes && token.scopes.length > 0
                        ? token.scopes
                              .map(
                                  (s) =>
                                      `<span class="mr-1 inline-flex items-center rounded-full bg-surface-100 px-2 py-0.5 text-base text-surface-700 dark:bg-surface-800 dark:text-surface-300">${escape(s)}</span>`,
                              )
                              .join('')
                        : `<span class="text-surface-400">—</span>`,
                ),
            DB.column<ApiToken>()
                .label('sk-api-tokens.fields.expires_at')
                .key('expires_at')
                .render((token) => {
                    if (!token.expires_at) return `<span class="text-surface-400">—</span>`;
                    const date = new Date(token.expires_at);
                    const isExpired = date < new Date();
                    const locale = document.documentElement.lang || 'en-US';
                    const formatted = date.toLocaleDateString(locale, {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                    });
                    return isExpired
                        ? `<span class="text-red-500">${formatted}</span>`
                        : `<span>${formatted}</span>`;
                }),
            DB.column<ApiToken>().label('sk-common.created_at').key('created_at'),
        )
        .addActions(
            DB.action<ApiToken>()
                .icon('pi pi-ban')
                .severity('danger')
                .tooltip(trans('sk-api-tokens.revoke'))
                .visible(() => can('api-tokens.delete'))
                .handle((token) =>
                    confirmDelete(
                        () =>
                            router.delete(apiTokens.destroy.url(token), {
                                onSuccess: () => bus.refresh(REFRESH_KEY),
                            }),
                        trans('sk-api-tokens.revoke_confirm'),
                    ),
                ),
        );

    if (can('api-tokens.create')) {
        tableBuilder.create({
            label: 'sk-api-tokens.create',
            onClick: () =>
                dialog.open(
                    CreateTokenModal,
                    {
                        availableScopes: props.availableScopes,
                        inDialog: true,
                        onCreated: (accessToken: string) => {
                            bus.refresh(REFRESH_KEY);
                            dialog.open(
                                OneTimeSecretModal,
                                { secret: accessToken, type: 'token', onSuccess: () => dialog.close() },
                                trans('sk-api-tokens.token_modal.title'),
                                { width: '560px' },
                            );
                        },
                        onCancel: () => dialog.close(),
                    },
                    trans('sk-api-tokens.create'),
                    { width: '520px' },
                ),
        });
    }

    const tableConfig = tableBuilder.build();
</script>

<template>
    <SkDatatable :config="tableConfig" :refresh-key="REFRESH_KEY" />
</template>
