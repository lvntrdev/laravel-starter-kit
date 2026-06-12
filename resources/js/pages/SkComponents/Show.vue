<script setup lang="ts">
    import { Head } from '@inertiajs/vue3';
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import { TB } from '@lvntr/components/TabBuilder/core';
    import ButtonShowcase from './components/ButtonShowcase.vue';
    import FormShowcase from './components/FormShowcase.vue';
    import MessageShowcase from './components/MessageShowcase.vue';
    import TagShowcase from './components/TagShowcase.vue';
    import ToastShowcase from './components/ToastShowcase.vue';

    // Vendor-resident developer showcase (system_admin only) — this page and its
    // tab components ship inside the package and are never published. The tab
    // components keep the "XShowcase" naming so no filename ever matches a
    // component it renders (<Tag>, <Button>, …) — that collision makes
    // unplugin-vue-components treat the usage as a recursive self-reference.

    const tabConfig = TB.tabs()
        .vertical()
        .addTabs(
            TB.item()
                .key('tag')
                .label('sk-menu.tag')
                .description('sk-component.tag.subtitle')
                .icon('pi pi-tag')
                .iconColor('blue'),
            TB.item()
                .key('buttons')
                .label('sk-menu.buttons')
                .description('sk-component.button.subtitle')
                .icon('pi pi-stop')
                .iconColor('indigo'),
            TB.item()
                .key('messages')
                .label('sk-menu.message')
                .description('sk-component.message.subtitle')
                .icon('pi pi-comment')
                .iconColor('emerald'),
            TB.item()
                .key('toast')
                .label('sk-menu.toast')
                .description('sk-component.toast.subtitle')
                .icon('pi pi-bell')
                .iconColor('amber'),
            TB.item()
                .key('forms')
                .label('sk-menu.forms')
                .description('sk-component.form.subtitle')
                .icon('pi pi-pencil')
                .iconColor('purple'),
        )
        .build();
</script>

<template>
    <Head :title="$t('sk-menu.components')" />

    <AdminLayout :title="$t('sk-menu.components')" :subtitle="$t('sk-component.subtitle')">
        <SkTabs :config="tabConfig">
            <template #tag>
                <TagShowcase />
            </template>
            <template #buttons>
                <ButtonShowcase />
            </template>
            <template #messages>
                <MessageShowcase />
            </template>
            <template #toast>
                <ToastShowcase />
            </template>
            <template #forms>
                <FormShowcase />
            </template>
        </SkTabs>
    </AdminLayout>
</template>
