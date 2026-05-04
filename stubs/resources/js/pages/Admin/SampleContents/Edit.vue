<script setup lang="ts">
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import SampleContentForm from '@/pages/Admin/SampleContents/components/SampleContentForm.vue';

    interface SampleContentResource {
        id: number;
        title: { translations: Record<string, string>; current: string };
        description: { translations: Record<string, string>; current: string };
        content: { translations: Record<string, string>; current: string };
        user: { id: string; name: string };
        created_at: string;
        updated_at: string;
    }

    interface Props {
        sample_content: SampleContentResource;
    }

    const props = defineProps<Props>();
</script>

<template>
    <AdminLayout :title="$t('sample-contents.edit_title')" :back-url="true">
        <SampleContentForm :resource="props.sample_content" />
    </AdminLayout>
</template>
