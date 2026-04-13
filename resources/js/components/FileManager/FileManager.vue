<script setup lang="ts">
    import { useConfirm } from '@/composables/useConfirm';
    import { trans } from 'laravel-vue-i18n';
    import Button from 'primevue/button';
    import ContextMenu from 'primevue/contextmenu';
    import Dialog from 'primevue/dialog';
    import InputText from 'primevue/inputtext';
    import ProgressSpinner from 'primevue/progressspinner';
    import Select from 'primevue/select';
    import { useToast } from 'primevue/usetoast';
    import { computed, onMounted, ref } from 'vue';
    import Breadcrumb from './components/Breadcrumb.vue';
    import FileGrid from './components/FileGrid.vue';
    import FolderTree from './components/FolderTree.vue';
    import { useFileManager } from './composables/useFileManager';
    import type { FileItem, FileManagerProps, FolderSummary, SelectionKey, SortKey } from './types';

    const props = withDefaults(defineProps<FileManagerProps>(), {
        contextId: null,
        readonly: false,
        height: '600px',
    });

    const toast = useToast();
    const { confirmDelete } = useConfirm();

    const fm = useFileManager({ context: props.context, contextId: props.contextId });

    onMounted(async () => {
        await fm.loadTree();
        await fm.loadContents(null);
    });

    // ── Sort ─────────────────────────────────────────────────────
    const sortOptions = computed(() => [
        { label: trans('file-manager.labels.sort_name'), value: 'name' as SortKey },
        { label: trans('file-manager.labels.sort_size'), value: 'size' as SortKey },
        { label: trans('file-manager.labels.sort_date'), value: 'date' as SortKey },
    ]);

    function onSortChange(value: SortKey): void {
        fm.setSort(value, fm.direction.value);
    }

    function toggleSortDir(): void {
        fm.toggleSortDirection();
    }

    // ── Stats ────────────────────────────────────────────────────
    function humanSize(bytes: number): string {
        if (!bytes) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / 1024 ** i;
        return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
    }

    // ── New folder / rename dialogs ──────────────────────────────
    const showNewFolder = ref(false);
    const newFolderName = ref('');

    function openNewFolder(): void {
        newFolderName.value = '';
        showNewFolder.value = true;
    }

    async function submitNewFolder(): Promise<void> {
        const name = newFolderName.value.trim();
        if (!name) return;
        try {
            await fm.createFolder(name);
            showNewFolder.value = false;
            toast.add({ severity: 'success', summary: '', detail: trans('file-manager.folder_created'), life: 2500 });
        } catch {
            /* handled by useApi */
        }
    }

    const showRename = ref(false);
    const renameTarget = ref<FolderSummary | null>(null);
    const renameValue = ref('');

    function openRename(folder: FolderSummary): void {
        renameTarget.value = folder;
        renameValue.value = folder.name;
        showRename.value = true;
    }

    async function submitRename(): Promise<void> {
        if (!renameTarget.value) return;
        const name = renameValue.value.trim();
        if (!name || name === renameTarget.value.name) {
            showRename.value = false;
            return;
        }
        try {
            await fm.renameFolder(renameTarget.value.id, name);
            showRename.value = false;
            toast.add({ severity: 'success', summary: '', detail: trans('file-manager.folder_renamed'), life: 2500 });
        } catch {
            /* handled */
        }
    }

    // ── Context menus ────────────────────────────────────────────
    const folderMenu = ref<InstanceType<typeof ContextMenu> | null>(null);
    const fileMenu = ref<InstanceType<typeof ContextMenu> | null>(null);
    const emptyMenu = ref<InstanceType<typeof ContextMenu> | null>(null);
    const contextFolder = ref<FolderSummary | null>(null);
    const contextFile = ref<FileItem | null>(null);

    const bulkActive = computed(() => fm.selectionCount.value > 1);

    const folderMenuItems = computed(() => {
        const multi = bulkActive.value && contextFolder.value && fm.isSelected('folder', contextFolder.value.id);
        return [
            {
                label: trans('file-manager.labels.open'),
                icon: 'pi pi-folder-open',
                disabled: multi,
                command: () => contextFolder.value && fm.loadContents(contextFolder.value.id),
            },
            {
                label: trans('file-manager.labels.rename'),
                icon: 'pi pi-pencil',
                disabled: props.readonly || multi,
                command: () => contextFolder.value && openRename(contextFolder.value),
            },
            {
                label: multi
                    ? trans('file-manager.labels.delete_selected') + ` (${fm.selectionCount.value})`
                    : trans('file-manager.labels.delete'),
                icon: 'pi pi-trash',
                disabled: props.readonly,
                command: () => {
                    if (multi) {
                        confirmBulkDelete();
                    } else if (contextFolder.value) {
                        confirmDeleteFolder(contextFolder.value);
                    }
                },
            },
        ];
    });

    const fileMenuItems = computed(() => {
        const multi = bulkActive.value && contextFile.value && fm.isSelected('file', contextFile.value.id);
        return [
            {
                label: trans('file-manager.labels.open'),
                icon: 'pi pi-external-link',
                disabled: multi,
                command: () => contextFile.value && window.open(contextFile.value.url, '_blank'),
            },
            {
                label: trans('file-manager.labels.download'),
                icon: 'pi pi-download',
                disabled: multi,
                command: () => contextFile.value && downloadFile(contextFile.value),
            },
            {
                label: multi
                    ? trans('file-manager.labels.delete_selected') + ` (${fm.selectionCount.value})`
                    : trans('file-manager.labels.delete'),
                icon: 'pi pi-trash',
                disabled: props.readonly,
                command: () => {
                    if (multi) {
                        confirmBulkDelete();
                    } else if (contextFile.value) {
                        confirmDeleteFile(contextFile.value);
                    }
                },
            },
        ];
    });

    function showFolderMenu(event: MouseEvent, folder: FolderSummary): void {
        contextFolder.value = folder;
        if (!fm.isSelected('folder', folder.id)) {
            fm.setSelection([`folder:${folder.id}` as SelectionKey]);
        }
        folderMenu.value?.show(event);
    }

    function showFileMenu(event: MouseEvent, file: FileItem): void {
        contextFile.value = file;
        if (!fm.isSelected('file', file.id)) {
            fm.setSelection([`file:${file.id}` as SelectionKey]);
        }
        fileMenu.value?.show(event);
    }

    const emptyMenuItems = computed(() => [
        {
            label: trans('file-manager.labels.new_folder'),
            icon: 'pi pi-folder-plus',
            disabled: props.readonly,
            command: () => openNewFolder(),
        },
        {
            label: trans('file-manager.labels.upload'),
            icon: 'pi pi-upload',
            disabled: props.readonly || uploading.value,
            command: () => triggerUpload(),
        },
        { separator: true },
        {
            label: trans('file-manager.labels.select_all'),
            icon: 'pi pi-check-square',
            disabled: fm.contents.folders.length + fm.contents.files.length === 0,
            command: () => fm.selectAll(),
        },
        {
            label: trans('file-manager.labels.refresh'),
            icon: 'pi pi-refresh',
            command: () => fm.refresh(),
        },
    ]);

    function showEmptyMenu(event: MouseEvent): void {
        emptyMenu.value?.show(event);
    }

    function confirmDeleteFolder(folder: FolderSummary): void {
        confirmDelete(async () => {
            await fm.deleteFolder(folder.id);
            toast.add({ severity: 'success', summary: '', detail: trans('file-manager.folder_deleted'), life: 2500 });
        });
    }

    function confirmDeleteFile(file: FileItem): void {
        confirmDelete(async () => {
            await fm.deleteFile(file.id);
            toast.add({ severity: 'success', summary: '', detail: trans('file-manager.file_deleted'), life: 2500 });
        });
    }

    function confirmBulkDelete(): void {
        if (fm.selectionCount.value === 0) return;
        confirmDelete(async () => {
            await fm.bulkDelete();
            toast.add({ severity: 'success', summary: '', detail: trans('file-manager.bulk_deleted'), life: 2500 });
        });
    }

    function downloadFile(file: FileItem): void {
        const params = new URLSearchParams({ context: props.context });
        if (props.contextId) params.set('context_id', props.contextId);
        window.location.href = `/file-manager/files/${file.id}/download?${params.toString()}`;
    }

    // ── Selection helpers ────────────────────────────────────────
    function onToggleSelect(type: 'folder' | 'file', id: string | number, event: MouseEvent): void {
        if (event.shiftKey || event.ctrlKey || event.metaKey) {
            fm.toggleSelect(type, id);
        } else {
            // plain click → single select
            fm.setSelection([`${type}:${String(id)}` as SelectionKey]);
        }
    }

    // ── Upload ───────────────────────────────────────────────────
    const fileInput = ref<HTMLInputElement | null>(null);
    const uploading = ref(false);
    const isDropping = ref(false);

    function triggerUpload(): void {
        fileInput.value?.click();
    }

    async function handleFiles(fileList: FileList | File[] | null): Promise<void> {
        if (!fileList || (fileList as FileList).length === 0) return;
        uploading.value = true;
        try {
            await fm.uploadFiles(fileList);
            toast.add({ severity: 'success', summary: '', detail: trans('file-manager.files_uploaded'), life: 2500 });
        } catch (e) {
            toast.add({
                severity: 'error',
                summary: '',
                detail: (e as Error).message ?? 'Upload failed',
                life: 4000,
            });
        } finally {
            uploading.value = false;
            if (fileInput.value) fileInput.value.value = '';
        }
    }

    function onFileChange(event: Event): void {
        handleFiles((event.target as HTMLInputElement).files);
    }

    function onDrop(event: DragEvent): void {
        event.preventDefault();
        isDropping.value = false;
        handleFiles(event.dataTransfer?.files ?? null);
    }

    function onDragOver(event: DragEvent): void {
        event.preventDefault();
        isDropping.value = true;
    }

    function onDragLeave(): void {
        isDropping.value = false;
    }

    function openFileExternal(file: FileItem): void {
        window.open(file.url, '_blank');
    }

    const bulkLabel = computed(() =>
        trans('file-manager.labels.selected_count', { count: String(fm.selectionCount.value) }),
    );
