<!-- resources/js/layouts/AppShell.vue -->
<script setup lang="ts">
    import { useSidebar } from '@/composables/useSidebar';

    // AppShell owns ONLY the structural shell + sidebar state plumbing.
    // No page props, no business logic, no flash/toast — those live in the
    // composing layout (e.g. AdminLayout). useSidebar has a single owner here.
    const { isCollapsed, isMobileOpen, isMobile, toggle, closeMobile } = useSidebar();
</script>

<template>
    <div class="admin-layout">
        <!-- Sidebar region -->
        <slot
            name="sidebar"
            :collapsed="isCollapsed"
            :mobile-open="isMobileOpen"
            :is-mobile="isMobile"
            :close-mobile="closeMobile"
        />

        <!-- Main Area -->
        <div
            class="admin-main"
            :class="{
                'admin-main--expanded': !isMobile && !isCollapsed,
                'admin-main--collapsed': !isMobile && isCollapsed,
                'admin-main--mobile': isMobile,
            }"
        >
            <!-- Header region -->
            <slot
                name="header"
                :collapsed="isCollapsed"
                :is-mobile="isMobile"
                :toggle="toggle"
            />

            <!-- Content region -->
            <main class="admin-content">
                <slot />
            </main>

            <!-- Footer region -->
            <slot name="footer" />
        </div>

        <!-- Global overlays region -->
        <slot name="overlays" />
    </div>
</template>
