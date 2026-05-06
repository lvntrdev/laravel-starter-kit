<script setup lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import FileManager from '@lvntr/components/FileManager/FileManager.vue';
</script>

<template>
    <AdminLayout :title="$t('sk-file.title')" :subtitle="$t('sk-file.subtitle')">
        <FileManager context="global" />
    </AdminLayout>
</template>
