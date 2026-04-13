<script setup lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import UserForm from '@/pages/Admin/Users/components/UserForm.vue';
    import FileManager from '@lvntr/components/FileManager/FileManager.vue';
    import { TB } from '@lvntr/components/TabBuilder/core';

    interface Props {
        userId: string;
    }

    defineProps<Props>();

    const tabConfig = TB.tabs()
        .addTabs(
            TB.item().key('general').label('admin.users.tabs.general').icon('pi pi-user'),
            TB.item().key('files').label('admin.users.tabs.files').icon('pi pi-folder'),
        )
        .build();
</script>

<template>
    <AdminLayout :title="$t('admin.users.edit')" :subtitle="userId" :back-url="true">
        <SkTabs :config="tabConfig">
            <template #general>
                <UserForm :user-id="userId" />
            </template>

            <template #files>
                <div class="p-2">
                    <FileManager context="user" :context-id="userId" height="650px" />
                </div>
            </template>
        </SkTabs>
    </AdminLayout>
</template>
