<!-- resources/js/components/Admin/AdminFooter.vue -->
<script setup lang="ts">
    import { usePage } from '@inertiajs/vue3';

    const appName = computed(() => usePage().props.appName as string);
    const year = new Date().getFullYear();
</script>

<template>
    <footer class="admin-footer">
        <span>&copy; {{ year }} {{ appName }}</span>
        <span>Laravel + Vue</span>
    </footer>
</template>
