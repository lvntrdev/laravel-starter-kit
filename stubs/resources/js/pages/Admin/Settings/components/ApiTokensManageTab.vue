<script setup lang="ts">
    import { useCan } from '@/composables/useCan';
    import { useConfirm } from '@/composables/useConfirm';
    import { useRefreshBus } from '@/composables/useRefreshBus';
    import { router } from '@inertiajs/vue3';
    import { DB } from '@lvntr/components/DatatableBuilder/core';
    import { trans } from 'laravel-vue-i18n';
    import { reactive, ref } from 'vue';

    import OneTimeSecretModal from '@/pages/Admin/ApiClients/components/OneTimeSecretModal.vue';
    import CreateTokenModal from '@/pages/Admin/ApiTokens/components/CreateTokenModal.vue';
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
    const bus = useRefreshBus();
    const { can } = useCan();

    const REFRESH_KEY = 'api-tokens-table';

    const createModal = ref(false);

    function openCreateModal() {
        createModal.value = true;
    }

    function onCreateModalClose() {
        createModal.value = false;
    }

    const tokenModal = reactive({
        visible: false,
        token: '',
    });

    function onTokenCreated(accessToken: string) {
        createModal.value = false;
        tokenModal.token = accessToken;
        tokenModal.visible = true;
    }

    function onTokenModalConfirmed() {
        tokenModal.visible = false;
        tokenModal.token = '';
        bus.refresh(REFRESH_KEY);
    }

    function revokeToken(token: ApiToken) {
        confirmDelete(
            () => {
                router.delete(apiTokens.destroy.url(token), {
                    onSuccess: () => bus.refresh(REFRESH_KEY),
                });
            },
            trans('sk-api-tokens.revoke_confirm'),
        );
    }

    const tableBuilder = DB.table<ApiToken>()
        .route(apiTokens.dtApi.url())
        .isCard(true)
        .cardTitle('sk-api-tokens.title')
        .cardSubtitle('sk-api-tokens.subtitle')
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
                .handle((token) => revokeToken(token)),
        );

    if (can('api-tokens.create')) {
        tableBuilder.create({
            label: 'sk-api-tokens.create',
            onClick: openCreateModal,
        });
    }

    const tableConfig = tableBuilder.build();
</script>

<template>
    <SkDatatable :config="tableConfig" :refresh-key="REFRESH_KEY" />

    <CreateTokenModal
        :visible="createModal"
        :available-scopes="props.availableScopes"
        @close="onCreateModalClose"
        @created="onTokenCreated"
    />

    <OneTimeSecretModal
        :visible="tokenModal.visible"
        :secret="tokenModal.token"
        type="token"
        @confirm="onTokenModalConfirmed"
    />
</template>
