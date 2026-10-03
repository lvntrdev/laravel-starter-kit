<script setup lang="ts">
    /**
     * MyShareLinksDrawer
     *
     * Bir dosyanın sunucudaki aktif paylaşım linklerini listeler. Drawer her
     * açıldığında liste GET /file-manager/share?media_id={id} ile yeniden
     * çekilir; her link kendi satırından iptal edilir ve başarılı iptalde satır
     * yerelde listeden düşer.
     */
    import { useFileShare } from '@/composables/useFileShare';
    import type { IssuedShareLink } from '@/composables/useFileShare';
    import { formatDateTime } from '@lvntr/components/utils/datetime';
    import { trans } from 'laravel-vue-i18n';
    import Button from 'primevue/button';
    import Column from 'primevue/column';
    import DataTable from 'primevue/datatable';
    import Drawer from 'primevue/drawer';
    import { ref, watch } from 'vue';

    interface Props {
        visible: boolean;
        /** Linkleri listelenen ve iptal edilen media kaydının ID'si. */
        mediaId: number;
    }

    const props = defineProps<Props>();

    const emit = defineEmits<{
        'update:visible': [value: boolean];
    }>();

    const { listShares, revokeShare } = useFileShare();

    const links = ref<IssuedShareLink[]>([]);
    const loading = ref(false);
    const revokingTokens = ref<Set<string>>(new Set());

    async function loadLinks(): Promise<void> {
        const mediaId = props.mediaId;
        loading.value = true;
        const result = await listShares(mediaId);
        // A late response for a previously opened file must not overwrite this one.
        if (mediaId !== props.mediaId) return;
        links.value = result ?? [];
        loading.value = false;
    }

    watch(
        () => props.visible,
        (open) => {
            if (open) void loadLinks();
        },
        { immediate: true },
    );

    function formatDate(iso: string): string {
        return formatDateTime(iso, {
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            second: 'numeric',
        });
    }

    async function handleRevoke(link: IssuedShareLink): Promise<void> {
        revokingTokens.value.add(link.token_hash);
        const success = await revokeShare(props.mediaId, link.token_hash);
        revokingTokens.value.delete(link.token_hash);

        if (success) {
            links.value = links.value.filter((l) => l.token_hash !== link.token_hash);
        }
    }
</script>

<template>
    <Drawer
        :visible="visible"
        :header="trans('sk-file-manager.share.drawer_title')"
        position="right"
        :style="{ width: '36rem' }"
        @update:visible="emit('update:visible', $event)"
    >
        <!-- Boş durum -->
        <div
            v-if="!loading && links.length === 0"
            class="flex flex-col items-center justify-center gap-3 py-16 text-center"
        >
            <i class="pi pi-share-alt text-5xl text-surface-300 dark:text-surface-600" />
            <p class="text-base text-surface-500 dark:text-surface-400">
                {{ trans('sk-file-manager.share.drawer_empty') }}
            </p>
        </div>

        <!-- Link tablosu -->
        <DataTable
            v-else
            :value="links"
            :loading="loading"
            data-key="token_hash"
            size="small"
            striped-rows
            class="w-full"
        >
            <Column
                :header="trans('sk-file-manager.share.column_created')"
                style="white-space: nowrap"
            >
                <template #body="{ data }: { data: IssuedShareLink }">
                    {{ formatDate(data.created_at) }}
                </template>
            </Column>

            <Column
                :header="trans('sk-file-manager.share.column_expires')"
                style="white-space: nowrap"
            >
                <template #body="{ data }: { data: IssuedShareLink }">
                    {{ formatDate(data.expires_at) }}
                </template>
            </Column>

            <Column style="width: 6rem; text-align: right">
                <template #body="{ data }: { data: IssuedShareLink }">
                    <Button
                        :label="trans('sk-file-manager.share.revoke')"
                        icon="pi pi-ban"
                        severity="danger"
                        outlined
                        size="small"
                        :loading="revokingTokens.has(data.token_hash)"
                        :disabled="revokingTokens.has(data.token_hash)"
                        @click="handleRevoke(data)"
                    />
                </template>
            </Column>
        </DataTable>
    </Drawer>
</template>
