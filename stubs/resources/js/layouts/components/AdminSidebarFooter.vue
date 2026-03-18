<!-- resources/js/components/Admin/AdminSidebarFooter.vue -->
<script setup lang="ts">
    import { usePage } from '@inertiajs/vue3';

    interface Props {
        collapsed: boolean;
    }

    defineProps<Props>();

    const page = usePage();
    const appName = computed(() => page.props.appName as string);
    const appVersion = computed(() => page.props.appVersion as string);
</script>

<template>
    <div class="admin-sidebar__footer">
        <div class="admin-sidebar__footer-icon">
            <i class="pi pi-box" />
        </div>
        <div class="admin-sidebar__footer-info" :class="collapsed ? 'opacity-0' : 'opacity-100'">
            <span class="admin-sidebar__footer-title"> {{ appName }} </span>
            <span class="admin-sidebar__footer-version"> v{{ appVersion }} </span>
        </div>
    </div>
</template>
