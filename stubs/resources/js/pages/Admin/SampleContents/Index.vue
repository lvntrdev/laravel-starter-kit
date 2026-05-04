<script setup lang="ts">
    import { DB } from '@lvntr/components/DatatableBuilder/core';
    import { useCan } from '@/composables/useCan';
    import { useConfirm } from '@/composables/useConfirm';
    import { useDialog } from '@/composables/useDialog';
    import { useRefreshBus } from '@/composables/useRefreshBus';
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import type { SampleContent } from '@/types';
    import { router } from '@inertiajs/vue3';
    import { trans } from 'laravel-vue-i18n';
    import { Button } from 'primevue';

    import SampleContentForm from '@/pages/Admin/SampleContents/components/SampleContentForm.vue';
    import sampleContents from '@/routes/sample-contents';

    const { confirmDelete } = useConfirm();
    const dialog = useDialog();
    const bus = useRefreshBus();
    const { can } = useCan();

    const REFRESH_KEY = 'sample_contents-table';

    // ── Create dialog ─────────────────────────────────────────────────────────────

    function openCreateDialog() {
        dialog.open(SampleContentForm, { inDialog: true }, trans('sample-contents.actions.create'), {
            refreshKey: REFRESH_KEY,
        });
    }

    // ── Edit dialog ───────────────────────────────────────────────────────────────

    function openEditDialog(sampleContent: SampleContent) {
        dialog.open(
            SampleContentForm,
            { resource: sampleContent, inDialog: true },
            trans('sample-contents.edit_title'),
            { refreshKey: REFRESH_KEY },
        );
    }

    // ── Delete ────────────────────────────────────────────────────────────────────

    function deleteSampleContent(sampleContent: SampleContent) {
        confirmDelete(() => {
            router.delete(sampleContents.destroy.url(sampleContent), {
                onSuccess: () => bus.refresh(REFRESH_KEY),
            });
        }, trans('sample-contents.messages.delete_confirm'));
    }

    // ── SkDatatable ───────────────────────────────────────────────────────────────

    const tableConfig = DB.table<SampleContent>()
        .route(sampleContents.dtApi.url())
        .sortable(true)
        .addColumns(
            DB.column<SampleContent>()
                .label(trans('sample-contents.fields.title'))
                .key('title')
                .render((row, escape) => escape(row.title.current)),
            DB.column<SampleContent>()
                .label(trans('sample-contents.fields.user'))
                .key('user')
                .sortable(false)
                .render((row, escape) => escape(row.user.name)),
            DB.column<SampleContent>()
                .label(trans('sample-contents.fields.created_at'))
                .key('created_at')
                .render((row) =>
                    new Date(row.created_at).toLocaleDateString('tr-TR', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                    }),
                ),
        )
        .addActions(
            DB.action<SampleContent>()
                .icon('pi pi-pencil')
                .severity('warn')
                .tooltip(trans('sample-contents.actions.edit'))
                .visible(() => can('sample-contents.update'))
                .handle((sampleContent) => openEditDialog(sampleContent)),
            DB.action<SampleContent>()
                .icon('pi pi-trash')
                .severity('danger')
                .tooltip(trans('sample-contents.actions.delete'))
                .visible(() => can('sample-contents.delete'))
                .handle((sampleContent) => deleteSampleContent(sampleContent)),
        )
        .build();
</script>

<template>
    <AdminLayout :title="$t('sample-contents.list_title')">
        <template v-if="can('sample-contents.create')" #page-actions>
            <Button :label="$t('sample-contents.actions.create')" icon="pi pi-plus" @click="openCreateDialog" />
        </template>
        <SkDatatable :config="tableConfig" :refresh-key="REFRESH_KEY" />
    </AdminLayout>
</template>
