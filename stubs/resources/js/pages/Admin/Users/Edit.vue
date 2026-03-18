<script setup lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import UserForm from '@/pages/Admin/Users/components/UserForm.vue';

    interface Props {
        userId: string;
    }

    defineProps<Props>();
</script>

<template>
    <AdminLayout :title="$t('admin.users.edit')" :subtitle="userId" :back-url="true">
        <UserForm :user-id="userId" />
    </AdminLayout>
</template>
