<script setup lang="ts">
    import { useCan } from '@/composables/useCan';
    import { useConfirm } from '@/composables/useConfirm';
    import { useDialog } from '@/composables/useDialog';
    import { useRefreshBus } from '@/composables/useRefreshBus';
    import { router } from '@inertiajs/vue3';
    import { DB } from '@lvntr/components/DatatableBuilder/core';
    import { trans } from 'laravel-vue-i18n';

    import ApiClientForm from './ApiClientForm.vue';
    import OneTimeSecretModal from './OneTimeSecretModal.vue';
    import apiClients from '@/routes/api-clients';

    interface ApiClient {
        id: string;
        name: string;
        grant_types: string[];
        redirect_uris: string[];
        scopes: string[];
        revoked: boolean;
        created_at: string;
        plain_secret: string | null;
    }

    const { confirmDelete } = useConfirm();
    const dialog = useDialog();
    const bus = useRefreshBus();
    const { can } = useCan();

    const REFRESH_KEY = 'api-clients-table';

    const tableBuilder = DB.table<ApiClient>()
        .route(apiClients.dtApi.url())
        .isCard(true)
        .title('sk-api-clients.title')
        .subtitle('sk-api-clients.subtitle')
        .searchable(true)
        .sortable(true)
        .addColumns(
            DB.column<ApiClient>().label('sk-api-clients.fields.name').key('name'),
            DB.column<ApiClient>()
                .label('sk-api-clients.fields.grant_type')
                .key('grant_types')
                .sortable(false)
                .render((client, escapeHtml) => {
                    const labels: Record<string, string> = {
                        authorization_code: trans('sk-api-clients.grant_types.authorization_code'),
                        client_credentials: trans('sk-api-clients.grant_types.client_credentials'),
                        personal_access: trans('sk-api-clients.grant_types.personal_access'),
                    };
                    return (
                        client.grant_types
                            ?.map((g) => labels[g] ?? g)
                            .map(
                                (l) =>
                                    `<span class="inline-flex items-center rounded-full bg-surface-100 px-2 py-0.5 text-base text-surface-700 dark:bg-surface-800 dark:text-surface-300">${escapeHtml(l)}</span>`,
                            )
                            .join(' ') ?? '—'
                    );
                }),
            DB.column<ApiClient>()
                .label('sk-api-clients.fields.client_id')
                .key('id')
                .render(
                    (client, escape) =>
                        `<code class="font-mono text-base text-surface-600 dark:text-surface-400">${escape(client.id)}</code>`,
                ),
            DB.column<ApiClient>()
                .label('sk-api-clients.fields.client_secret')
                .key('plain_secret')
                .sortable(false)
                .render(() => `<span class="font-mono text-base text-surface-400">••••••••</span>`),
            DB.column<ApiClient>().label('sk-common.created_at').key('created_at'),
        )
        .addActions(
            DB.action<ApiClient>()
                .icon('pi pi-pencil')
                .severity('warn')
                .tooltip(trans('sk-button.edit'))
                .visible(() => can('api-clients.update'))
                .handle((client) =>
                    dialog.open(
                        ApiClientForm,
                        { clientId: client.id, inDialog: true },
                        trans('sk-api-clients.edit'),
                        { width: '680px', refreshKey: REFRESH_KEY },
                    ),
                ),
            DB.action<ApiClient>()
                .icon('pi pi-ban')
                .severity('danger')
                .tooltip(trans('sk-api-clients.revoke'))
                .visible(() => can('api-clients.delete'))
                .handle((client) =>
                    confirmDelete(
                        () =>
                            router.delete(apiClients.destroy.url(client), {
                                onSuccess: () => bus.refresh(REFRESH_KEY),
                            }),
                        trans('sk-api-clients.revoke_confirm', { name: client.name }),
                    ),
                ),
        );

    if (can('api-clients.create')) {
        tableBuilder.create({
            label: 'sk-api-clients.create',
            onClick: () =>
                dialog.open(
                    ApiClientForm,
                    {
                        inDialog: true,
                        onCreated: (plainSecret: string) => {
                            bus.refresh(REFRESH_KEY);
                            dialog.open(
                                OneTimeSecretModal,
                                { secret: plainSecret, type: 'secret', onSuccess: () => dialog.close() },
                                trans('sk-api-clients.secret_modal.title'),
                                { width: '560px' },
                            );
                        },
                        onCancel: () => dialog.close(),
                    },
                    trans('sk-api-clients.create'),
                    { width: '680px' },
                ),
        });
    }

    const tableConfig = tableBuilder.build();
</script>

<template>
    <SkDatatable :config="tableConfig" :refresh-key="REFRESH_KEY" />
</template>
