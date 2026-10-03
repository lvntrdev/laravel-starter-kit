<script setup lang="ts">
    import MyShareLinksDrawer from './components/MyShareLinksDrawer.vue';
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
    const drawerVisible = ref(false);

    function openShareModal(file: { id: number; file_name: string }): void {
        shareTarget.value = { mediaId: file.id, fileName: file.file_name };
        shareModalVisible.value = true;
    }

    function openLinksDrawer(): void {
        shareModalVisible.value = false;
        drawerVisible.value = true;
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
            @manage-links="openLinksDrawer"
        />

        <!-- Dosyanın aktif paylaşım linkleri -->
        <MyShareLinksDrawer
            v-if="shareTarget"
            v-model:visible="drawerVisible"
            :media-id="shareTarget.mediaId"
        />
    </AdminLayout>
</template>
