<script setup lang="ts">
    import { useCan } from '@/composables/useCan';
    import { useConfirm } from '@/composables/useConfirm';
    import { useDialog } from '@/composables/useDialog';
    import { useRefreshBus } from '@/composables/useRefreshBus';
    import { router } from '@inertiajs/vue3';
    import { DB } from '@lvntr/components/DatatableBuilder/core';
    import { trans } from 'laravel-vue-i18n';
    import { reactive } from 'vue';

    import ApiClientForm from '@/pages/Admin/ApiClients/components/ApiClientForm.vue';
    import OneTimeSecretModal from '@/pages/Admin/ApiClients/components/OneTimeSecretModal.vue';
    import apiClients from '@/routes/api-clients';

    interface ScopeOption {
        id: string;
        description: string;
    }

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

    interface Props {
        availableScopes: ScopeOption[];
    }

    const props = defineProps<Props>();

    const { confirmDelete } = useConfirm();
    const dialog = useDialog();
    const bus = useRefreshBus();
    const { can } = useCan();

    const REFRESH_KEY = 'api-clients-table';

    const secretModal = reactive({
        visible: false,
        secret: '',
    });

    function showSecret(plainSecret: string) {
        secretModal.secret = plainSecret;
        secretModal.visible = true;
    }

    function onSecretConfirmed() {
        secretModal.visible = false;
        secretModal.secret = '';
        bus.refresh(REFRESH_KEY);
    }

    function openCreateModal() {
        dialog.open(
            ApiClientForm,
            {
                inDialog: true,
                availableScopes: props.availableScopes,
                onCreated: (plainSecret: string) => {
                    dialog.close();
                    showSecret(plainSecret);
                },
                onCancel: () => dialog.close(),
            },
            trans('sk-api-clients.create'),
            { width: '680px' },
        );
    }

    function openEditDialog(client: ApiClient) {
        dialog.open(
            ApiClientForm,
            {
                clientId: client.id,
                inDialog: true,
                availableScopes: props.availableScopes,
                onSuccess: () => {
                    dialog.close();
                    bus.refresh(REFRESH_KEY);
                },
                onCancel: () => dialog.close(),
            },
            trans('sk-api-clients.edit'),
            { width: '680px' },
        );
    }

    function revokeClient(client: ApiClient) {
        confirmDelete(
            () => {
                router.delete(apiClients.destroy.url(client), {
                    onSuccess: () => bus.refresh(REFRESH_KEY),
                });
            },
            trans('sk-api-clients.revoke_confirm', { name: client.name }),
        );
    }

    const tableBuilder = DB.table<ApiClient>()
        .route(apiClients.dtApi.url())
        .isCard(true)
        .cardTitle('sk-api-clients.title')
        .cardSubtitle('sk-api-clients.subtitle')
        .searchable(true)
        .sortable(true)
        .addColumns(
            DB.column<ApiClient>().label('sk-api-clients.fields.name').key('name'),
            DB.column<ApiClient>()
                .label('sk-api-clients.fields.grant_type')
                .key('grant_types')
                .sortable(false)
                .render((client) => {
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
                                    `<span class="inline-flex items-center rounded-full bg-surface-100 px-2 py-0.5 text-base text-surface-700 dark:bg-surface-800 dark:text-surface-300">${l}</span>`,
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
                .handle((client) => openEditDialog(client)),
            DB.action<ApiClient>()
                .icon('pi pi-ban')
                .severity('danger')
                .tooltip(trans('sk-api-clients.revoke'))
                .visible(() => can('api-clients.delete'))
                .handle((client) => revokeClient(client)),
        );

    if (can('api-clients.create')) {
        tableBuilder.create({
            label: 'sk-api-clients.create',
            onClick: openCreateModal,
        });
    }

    const tableConfig = tableBuilder.build();
</script>

<template>
    <SkDatatable :config="tableConfig" :refresh-key="REFRESH_KEY" />

    <OneTimeSecretModal
        :visible="secretModal.visible"
        :secret="secretModal.secret"
        type="secret"
        @confirm="onSecretConfirmed"
    />
</template>
