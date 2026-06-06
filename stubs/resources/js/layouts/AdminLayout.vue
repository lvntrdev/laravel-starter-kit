<!-- resources/js/layouts/AdminLayout.vue -->
<script setup lang="ts">
    import { useDarkMode } from '@/composables/useDarkMode';
    import { useFlash } from '@/composables/useFlash';
    import AppShell from '@/layouts/AppShell.vue';
    import AdminFooter from '@/layouts/components/AdminFooter.vue';
    import AdminHeader from '@/layouts/components/AdminHeader.vue';
    import AdminPageHeader from '@/layouts/components/AdminPageHeader.vue';
    import AdminSidebar from '@/layouts/components/AdminSidebar.vue';
    import { Head, router } from '@inertiajs/vue3';
    import AppDialog from '@lvntr/components/ui/AppDialog.vue';
    import ConfirmDialogComponent from '@lvntr/components/ui/ConfirmDialogComponent.vue';
    import ImageLightbox from '@lvntr/components/ui/ImageLightbox.vue';
    import { trans } from 'laravel-vue-i18n';
    import { useToast } from 'primevue/usetoast';

    interface Props {
        title?: string;
        subtitle?: string;
        backUrl?: string | boolean;
    }

    withDefaults(defineProps<Props>(), {
        title: '',
        subtitle: '',
        backUrl: false,
    });

    const { isDark, toggleDark } = useDarkMode();
    const { flash } = useFlash();
    const toast = useToast();

    const removeFinishListener = router.on('finish', () => {
        if (flash.value.success) {
            toast.add({
                severity: 'success',
                summary: trans('sk-layout.success'),
                detail: flash.value.success,
                group: 'bc',
                life: 4000,
            });
        }
        if (flash.value.error) {
            toast.add({
                severity: 'error',
                summary: trans('sk-layout.error'),
                detail: flash.value.error,
                group: 'bc',
                life: 6000,
            });
        }
        if (flash.value.warning) {
            toast.add({
                severity: 'warn',
                summary: trans('sk-layout.warning'),
                detail: flash.value.warning,
                group: 'bc',
                life: 5000,
            });
        }
        if (flash.value.info) {
            toast.add({
                severity: 'info',
                summary: trans('sk-layout.info'),
                detail: flash.value.info,
                group: 'bc',
                life: 4000,
            });
        }
    });

    onUnmounted(() => {
        removeFinishListener();
    });
</script>

<template>
    <Head :title="title" />
    <AppShell>
        <!-- Sidebar -->
        <template #sidebar="{ collapsed, mobileOpen, isMobile, closeMobile }">
            <AdminSidebar
                :collapsed="collapsed"
                :mobile-open="mobileOpen"
                :is-mobile="isMobile"
                @close-mobile="closeMobile"
            />
        </template>

        <!-- Header -->
        <template #header="{ collapsed, isMobile, toggle }">
            <AdminHeader
                :collapsed="collapsed"
                :is-mobile="isMobile"
                :is-dark="isDark"
                @toggle-sidebar="toggle"
                @toggle-dark="toggleDark"
            />
        </template>

        <!-- Content -->
        <AdminPageHeader :title="title" :subtitle="subtitle" :back-url="backUrl">
            <template #actions>
                <slot name="page-actions" />
            </template>
        </AdminPageHeader>

        <slot />

        <!-- Footer -->
        <template #footer>
            <AdminFooter />
        </template>

        <!-- Global Overlays -->
        <template #overlays>
            <ConfirmDialogComponent />
            <ToastComponent />
            <AppDialog />
            <ImageLightbox />
        </template>
    </AppShell>
</template>
