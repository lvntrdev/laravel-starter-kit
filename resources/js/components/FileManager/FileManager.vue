<script setup lang="ts">
    import { useConfirm } from '@/composables/useConfirm';
    import { useDialog } from '@/composables/useDialog';
    import { useImageLightbox } from '@/composables/useImageLightbox';
    import { trans } from 'laravel-vue-i18n';
    import Button from 'primevue/button';
    import ContextMenu from 'primevue/contextmenu';
    import Dialog from 'primevue/dialog';
    import IconField from 'primevue/iconfield';
    import InputIcon from 'primevue/inputicon';
    import InputText from 'primevue/inputtext';
    import ProgressSpinner from 'primevue/progressspinner';
    import Tooltip from 'primevue/tooltip';
    import { useToast } from 'primevue/usetoast';

    // Explicit binding so the template's `v-tooltip` compiles to a direct
    // reference instead of a dynamic `resolveDirective('tooltip')` call —
    // avoids the "resolveDirective imported but never used" warning in consumer builds.
    const vTooltip = Tooltip;
    import { computed, onMounted, ref } from 'vue';
    import FilePreviewModal, { suggestedPreviewWidth } from '@lvntr/components/ui/FilePreviewModal.vue';
    import Breadcrumb from './components/Breadcrumb.vue';
    import FileDetailsDialog from './components/FileDetailsDialog.vue';
    import FileGrid from './components/FileGrid.vue';
    import FileManagerSidebar from './components/FileManagerSidebar.vue';
    import FileManagerStats from './components/FileManagerStats.vue';
    import FolderTree from './components/FolderTree.vue';
    import { useFileManager } from './composables/useFileManager';
    import type {
        FileItem,
        FileManagerProps,
        FolderNode,
        FolderSummary,
        QuickView,
        SelectionKey,
        ViewMode,
    } from './types';

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
        window.addEventListener('keydown', onKeyDown);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', onKeyDown);
    });

    function isTypingElement(el: EventTarget | null): boolean {
        if (!(el instanceof HTMLElement)) return false;
        if (el.isContentEditable) return true;
        const tag = el.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
    }

    function isAnyDialogOpen(): boolean {
        return showNewFolder.value || showRename.value || showMove.value;
    }

    function onKeyDown(event: KeyboardEvent): void {
        if (isTypingElement(event.target)) return;
        if (isAnyDialogOpen()) return;
        if (busyMessage.value) return;

        const meta = event.ctrlKey || event.metaKey;

        if (meta && event.key.toLowerCase() === 'a') {
            if (fm.contents.folders.length + fm.contents.files.length === 0) return;
            event.preventDefault();
            fm.selectAll();
            return;
        }

        if (event.key === 'Escape') {
            if (fm.selectionCount.value > 0) {
                event.preventDefault();
                fm.clearSelection();
            }
            return;
        }

        if ((event.key === 'Delete' || event.key === 'Backspace') && !props.readonly) {
            if (fm.selectionCount.value === 0) return;
            event.preventDefault();
            confirmBulkDelete();
        }
    }

    // ── View / quick-view / search ──────────────────────────────
    const viewMode = ref<ViewMode>('grid');
    const quickView = ref<QuickView>('all');
    const searchQuery = ref('');

    function flattenTree(nodes: FolderNode[]): FolderSummary[] {
        const out: FolderSummary[] = [];
        const walk = (list: FolderNode[]) => {
            for (const node of list) {
                out.push({ id: node.id, parent_id: node.parent_id, name: node.name });
                if (node.children.length) walk(node.children);
            }
        };
        walk(nodes);
        return out;
    }

    const allFolders = computed(() => flattenTree(fm.tree.value));

    const filteredFolders = computed<FolderSummary[]>(() => {
        const q = searchQuery.value.trim().toLowerCase();
        if (!q) return fm.contents.folders;
        return fm.contents.folders.filter((f) => f.name.toLowerCase().includes(q));
    });

    const filteredFiles = computed<FileItem[]>(() => {
        const q = searchQuery.value.trim().toLowerCase();
        if (!q) return fm.contents.files;
        return fm.contents.files.filter((f) => f.file_name.toLowerCase().includes(q));
    });

    function onSelectQuick(view: QuickView): void {
        quickView.value = view;
        if (view === 'all') {
            fm.setSort('name', 'asc');
            fm.loadContents(null);
            return;
        }
        if (view === 'recent') {
            fm.setSort('date', 'desc');
            fm.loadContents(null);
            return;
        }
        toast.add({
            severity: 'info',
            summary: '',
            group: 'bc',
            detail: trans('sk-file-manager.coming_soon'),
            life: 2500,
        });
    }

    function onSelectSidebarFolder(folderId: string): void {
        quickView.value = 'all';
        fm.loadContents(folderId);
    }

    function setViewMode(mode: ViewMode): void {
        viewMode.value = mode;
    }

    // ── Stats ────────────────────────────────────────────────────
    const folderTotalCount = computed(() => allFolders.value.length);

    const lastUploadAt = computed<string | null>(() => {
        let latest: string | null = null;
        for (const f of fm.contents.files) {
            if (!f.created_at) continue;
            if (!latest || f.created_at > latest) latest = f.created_at;
        }
        return latest;
    });

    // Storage quota — currently a sane visual default (10 GB) until a backend
    // setting is wired up. The bar still scales correctly when filled.
    const STORAGE_QUOTA_BYTES = 10 * 1024 * 1024 * 1024;
    const usedBytes = computed(() => fm.contents.stats.total_size);

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
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.folder_created'),
                life: 2500,
            });
        } catch {
            /* handled by useApi */
        }
    }

    const showRename = ref(false);
    const renameTarget = ref<FolderSummary | null>(null);
    const renameValue = ref('');

    // ── Move modal ───────────────────────────────────────────────
    interface MoveSource {
        type: 'folder' | 'file';
        id: string;
        name: string;
    }

    const showMove = ref(false);
    const moveSources = ref<MoveSource[]>([]);
    const moveTargetId = ref<string | null>(null);

    const moveHeaderLabel = computed(() => {
        if (moveSources.value.length === 0) return '';
        if (moveSources.value.length === 1) return moveSources.value[0].name;
        return trans('sk-file-manager.labels.selected_count', { count: String(moveSources.value.length) });
    });

    function folderSummaryById(id: string): FolderSummary | null {
        return fm.contents.folders.find((f) => f.id === id) ?? null;
    }

    function fileItemById(id: string): FileItem | null {
        return fm.contents.files.find((f) => String(f.id) === id) ?? null;
    }

    function openMoveFolder(folder: FolderSummary): void {
        const multi = fm.selectionCount.value > 1 && fm.isSelected('folder', folder.id);
        openMoveDialog(multi ? collectSelectedSources() : [{ type: 'folder', id: folder.id, name: folder.name }]);
    }

    function openMoveFile(file: FileItem): void {
        const multi = fm.selectionCount.value > 1 && fm.isSelected('file', file.id);
        openMoveDialog(
            multi ? collectSelectedSources() : [{ type: 'file', id: String(file.id), name: file.file_name }],
        );
    }

    function collectSelectedSources(): MoveSource[] {
        const sources: MoveSource[] = [];
        for (const item of fm.selectedItems.value) {
            if (item.type === 'folder') {
                const f = folderSummaryById(item.id);
                if (f) sources.push({ type: 'folder', id: f.id, name: f.name });
            } else {
                const f = fileItemById(item.id);
                if (f) sources.push({ type: 'file', id: String(f.id), name: f.file_name });
            }
        }
        return sources;
    }

    function openMoveDialog(sources: MoveSource[]): void {
        if (sources.length === 0) return;
        moveSources.value = sources;
        moveTargetId.value = fm.currentFolderId.value;
        showMove.value = true;
    }

    async function runCancellableBulk<T extends { type: 'folder' | 'file'; id: string }>(
        title: string,
        items: T[],
        target: string | null,
        op: (item: T) => Promise<void>,
    ): Promise<void> {
        let cancelled = false;
        busy.value = {
            title,
            description: trans('sk-file-manager.labels.bulk_remaining', { count: String(items.length) }),
            onCancel: items.length > 1 ? () => (cancelled = true) : null,
        };
        try {
            let remaining = items.length;
            for (const item of items) {
                if (cancelled) break;
                if (item.type === 'folder' && item.id === target) {
                    remaining--;
                    setBusyDescription(trans('sk-file-manager.labels.bulk_remaining', { count: String(remaining) }));
                    continue;
                }
                await op(item);
                remaining--;
                setBusyDescription(trans('sk-file-manager.labels.bulk_remaining', { count: String(remaining) }));
            }
        } finally {
            busy.value = null;
        }
    }

    async function submitMove(): Promise<void> {
        if (moveSources.value.length === 0) return;
        const sources = moveSources.value;
        const target = moveTargetId.value;
        showMove.value = false;
        try {
            await runCancellableBulk(trans('sk-file-manager.labels.moving'), sources, target, (source) =>
                fm.moveItem(source.type, source.id, target),
            );
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.item_moved'),
                life: 2500,
            });
            fm.clearSelection();
        } catch {
            /* handled */
        }
    }

    async function handleDropOnFolder(targetFolderId: string): Promise<void> {
        const items = fm.selectedItems.value.length > 0 ? [...fm.selectedItems.value] : [];
        if (items.length === 0) return;
        try {
            await runCancellableBulk(trans('sk-file-manager.labels.moving'), items, targetFolderId, (item) =>
                fm.moveItem(item.type, item.id, targetFolderId),
            );
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.item_moved'),
                life: 2500,
            });
            fm.clearSelection();
        } catch {
            /* handled */
        }
    }

    function onInternalDragStart(type: 'folder' | 'file', id: string | number): void {
        const key = `${type}:${String(id)}` as SelectionKey;
        if (!fm.isSelected(type, id)) {
            fm.setSelection([key]);
        }
    }

    // ── Preview / details modal ──────────────────────────────────
    const dialog = useDialog();
    const lightbox = useImageLightbox();

    function openPreview(file: FileItem): void {
        if (file.mime_type.startsWith('image/')) {
            lightbox.open(file.url, file.file_name);
            return;
        }
        const width = suggestedPreviewWidth(file.mime_type);
        dialog.open(
            FilePreviewModal,
            {
                file: {
                    url: file.url,
                    name: file.file_name,
                    mimeType: file.mime_type,
                    size: file.size,
                },
                onDownload: () => downloadFile(file),
                showExternalOpen: true,
            },
            file.file_name,
            width ? { width } : {},
        );
    }

    function openInNewTab(file: FileItem): void {
        window.open(file.url, '_blank', 'noopener,noreferrer');
    }

    function openFileDetails(file: FileItem): void {
        const folderName =
            fm.breadcrumb.value.length > 0
                ? fm.breadcrumb.value[fm.breadcrumb.value.length - 1].name
                : trans('sk-file-manager.labels.root');

        dialog.open(
            FileDetailsDialog,
            { file, folderName, onDownload: () => downloadFile(file) },
            trans('sk-file-manager.labels.details.title'),
            { width: '32rem' },
        );
    }

    async function shareFile(file: FileItem): Promise<void> {
        const link = new URL(file.url, window.location.origin).toString();
        try {
            await navigator.clipboard.writeText(link);
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.link_copied'),
                life: 2500,
            });
        } catch {
            toast.add({
                severity: 'info',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.coming_soon'),
                life: 2500,
            });
        }
    }

    function comingSoon(): void {
        toast.add({
            severity: 'info',
            summary: '',
            group: 'bc',
            detail: trans('sk-file-manager.coming_soon'),
            life: 2500,
        });
    }

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
            showRename.value = false;
            await runBusy(trans('sk-file-manager.labels.renaming'), () =>
                fm.renameFolder(renameTarget.value!.id, name),
            );
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.folder_renamed'),
                life: 2500,
            });
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
        const multi = Boolean(
            bulkActive.value && contextFolder.value && fm.isSelected('folder', contextFolder.value.id),
        );
        return [
            {
                label: trans('sk-file-manager.labels.open'),
                icon: 'pi pi-folder-open',
                disabled: multi,
                command: () => contextFolder.value && fm.loadContents(contextFolder.value.id),
            },
            { separator: true },
            {
                label: trans('sk-file-manager.labels.rename'),
                icon: 'pi pi-pencil',
                disabled: props.readonly || multi,
                command: () => contextFolder.value && openRename(contextFolder.value),
            },
            {
                label: multi
                    ? trans('sk-file-manager.labels.move') + ` (${fm.selectionCount.value})`
                    : trans('sk-file-manager.labels.move'),
                icon: 'pi pi-arrow-right-arrow-left',
                disabled: props.readonly,
                command: () => contextFolder.value && openMoveFolder(contextFolder.value),
            },
            { separator: true },
            {
                label: trans('sk-file-manager.labels.add_to_favorites'),
                icon: 'pi pi-star',
                disabled: multi,
                command: () => comingSoon(),
            },
            { separator: true },
            {
                label: multi
                    ? trans('sk-file-manager.labels.delete_selected') + ` (${fm.selectionCount.value})`
                    : trans('sk-file-manager.labels.delete'),
                icon: 'pi pi-trash',
                class: 'fm-menu-danger',
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
        const multi = Boolean(bulkActive.value && contextFile.value && fm.isSelected('file', contextFile.value.id));
        return [
            {
                label: trans('sk-file-manager.labels.open'),
                icon: 'pi pi-external-link',
                disabled: multi,
                command: () => contextFile.value && openInNewTab(contextFile.value),
            },
            {
                label: trans('sk-file-manager.labels.preview'),
                icon: 'pi pi-eye',
                disabled: multi,
                command: () => contextFile.value && openPreview(contextFile.value),
            },
            { separator: true },
            {
                label: trans('sk-file-manager.labels.download'),
                icon: 'pi pi-download',
                disabled: multi,
                command: () => contextFile.value && downloadFile(contextFile.value),
            },
            {
                label: trans('sk-file-manager.labels.share'),
                icon: 'pi pi-share-alt',
                disabled: multi,
                command: () => contextFile.value && shareFile(contextFile.value),
            },
            { separator: true },
            {
                label: multi
                    ? trans('sk-file-manager.labels.move') + ` (${fm.selectionCount.value})`
                    : trans('sk-file-manager.labels.move'),
                icon: 'pi pi-arrow-right-arrow-left',
                disabled: props.readonly,
                command: () => contextFile.value && openMoveFile(contextFile.value),
            },
            {
                label: trans('sk-file-manager.labels.copy'),
                icon: 'pi pi-copy',
                disabled: multi,
                command: () => comingSoon(),
            },
            {
                label: trans('sk-file-manager.labels.rename'),
                icon: 'pi pi-pencil',
                disabled: props.readonly || multi,
                command: () => comingSoon(),
            },
            { separator: true },
            {
                label: trans('sk-file-manager.labels.add_to_favorites'),
                icon: 'pi pi-star',
                disabled: multi,
                command: () => comingSoon(),
            },
            {
                label: trans('sk-file-manager.labels.details'),
                icon: 'pi pi-info-circle',
                disabled: multi,
                command: () => contextFile.value && openFileDetails(contextFile.value),
            },
            { separator: true },
            {
                label: multi
                    ? trans('sk-file-manager.labels.delete_selected') + ` (${fm.selectionCount.value})`
                    : trans('sk-file-manager.labels.delete'),
                icon: 'pi pi-trash',
                class: 'fm-menu-danger',
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
        folderMenu.value?.show(event);
    }

    function showFileMenu(event: MouseEvent, file: FileItem): void {
        contextFile.value = file;
        fileMenu.value?.show(event);
    }

    const emptyMenuItems = computed(() => [
        {
            label: trans('sk-file-manager.labels.new_folder'),
            icon: 'pi pi-folder-plus',
            disabled: props.readonly,
            command: () => openNewFolder(),
        },
        {
            label: trans('sk-file-manager.labels.upload'),
            icon: 'pi pi-upload',
            disabled: props.readonly || uploading.value,
            command: () => triggerUpload(),
        },
        { separator: true },
        {
            label: trans('sk-file-manager.labels.select_all'),
            icon: 'pi pi-check-square',
            disabled: fm.contents.folders.length + fm.contents.files.length === 0,
            command: () => fm.selectAll(),
        },
        {
            label: trans('sk-file-manager.labels.refresh'),
            icon: 'pi pi-refresh',
            command: () => fm.refresh(),
        },
    ]);

    function showEmptyMenu(event: MouseEvent): void {
        emptyMenu.value?.show(event);
    }

    function confirmDeleteFolder(folder: FolderSummary): void {
        confirmDelete(async () => {
            await runBusy(trans('sk-file-manager.labels.deleting'), () => fm.deleteFolder(folder.id));
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.folder_deleted'),
                life: 2500,
            });
        });
    }

    function confirmDeleteFile(file: FileItem): void {
        confirmDelete(async () => {
            await runBusy(trans('sk-file-manager.labels.deleting'), () => fm.deleteFile(file.id));
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.file_deleted'),
                life: 2500,
            });
        });
    }

    function confirmBulkDelete(): void {
        if (fm.selectionCount.value === 0) return;
        confirmDelete(async () => {
            await runBusy(trans('sk-file-manager.labels.deleting'), () => fm.bulkDelete());
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.bulk_deleted'),
                life: 2500,
            });
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

    // ── Busy overlay ─────────────────────────────────────────────
    interface BusyState {
        title: string;
        description: string;
        onCancel: (() => void) | null;
    }

    const busy = ref<BusyState | null>(null);
    const busyMessage = computed(() => busy.value?.title ?? null);

    async function runBusy<T>(title: string, task: () => Promise<T>): Promise<T> {
        busy.value = { title, description: '', onCancel: null };
        try {
            return await task();
        } finally {
            busy.value = null;
        }
    }

    function setBusyDescription(description: string): void {
        if (busy.value) busy.value = { ...busy.value, description };
    }

    // ── Upload ───────────────────────────────────────────────────
    const fileInput = ref<HTMLInputElement | null>(null);
    const uploading = ref(false);
    const isDropping = ref(false);

    function triggerUpload(): void {
        fileInput.value?.click();
    }

    function isMimeAllowed(file: File): boolean {
        if (!props.acceptedMimes || props.acceptedMimes.length === 0) return true;
        const name = file.name.toLowerCase();
        return props.acceptedMimes.some((rule) => {
            const r = rule.trim().toLowerCase();
            if (!r) return false;
            if (r.startsWith('.')) return name.endsWith(r);
            if (r.endsWith('/*')) return file.type.startsWith(r.slice(0, -1));
            return file.type === r;
        });
    }

    function partitionFiles(list: File[]): { accepted: File[]; rejections: string[] } {
        const accepted: File[] = [];
        const rejections: string[] = [];
        const maxBytes = props.maxSizeKb ? props.maxSizeKb * 1024 : null;
        for (const file of list) {
            if (!isMimeAllowed(file)) {
                rejections.push(trans('sk-file-manager.errors.invalid_type', { name: file.name }));
                continue;
            }
            if (maxBytes !== null && file.size > maxBytes) {
                rejections.push(
                    trans('sk-file-manager.errors.file_too_large', {
                        name: file.name,
                        max: humanSize(maxBytes),
                    }),
                );
                continue;
            }
            accepted.push(file);
        }
        return { accepted, rejections };
    }

    async function handleFiles(fileList: FileList | File[] | null): Promise<void> {
        if (!fileList || (fileList as FileList).length === 0) return;
        const { accepted, rejections } = partitionFiles(Array.from(fileList as ArrayLike<File>));
        for (const message of rejections) {
            toast.add({ severity: 'warn', summary: '', group: 'bc', detail: message, life: 4000 });
        }
        if (accepted.length === 0) {
            if (fileInput.value) fileInput.value.value = '';
            return;
        }
        uploading.value = true;
        try {
            const result = await fm.uploadFiles(accepted);
            if (result.uploaded.length > 0) {
                toast.add({
                    severity: 'success',
                    summary: '',
                    group: 'bc',
                    detail: trans('sk-file-manager.files_uploaded'),
                    life: 2500,
                });
            }
            for (const message of result.errors) {
                toast.add({ severity: 'error', summary: '', group: 'bc', detail: message, life: 4000 });
            }
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
        if (event.dataTransfer && Array.from(event.dataTransfer.types).includes('Files')) {
            isDropping.value = true;
        }
    }

    function onDragLeave(): void {
        isDropping.value = false;
    }

    function openFileFromGrid(file: FileItem): void {
        openPreview(file);
    }

    const bulkLabel = computed(() =>
        trans('sk-file-manager.labels.selected_count', { count: String(fm.selectionCount.value) }),
    );

    const visiblePending = computed(() =>
        fm.pendingUploads.value.filter((p) => (p.folderId ?? null) === (fm.currentFolderId.value ?? null)),
    );

    const currentFolderName = computed(() => {
        const trail = fm.breadcrumb.value;
        if (trail.length === 0) {
            if (quickView.value === 'recent') return trans('sk-file-manager.labels.sidebar.recent');
            if (quickView.value === 'favorites') return trans('sk-file-manager.labels.sidebar.favorites');
            if (quickView.value === 'trash') return trans('sk-file-manager.labels.sidebar.trash');
            return trans('sk-file-manager.labels.files_section');
        }
        return trail[trail.length - 1].name;
    });

    const showBreadcrumb = computed(() => fm.currentFolderId.value !== null || fm.breadcrumb.value.length > 0);
</script>

<template>
    <div
        class="fm-root relative flex flex-col overflow-hidden rounded-xl border border-surface-200 bg-surface-0 dark:border-surface-700 dark:bg-surface-900"
        :style="{ height }"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
    >
        <!-- Top bar: search -->
        <div
            class="flex items-center justify-end gap-3 border-b border-surface-200 bg-surface-0 px-4 py-3 dark:border-surface-700 dark:bg-surface-900"
        >
            <IconField icon-position="left" class="w-full max-w-sm">
                <InputIcon class="pi pi-search" />
                <InputText
                    v-model="searchQuery"
                    type="text"
                    :placeholder="trans('sk-file-manager.labels.search_placeholder')"
                    class="w-full"
                />
            </IconField>
        </div>

        <!-- Body: sidebar + main -->
        <div class="flex min-h-0 flex-1">
            <FileManagerSidebar
                :tree="fm.tree.value"
                :current-folder-id="fm.currentFolderId.value"
                :quick-view="quickView"
                :used-bytes="usedBytes"
                :quota-bytes="STORAGE_QUOTA_BYTES"
                :readonly="readonly"
                @select-quick="onSelectQuick"
                @select-folder="onSelectSidebarFolder"
                @new-folder="openNewFolder"
            />

            <section
                class="fm-main flex min-w-0 flex-1 flex-col"
                :class="{ 'bg-primary-50/50 dark:bg-primary-950/20': isDropping }"
            >
                <!-- Stats -->
                <div
                    class="border-b border-surface-200 bg-surface-50/40 px-5 py-4 dark:border-surface-700 dark:bg-surface-950/30"
                >
                    <FileManagerStats
                        :file-count="fm.contents.stats.file_count"
                        :total-size="fm.contents.stats.total_size"
                        :folder-count="folderTotalCount"
                        :favorite-count="0"
                        :last-upload-at="lastUploadAt"
                    />
                </div>

                <!-- Header: title + view toggle + upload -->
                <header
                    class="flex flex-wrap items-center gap-3 border-b border-surface-200 px-5 py-3 dark:border-surface-700"
                >
                    <div class="flex min-w-0 flex-1 items-center gap-2">
                        <i class="pi pi-folder-open text-primary-500" style="font-size: 1.4rem" />
                        <h2 class="truncate text-xl font-semibold" :title="currentFolderName">
                            {{ currentFolderName }}
                        </h2>
                        <span
                            v-if="searchQuery.trim()"
                            class="ml-2 rounded-full bg-surface-100 px-2 py-0.5 text-xs font-medium text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                        >
                            {{ filteredFolders.length + filteredFiles.length }}
                        </span>
                    </div>

                    <div
                        class="inline-flex overflow-hidden rounded-lg border border-surface-200 dark:border-surface-700"
                    >
                        <button
                            v-tooltip.bottom="trans('sk-file-manager.labels.view_grid')"
                            type="button"
                            class="px-3 py-1.5 transition-colors"
                            :class="
                                viewMode === 'grid'
                                    ? 'bg-primary-500 text-white'
                                    : 'text-surface-600 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800'
                            "
                            :aria-pressed="viewMode === 'grid'"
                            @click="setViewMode('grid')"
                        >
                            <i class="pi pi-th-large" style="font-size: 0.95rem" />
                        </button>
                        <button
                            v-tooltip.bottom="trans('sk-file-manager.labels.view_list')"
                            type="button"
                            class="px-3 py-1.5 transition-colors"
                            :class="
                                viewMode === 'list'
                                    ? 'bg-primary-500 text-white'
                                    : 'text-surface-600 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800'
                            "
                            :aria-pressed="viewMode === 'list'"
                            @click="setViewMode('list')"
                        >
                            <i class="pi pi-list" style="font-size: 0.95rem" />
                        </button>
                    </div>

                    <Button
                        :icon="uploading ? 'pi pi-spin pi-spinner' : 'pi pi-cloud-upload'"
                        :label="trans('sk-file-manager.labels.upload_new')"
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

                <!-- Breadcrumb -->
                <div
                    v-if="showBreadcrumb"
                    class="border-b border-surface-200 bg-surface-50 px-5 py-2 dark:border-surface-700 dark:bg-surface-950"
                >
                    <Breadcrumb
                        :trail="fm.breadcrumb.value"
                        :root-label="trans('sk-file-manager.labels.root')"
                        @navigate="(id) => fm.loadContents(id)"
                    />
                </div>

                <!-- Selection bar -->
                <div
                    v-if="fm.selectionCount.value > 0"
                    class="flex items-center justify-end gap-2 border-b border-surface-200 bg-primary-50/40 px-5 py-2 dark:border-surface-700 dark:bg-primary-950/20"
                >
                    <span class="font-medium text-primary-600 dark:text-primary-300">{{ bulkLabel }}</span>
                    <Button
                        size="small"
                        severity="secondary"
                        text
                        icon="pi pi-times"
                        :label="trans('sk-file-manager.labels.close')"
                        @click="fm.clearSelection"
                    />
                    <Button
                        size="small"
                        severity="danger"
                        icon="pi pi-trash"
                        :label="trans('sk-file-manager.labels.delete_selected')"
                        :disabled="readonly"
                        @click="confirmBulkDelete"
                    />
                </div>

                <div class="relative flex-1 overflow-hidden">
                    <FileGrid
                        :folders="filteredFolders"
                        :files="filteredFiles"
                        :pending="visiblePending"
                        :loading="fm.loading.contents"
                        :empty-label="
                            searchQuery.trim()
                                ? trans('sk-file-manager.labels.no_results')
                                : trans('sk-file-manager.labels.empty_folder')
                        "
                        :is-selected="fm.isSelected"
                        :view-mode="viewMode"
                        @open-folder="(id) => fm.loadContents(id)"
                        @open-file="openFileFromGrid"
                        @context-folder="showFolderMenu"
                        @context-file="showFileMenu"
                        @context-empty="showEmptyMenu"
                        @download-file="downloadFile"
                        @toggle-select="onToggleSelect"
                        @set-selection="(keys) => fm.setSelection(keys)"
                        @clear-selection="fm.clearSelection"
                        @dismiss-pending="(id) => fm.dismissPending(id)"
                        @drop-on-folder="(targetId) => handleDropOnFolder(targetId)"
                        @internal-drag-start="onInternalDragStart"
                        @check-toggle="(type, id) => fm.toggleSelect(type, id)"
                    />

                    <div
                        v-if="fm.loading.contents"
                        class="absolute inset-0 flex items-center justify-center bg-white/50 dark:bg-surface-900/50"
                    >
                        <ProgressSpinner style="width: 32px; height: 32px" stroke-width="4" />
                    </div>
                </div>
            </section>
        </div>

        <!-- Full-area drop zone overlay -->
        <div
            v-if="isDropping"
            class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center bg-primary-500/10 backdrop-blur-sm"
        >
            <div
                class="flex flex-col items-center gap-3 rounded-2xl border-2 border-dashed border-primary-400 bg-surface-0/95 px-10 py-8 text-primary-700 shadow-xl dark:border-primary-500 dark:bg-surface-900/95 dark:text-primary-200"
            >
                <i class="pi pi-cloud-upload" style="font-size: 3.5rem" />
                <span class="text-lg font-semibold">{{ trans('sk-file-manager.labels.drop_files_here') }}</span>
            </div>
        </div>

        <!-- Busy overlay (modal card) -->
        <div
            v-if="busy"
            class="absolute inset-0 z-40 flex items-center justify-center bg-slate-900/40 backdrop-blur-sm"
        >
            <div
                class="flex w-88 max-w-[90%] flex-col gap-3 rounded-2xl bg-surface-0 p-6 shadow-2xl dark:bg-surface-900"
            >
                <div class="flex items-center gap-3">
                    <i class="pi pi-spin pi-spinner shrink-0 text-primary-500" style="font-size: 1.4rem" />
                    <h3 class="text-lg font-semibold leading-tight text-surface-900 dark:text-surface-100">
                        {{ busy.title }}
                    </h3>
                </div>
                <p v-if="busy.description" class="text-surface-500 dark:text-surface-400">
                    {{ busy.description }}
                </p>
                <div v-if="busy.onCancel" class="mt-2 flex justify-end">
                    <Button rounded :label="trans('sk-file-manager.labels.stop')" @click="busy.onCancel" />
                </div>
            </div>
        </div>

        <ContextMenu ref="folderMenu" class="fm-context-menu" :model="folderMenuItems" />
        <ContextMenu ref="fileMenu" class="fm-context-menu" :model="fileMenuItems" />
        <ContextMenu ref="emptyMenu" class="fm-context-menu" :model="emptyMenuItems" />

        <Dialog
            v-model:visible="showNewFolder"
            :header="trans('sk-file-manager.labels.new_folder')"
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
            :header="trans('sk-file-manager.labels.rename')"
            modal
            :style="{ width: '24rem' }"
        >
            <InputText v-model="renameValue" class="w-full" autofocus @keyup.enter="submitRename" />
            <template #footer>
                <Button severity="secondary" text label="Cancel" @click="showRename = false" />
                <Button label="OK" @click="submitRename" />
            </template>
        </Dialog>

        <Dialog
            v-model:visible="showMove"
            :header="trans('sk-file-manager.labels.move_header', { name: moveHeaderLabel })"
            modal
            :style="{ width: '28rem' }"
        >
            <div class="mb-3 text-surface-600 dark:text-surface-300">
                {{ trans('sk-file-manager.labels.move_hint') }}
            </div>
            <div class="max-h-80 overflow-auto rounded-lg border border-surface-200 dark:border-surface-700">
                <FolderTree
                    :tree="fm.tree.value"
                    :selected-id="moveTargetId"
                    :root-label="trans('sk-file-manager.labels.root')"
                    @select="(id) => (moveTargetId = id)"
                />
            </div>
            <template #footer>
                <Button
                    severity="secondary"
                    text
                    :label="trans('sk-file-manager.labels.close')"
                    @click="showMove = false"
                />
                <Button icon="pi pi-check" :label="trans('sk-file-manager.labels.move')" @click="submitMove" />
            </template>
        </Dialog>
    </div>
</template>

<style>
    .fm-context-menu.p-contextmenu {
        min-width: 16rem;
        padding: 0.4rem;
        background: var(--p-surface-0);
        border: 1px solid var(--p-surface-200);
        border-radius: 1rem;
        box-shadow:
            0 16px 40px -12px rgba(15, 23, 42, 0.22),
            0 4px 14px -4px rgba(15, 23, 42, 0.1);
    }
    .fm-context-menu.p-contextmenu .p-contextmenu-item-content {
        border-radius: 0.625rem;
    }
    .fm-context-menu.p-contextmenu .p-contextmenu-item-link {
        padding: 0.55rem 0.85rem;
        gap: 0.85rem;
        font-weight: 500;
        color: var(--p-surface-800);
    }
    .fm-context-menu.p-contextmenu .p-contextmenu-item-icon {
        color: var(--p-surface-600);
        font-size: 1rem;
    }
    .fm-context-menu.p-contextmenu .p-contextmenu-item:not(.p-disabled) .p-contextmenu-item-content:hover {
        background: var(--p-surface-100);
    }
    .fm-context-menu.p-contextmenu .p-contextmenu-separator {
        margin: 0.3rem 0.25rem;
        border-top: 1px solid var(--p-surface-200);
    }

    /* Danger entry — colours the label *and* icon red, on hover too. */
    .fm-context-menu.p-contextmenu .p-contextmenu-item.fm-menu-danger .p-contextmenu-item-link {
        color: var(--p-red-600, #dc2626);
    }
    .fm-context-menu.p-contextmenu .p-contextmenu-item.fm-menu-danger .p-contextmenu-item-icon {
        color: var(--p-red-600, #dc2626);
    }
    .fm-context-menu.p-contextmenu
        .p-contextmenu-item.fm-menu-danger:not(.p-disabled)
        .p-contextmenu-item-content:hover {
        background: var(--p-red-50, #fef2f2);
    }

    .dark .fm-context-menu.p-contextmenu {
        background: var(--p-surface-900);
        border-color: var(--p-surface-700);
        box-shadow:
            0 16px 40px -12px rgba(0, 0, 0, 0.6),
            0 4px 14px -4px rgba(0, 0, 0, 0.35);
    }
    .dark .fm-context-menu.p-contextmenu .p-contextmenu-item-link {
        color: var(--p-surface-100);
    }
    .dark .fm-context-menu.p-contextmenu .p-contextmenu-item-icon {
        color: var(--p-surface-400);
    }
    .dark .fm-context-menu.p-contextmenu .p-contextmenu-item:not(.p-disabled) .p-contextmenu-item-content:hover {
        background: var(--p-surface-800);
    }
    .dark .fm-context-menu.p-contextmenu .p-contextmenu-separator {
        border-color: var(--p-surface-700);
    }
    .dark .fm-context-menu.p-contextmenu .p-contextmenu-item.fm-menu-danger .p-contextmenu-item-link,
    .dark .fm-context-menu.p-contextmenu .p-contextmenu-item.fm-menu-danger .p-contextmenu-item-icon {
        color: var(--p-red-400, #f87171);
    }
    .dark
        .fm-context-menu.p-contextmenu
        .p-contextmenu-item.fm-menu-danger:not(.p-disabled)
        .p-contextmenu-item-content:hover {
        background: rgba(220, 38, 38, 0.12);
    }
</style>
