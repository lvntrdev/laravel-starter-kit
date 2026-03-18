<!-- resources/js/components/AppDialog.vue -->
<!-- Global dialog — registered once in AdminLayout.             -->
<!-- Renders whatever component is passed via useDialog().open() -->
<script setup lang="ts">
    import { useDialog } from '@/composables/useDialog';

    const { state, close } = useDialog();
</script>

<template>
    <Dialog
        :visible="state.visible"
        :header="state.header"
        :modal="true"
        :draggable="false"
        :style="{ width: state.width }"
        :breakpoints="{ '768px': '95vw' }"
        @update:visible="(val) => !val && close()"
    >
        <div v-if="state.loading" class="flex justify-center py-8">
            <i class="pi pi-spin pi-spinner text-2xl text-surface-400" />
        </div>

        <component :is="state.component" v-else-if="state.component" v-bind="state.props" />
    </Dialog>
</template>
