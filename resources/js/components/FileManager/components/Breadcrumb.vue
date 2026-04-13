<script setup lang="ts">
    import Breadcrumb from 'primevue/breadcrumb';
    import { computed } from 'vue';
    import type { FolderSummary } from '../types';

    interface Props {
        trail: FolderSummary[];
        rootLabel?: string;
    }

    const props = defineProps<Props>();
    const emit = defineEmits<{
        (e: 'navigate', folderId: string | null): void;
    }>();

    const home = computed(() => ({
        icon: 'pi pi-home',
        label: props.rootLabel ?? 'Root',
        command: () => emit('navigate', null),
    }));

    const items = computed(() =>
        props.trail.map((folder) => ({
            label: folder.name,
            command: () => emit('navigate', folder.id),
        })),
    );
</script>

<template>
    <Breadcrumb :home="home" :model="items" class="fm-breadcrumb" />
</template>

<style scoped>
    .fm-breadcrumb :deep(.p-breadcrumb) {
        border: none;
        padding: 0.25rem 0.5rem;
        background: transparent;
    }
</style>
