<script setup lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import SampleContentForm from '@/pages/Admin/SampleContents/components/SampleContentForm.vue';
</script>

<template>
    <AdminLayout :title="$t('sample-contents.create_title')" :back-url="true">
        <SampleContentForm />
    </AdminLayout>
</template>
