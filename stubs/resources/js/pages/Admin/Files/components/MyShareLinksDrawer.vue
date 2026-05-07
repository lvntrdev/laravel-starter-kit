<script setup lang="ts">
    /**
     * MyShareLinksDrawer
     *
     * Kullanıcının oluşturduğu aktif paylaşım linklerini listeler ve
     * revoke edilmesine olanak tanır.
     *
     * TODO: Aktif paylaşım listesi için backend endpoint henüz oluşturulmamış
     * (T4 scope'u dışındaydı). Endpoint eklendiğinde aşağıdaki loadLinks()
     * fonksiyonunu aktif edin ve emptyState'i kaldırın.
     * Beklenen endpoint: GET /file-manager/share?media_id={id}
     * Beklenen response: { data: [{ url, expires_at, token_hash }] }
     *
     * Şu anda yalnızca session-local state tutulmaktadır:
     * Drawer açılmadan önce ShareLinkModal'dan oluşturulan linkler
     * `links` prop'u üzerinden buraya aktarılır.
     */
    import { useFileShare } from '@/composables/useFileShare';
    import type { ShareLinkResult } from '@/composables/useFileShare';
    import { trans } from 'laravel-vue-i18n';
    import Button from 'primevue/button';
    import Column from 'primevue/column';
    import DataTable from 'primevue/datatable';
    import Drawer from 'primevue/drawer';
    import Tag from 'primevue/tag';
    import { ref } from 'vue';

    interface Props {
        visible: boolean;
        /** Session'da üretilmiş linkler. Backend listesi gelene kadar buradan beslenir. */
        links: ShareLinkResult[];
        /** Her linkin ait olduğu media ID'si. Revoke için gerekli. */
        mediaId: number;
    }

    const props = defineProps<Props>();

    const emit = defineEmits<{
        'update:visible': [value: boolean];
        /** Revoke başarılıysa token_hash ile emit edilir, parent state'i günceller. */
        revoked: [tokenHash: string];
    }>();

    const { revokeShare } = useFileShare();

    const revokingTokens = ref<Set<string>>(new Set());

    function isExpired(expiresAt: string): boolean {
        try {
            return new Date(expiresAt) < new Date();
        } catch {
            return false;
        }
    }

    function formatExpiry(iso: string): string {
        try {
            return new Date(iso).toLocaleString();
        } catch {
            return iso;
        }
    }

    async function handleRevoke(link: ShareLinkResult): Promise<void> {
        revokingTokens.value.add(link.token_hash);
        const success = await revokeShare(props.mediaId, link.token_hash);
        revokingTokens.value.delete(link.token_hash);

        if (success) {
            emit('revoked', link.token_hash);
        }
    }

    async function copyLink(url: string): Promise<void> {
        try {
            await navigator.clipboard.writeText(url);
        } catch {
            // sessiz fail — kullanıcı URL'yi elle seçebilir
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
            v-if="links.length === 0"
            class="flex flex-col items-center justify-center gap-3 py-16 text-center"
        >
            <i class="pi pi-share-alt text-5xl text-surface-300 dark:text-surface-600" />
            <p class="text-base text-surface-500 dark:text-surface-400">
                {{ trans('sk-file-manager.share.drawer_empty') }}
            </p>
            <!--
                TODO: Backend aktif paylaşım listesi endpoint'i (GET /file-manager/share?media_id=X)
                eklendiğinde bu boş durum mesajı yalnızca gerçekten boş liste için gösterilir.
            -->
        </div>

        <!-- Link tablosu -->
        <DataTable
            v-else
            :value="links"
            size="small"
            striped-rows
            class="w-full"
        >
            <!-- URL sütunu -->
            <Column
                :header="trans('sk-file-manager.share.column_link')"
                class="min-w-0"
            >
                <template #body="{ data }: { data: ShareLinkResult }">
                    <div class="flex items-center gap-2 min-w-0">
                        <span
                            class="block truncate font-mono text-base text-surface-700 dark:text-surface-200"
                            :title="data.url"
                        >
                            {{ data.url }}
                        </span>
                        <Button
                            icon="pi pi-copy"
                            severity="secondary"
                            text
                            size="small"
                            :aria-label="trans('sk-file-manager.share.copy')"
                            @click="copyLink(data.url)"
                        />
                    </div>
                </template>
            </Column>

            <!-- Sona erme sütunu -->
            <Column
                :header="trans('sk-file-manager.share.column_expires')"
                style="width: 11rem; white-space: nowrap"
            >
                <template #body="{ data }: { data: ShareLinkResult }">
                    <Tag
                        :severity="isExpired(data.expires_at) ? 'danger' : 'success'"
                        :value="isExpired(data.expires_at)
                            ? trans('sk-file-manager.share.status_expired')
                            : formatExpiry(data.expires_at)"
                        class="text-base"
                    />
                </template>
            </Column>

            <!-- Revoke sütunu -->
            <Column style="width: 6rem; text-align: right">
                <template #body="{ data }: { data: ShareLinkResult }">
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

        <!--
            TODO: Backend endpoint eklenince aşağıdaki notu kaldır.
            Şu anda yalnızca bu oturumda ShareLinkModal üzerinden üretilen linkler
            burada gösterilmektedir. Sayfa yenilenirse liste sıfırlanır.
        -->
        <p
            v-if="links.length > 0"
            class="mt-4 text-base text-surface-400 dark:text-surface-500"
        >
            {{ trans('sk-file-manager.share.drawer_session_note') }}
        </p>
    </Drawer>
</template>
