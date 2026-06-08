<script setup lang="ts">
    import { useCan } from '@/composables/useCan';
    import { useConfirm } from '@/composables/useConfirm';
    import { useDialog } from '@/composables/useDialog';
    import { useRefreshBus } from '@/composables/useRefreshBus';
    import { useDatatableSelection } from '@/composables/useDatatableSelection';
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import type { User } from '@/types';
    import { router } from '@inertiajs/vue3';
    import { DB } from '@lvntr/components/DatatableBuilder/core';
    import { trans } from 'laravel-vue-i18n';

    import UserForm from '@/pages/Admin/Users/components/UserForm.vue';
    import users from '@/routes/users';
    import { Button } from 'primevue';

    interface Props {
        roleOptions: { label: string; value: string }[];
    }

    const props = defineProps<Props>();

    const { confirmDelete, confirmAction } = useConfirm();
    const dialog = useDialog();
    const bus = useRefreshBus();
    const { can } = useCan();

    const REFRESH_KEY = 'users-table';

    // ── Bulk Selection ─────────────────────────────────────────────────────────────

    const selection = useDatatableSelection({
        bulkUrl: users.bulk.url(),
        idKey: 'id',
        onSuccess: () => bus.refresh(REFRESH_KEY),
    });

    // ── Create dialog ─────────────────────────────────────────────────────────────

    function openCreateDialog() {
        dialog.open(UserForm, { inDialog: true, roleOptions: props.roleOptions }, trans('sk-user.create'), {
            icon: 'pi pi-user-plus',
            subtitle: 'Yeni kullanıcı oluştur',
            refreshKey: REFRESH_KEY,
        });
    }

    // ── Edit dialog ───────────────────────────────────────────────────────────────

    function openEditDialog(userId: string) {
        dialog.open(UserForm, { userId, inDialog: true, roleOptions: props.roleOptions }, trans('sk-user.edit'), {
            icon: 'pi pi-user-edit',
            subtitle: 'Kullanıcı bilgilerini güncelle',
            refreshKey: REFRESH_KEY,
        });
    }

    // ── Delete ────────────────────────────────────────────────────────────────────

    function deleteUser(user: User) {
        confirmDelete(
            () => {
                router.delete(users.destroy.url(user), {
                    onSuccess: () => bus.refresh('users-table'),
                });
            },
            trans('sk-user.delete_confirm', { name: user.full_name }),
        );
    }

    // ── Bulk Delete ───────────────────────────────────────────────────────────────

    /** Filter snapshot — reflects the table's active filters for cross-page selection. */
    const activeFilterSnapshot = ref<Record<string, unknown>>({});

    function confirmBulkDelete(totalFiltered: number) {
        if (!selection.hasSelection.value) return;

        const isAllMode = selection.isAllFilteredMode.value;
        const count = selection.selectedCount.value;

        const message = isAllMode
            ? trans('sk-datatable.bulk_delete_confirm_all', { total: String(totalFiltered) })
            : trans('sk-datatable.bulk_delete_confirm', { count: String(count) });

        confirmAction({
            header: trans('sk-datatable.bulk_delete_header'),
            message,
            icon: 'pi pi-trash',
            acceptLabel: trans('sk-button.delete'),
            acceptClass: 'p-button-danger',
            onAccept: () => {
                selection.executeBulkAction('delete', activeFilterSnapshot.value);
            },
        });
    }

    // ── SkDatatable ─────────────────────────────────────────────────────────────────

    const tableConfig = DB.table<User>()
        .route(users.dtApi.url())
        // .searchable(true)
        .sortable(true)
        // .isCard(false)
        // .pagination(true)
        // .create({ onClick: openCreateDialog })
        .addColumns(
            DB.column<User>().label('sk-common.full_name').key('full_name'),
            DB.column<User>().key('email'),
            DB.column<User>().label('sk-common.role').key('role'),
            DB.column<User>().key('status').tag('definition').tagKey('userStatus').tagOutlined(),
            DB.column<User>().label('sk-common.created_at').key('created_at'),
        )
        .addFilters(
            DB.filter().key('status').definitionOptions('userStatus'),
            DB.filter().key('role').label('sk-common.role').type('select').options(props.roleOptions),
        )
        .addActions(
            DB.action<User>()
                .icon('pi pi-pencil')
                .severity('warn')
                .label('sk-button.edit')
                .visible(() => can('users.update'))
                .handle((user) => openEditDialog(user.id)),
            DB.action<User>()
                .icon('pi pi-trash')
                .severity('danger')
                .label('sk-button.delete')
                .visible(() => can('users.delete'))
                .handle((user) => deleteUser(user)),
        )
        .build();

    // ── Table ref for total count access ──────────────────────────────────────────
    const tableRef = ref();
    const totalFiltered = ref(0);

    function onTableLoad(_data: unknown[], total: number) {
        // total = meta.total from the API response (all filtered records, not just current page).
        totalFiltered.value = total;

        // Update filter snapshot from the table's current URL params.
        // SkDatatable syncs state to URL on every load, so we parse it here.
        const params = new URLSearchParams(window.location.search);
        const snapshot: Record<string, unknown> = {};
        params.forEach((val, key) => {
            snapshot[key] = val;
        });
        activeFilterSnapshot.value = snapshot;
    }
</script>

<template>
    <AdminLayout :title="$t('sk-menu.users')" :subtitle="$t('sk-user.subtitle')">
        <template v-if="can('users.create')" #page-actions>
            <Button :label="$t('sk-user.create')" icon="pi pi-user-plus" @click="openCreateDialog" />
        </template>

        <SkDatatable
            ref="tableRef"
            :config="tableConfig"
            :refresh-key="REFRESH_KEY"
            :selection="selection"
            @load="onTableLoad"
        >
            <!-- Bulk action toolbar — shown only when rows are selected -->
            <template v-if="selection.hasSelection.value || selection.isAllFilteredMode.value" #toolbar>
                <div class="sk-dt-bulk-toolbar">
                    <!-- Selected count label -->
                    <span class="sk-dt-bulk-toolbar__count">
                        <template v-if="selection.isAllFilteredMode.value">
                            {{
                                $t('sk-datatable.bulk_selected_all_filtered', {
                                    count: String(selection.selectedCount.value),
                                })
                            }}
                        </template>
                        <template v-else>
                            {{ $t('sk-datatable.bulk_selected', { count: String(selection.selectedCount.value) }) }}
                        </template>
                    </span>

                    <!-- Select all filtered (cross-page) -->
                    <Button
                        v-if="!selection.isAllFilteredMode.value && totalFiltered > selection.selectedCount.value"
                        :label="$t('sk-datatable.bulk_select_all_filtered', { total: String(totalFiltered) })"
                        size="small"
                        severity="secondary"
                        variant="text"
                        @click="selection.selectAllFiltered()"
                    />

                    <!-- Bulk delete -->
                    <Button
                        v-if="can('users.delete')"
                        :label="$t('sk-datatable.bulk_delete')"
                        icon="pi pi-trash"
                        size="small"
                        severity="danger"
                        :loading="selection.submitting.value"
                        @click="confirmBulkDelete(totalFiltered)"
                    />

                    <!-- Clear selection -->
                    <Button
                        :label="$t('sk-datatable.bulk_clear_selection')"
                        size="small"
                        severity="secondary"
                        variant="outlined"
                        icon="pi pi-times"
                        @click="selection.clearSelection()"
                    />
                </div>
            </template>
        </SkDatatable>
    </AdminLayout>
</template>
