<script setup lang="ts">
    import ShareLinkModal from './components/ShareLinkModal.vue';
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import FileManager from '@lvntr/components/FileManager/FileManager.vue';
    import { ref } from 'vue';

    // ── Share modal state ───────────────────────────────────────
    interface ShareTarget {
        mediaId: number;
        fileName: string;
    }

    const shareModalVisible = ref(false);
    const shareTarget = ref<ShareTarget | null>(null);

    function openShareModal(file: { id: number; file_name: string }): void {
        shareTarget.value = { mediaId: file.id, fileName: file.file_name };
        shareModalVisible.value = true;
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
            Aktif paylaşımlar drawer'ı burada `v-if="false"` ile duruyordu; ölü olduğu
            hâlde bundle'a giriyordu, o yüzden import'u ile birlikte kaldırıldı.
            Bileşen ./components/MyShareLinksDrawer.vue içinde duruyor.
            TODO: Backend list endpoint'i (GET /file-manager/share?media_id=X) geldiğinde
            bileşen yeniden import edilir, sessionLinks state'i geri eklenir ve
            FileManager'a "my-links" aksiyonu bağlanır.
        -->
    </AdminLayout>
</template>
