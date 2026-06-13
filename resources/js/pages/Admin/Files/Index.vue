<script setup lang="ts">
    import MyShareLinksDrawer from './components/MyShareLinksDrawer.vue';
    import ShareLinkModal from './components/ShareLinkModal.vue';
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import FileManager from '@lvntr/components/FileManager/FileManager.vue';
    import type { ShareLinkResult } from '@/composables/useFileShare';
    import { ref } from 'vue';

    // ── Share modal state ───────────────────────────────────────
    interface ShareTarget {
        mediaId: number;
        fileName: string;
    }

    const shareModalVisible = ref(false);
    const shareTarget = ref<ShareTarget | null>(null);

    /** Session'da üretilen linkler — drawer'a aktarılır. */
    const sessionLinks = ref<ShareLinkResult[]>([]);

    function openShareModal(file: { id: number; file_name: string }): void {
        shareTarget.value = { mediaId: file.id, fileName: file.file_name };
        shareModalVisible.value = true;
    }

    // ── My Links drawer state ───────────────────────────────────
    // TODO: FileManager'a "my-links" aksiyonu eklendiğinde openShareDrawer() çağrılır.
    // Şimdilik drawer yalnızca manuel tetikleme ile açılabilir.
    const drawerVisible = ref(false);
    const drawerMediaId = ref<number>(0);

    function onShareRevoked(tokenHash: string): void {
        sessionLinks.value = sessionLinks.value.filter((l) => l.token_hash !== tokenHash);
    }
</script>

<template>
    <AdminLayout :title="$t('sk-file.title')" :subtitle="$t('sk-file.subtitle')">
        <FileManager
            context="global"
            @share="openShareModal"
        />

        <!-- Paylaşım linki oluşturma modal -->
        <ShareLinkModal
            v-if="shareTarget"
            v-model:visible="shareModalVisible"
            :media-id="shareTarget.mediaId"
            :file-name="shareTarget.fileName"
        />

        <!--
            Aktif paylaşımlar drawer'ı — geçici olarak gizlendi.
            TODO: Backend list endpoint'i (GET /file-manager/share?media_id=X) bu plan
            kapsamı dışındadır (backlog). Endpoint eklendiğinde v-if="false" kaldırılır
            ve FileManager'a "my-links" aksiyonu eklenerek drawer tetiklenir.
        -->
        <MyShareLinksDrawer
            v-if="false"
            v-model:visible="drawerVisible"
            :media-id="drawerMediaId"
            :links="sessionLinks"
            @revoked="onShareRevoked"
        />
    </AdminLayout>
</template>