</script>

<template>
    <div
        class="fm-root flex overflow-hidden rounded-xl border border-surface-200 bg-surface-0 dark:border-surface-700 dark:bg-surface-900"
        :style="{ height }"
    >
        <aside class="fm-sidebar w-72 shrink-0 border-r border-surface-200 dark:border-surface-700">
            <FolderTree
                :tree="fm.tree.value"
                :selected-id="fm.currentFolderId.value"
                :root-label="trans('file-manager.labels.root')"
                @select="(id) => fm.loadContents(id)"
            />
        </aside>

        <section
            class="fm-main flex min-w-0 flex-1 flex-col"
            :class="{ 'bg-primary-50/50 dark:bg-primary-950/20': isDropping }"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
        >
            <header
                class="flex flex-wrap items-center gap-2 border-b border-surface-200 px-4 py-3 dark:border-surface-700"
            >
                <Breadcrumb
                    :trail="fm.breadcrumb.value"
                    :root-label="trans('file-manager.labels.root')"
                    class="min-w-0 flex-1"
                    @navigate="(id) => fm.loadContents(id)"
                />

                <div class="flex items-center gap-1.5">
                    <Select
                        :model-value="fm.sort.value"
                        :options="sortOptions"
                        option-label="label"
                        option-value="value"
                        size="small"
                        class="fm-sort-select w-32"
                        @update:model-value="onSortChange"
                    />
                    <Button
                        size="small"
                        severity="secondary"
                        text
                        :icon="fm.direction.value === 'asc' ? 'pi pi-sort-amount-up' : 'pi pi-sort-amount-down'"
                        @click="toggleSortDir"
                    />
                </div>

                <Button
                    size="small"
                    severity="secondary"
                    icon="pi pi-folder-plus"
                    :label="trans('file-manager.labels.new_folder')"
                    :disabled="readonly"
                    @click="openNewFolder"
                />

                <Button
                    size="small"
                    :icon="uploading ? 'pi pi-spin pi-spinner' : 'pi pi-upload'"
                    :label="trans('file-manager.labels.upload')"
                    :disabled="readonly || uploading"
                    @click="triggerUpload"
                />

                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    class="hidden"
                    :accept="acceptedMimes?.join(',')"
                    @change="onFileChange"
                >
            </header>

            <div
                class="flex flex-wrap items-center justify-between gap-2 border-b border-surface-200 bg-surface-50 px-4 py-2 text-xs text-surface-600 dark:border-surface-700 dark:bg-surface-950 dark:text-surface-300"
            >
                <div class="flex items-center gap-4">
                    <span class="inline-flex items-center gap-1.5">
                        <i class="pi pi-file text-surface-400" style="font-size: 0.85rem" />
                        {{ trans('file-manager.labels.total_files', { count: String(fm.contents.stats.file_count) }) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <i class="pi pi-database text-surface-400" style="font-size: 0.85rem" />
                        {{ trans('file-manager.labels.total_size', { size: humanSize(fm.contents.stats.total_size) }) }}
                    </span>
                </div>

                <div v-if="fm.selectionCount.value > 0" class="flex items-center gap-2">
                    <span class="font-medium text-primary-600 dark:text-primary-300">{{ bulkLabel }}</span>
                    <Button
                        size="small"
                        severity="secondary"
                        text
                        icon="pi pi-times"
                        label="Clear"
                        @click="fm.clearSelection"
                    />
                    <Button
                        size="small"
                        severity="danger"
                        icon="pi pi-trash"
                        :label="trans('file-manager.labels.delete_selected')"
                        :disabled="readonly"
                        @click="confirmBulkDelete"
                    />
                </div>
            </div>

            <div class="relative flex-1 overflow-hidden">
                <FileGrid
                    :folders="fm.contents.folders"
                    :files="fm.contents.files"
                    :loading="fm.loading.contents"
                    :empty-label="trans('file-manager.labels.empty_folder')"
                    :is-selected="fm.isSelected"
                    @open-folder="(id) => fm.loadContents(id)"
                    @open-file="openFileExternal"
                    @context-folder="showFolderMenu"
                    @context-file="showFileMenu"
                    @context-empty="showEmptyMenu"
                    @download-file="downloadFile"
                    @toggle-select="onToggleSelect"
                    @set-selection="(keys) => fm.setSelection(keys)"
                    @clear-selection="fm.clearSelection"
                />

                <div
                    v-if="fm.loading.contents"
                    class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-surface-900/50"
                >
                    <ProgressSpinner style="width: 32px; height: 32px" stroke-width="4" />
                </div>

                <div
                    v-if="isDropping"
                    class="pointer-events-none absolute inset-4 flex items-center justify-center rounded-lg border-2 border-dashed border-primary-400 bg-primary-50/70 text-primary-700 dark:border-primary-500 dark:bg-primary-950/40 dark:text-primary-200"
                >
                    <div class="flex flex-col items-center gap-2">
                        <i class="pi pi-cloud-upload" style="font-size: 3rem" />
                        <span>{{ trans('file-manager.labels.drop_files_here') }}</span>
                    </div>
                </div>
            </div>
        </section>

        <ContextMenu ref="folderMenu" :model="folderMenuItems" />
        <ContextMenu ref="fileMenu" :model="fileMenuItems" />
        <ContextMenu ref="emptyMenu" :model="emptyMenuItems" />

        <Dialog
            v-model:visible="showNewFolder"
            :header="trans('file-manager.labels.new_folder')"
            modal
            :style="{ width: '24rem' }"
        >
            <InputText v-model="newFolderName" class="w-full" autofocus @keyup.enter="submitNewFolder" />
            <template #footer>
                <Button severity="secondary" text label="Cancel" @click="showNewFolder = false" />
                <Button label="OK" @click="submitNewFolder" />
            </template>
        </Dialog>

        <Dialog
            v-model:visible="showRename"
            :header="trans('file-manager.labels.rename')"
            modal
            :style="{ width: '24rem' }"
        >
            <InputText v-model="renameValue" class="w-full" autofocus @keyup.enter="submitRename" />
            <template #footer>
                <Button severity="secondary" text label="Cancel" @click="showRename = false" />
                <Button label="OK" @click="submitRename" />
            </template>
        </Dialog>
    </div>
</template>
