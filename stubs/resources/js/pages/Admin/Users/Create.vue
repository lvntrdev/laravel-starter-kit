<script setup lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import UserForm from '@/pages/Admin/Users/components/UserForm.vue';
</script>

<template>
    <AdminLayout :title="$t('admin.users.create')" :back-url="true">
        <UserForm />
    </AdminLayout>
</template>
