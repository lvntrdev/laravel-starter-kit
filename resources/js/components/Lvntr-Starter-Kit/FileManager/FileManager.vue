<script setup lang="ts">
    import { useConfirm } from '@/composables/useConfirm';
    import { useDialog } from '@/composables/useDialog';
    import { useImageLightbox } from '@/composables/useImageLightbox';
    import { trans } from 'laravel-vue-i18n';
    import Button from 'primevue/button';
    import ContextMenu from 'primevue/contextmenu';
    import Dialog from 'primevue/dialog';
    import InputText from 'primevue/inputtext';
    import ProgressSpinner from 'primevue/progressspinner';
    import Tooltip from 'primevue/tooltip';
    import { useToast } from 'primevue/usetoast';

    // Explicit binding so the template's `v-tooltip` compiles to a direct
    // reference instead of a dynamic `resolveDirective('tooltip')` call —
    // avoids the "resolveDirective imported but never used" warning in consumer builds.
    const vTooltip = Tooltip;
    import { usePage } from '@inertiajs/vue3';
    import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
    import FilePreviewModal, { suggestedPreviewWidth } from '../ui/FilePreviewModal.vue';
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
        height: 'auto',
    });

    const emit = defineEmits<{
        /** Paylaş menüsü tıklandığında dosya bilgisiyle emit edilir.
         *  Host bileşen (örn. Index.vue) bu event'i dinleyerek ShareLinkModal açar. */
        share: [file: FileItem];
    }>();

    const page = usePage();
    const effectiveEnableTrash = computed(
        () => props.enableTrash ?? page.props.fileManagerSettings?.enable_trash ?? true,
    );

    const toast = useToast();
    const { confirmDelete, confirmAction } = useConfirm();

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
        return showNewFolder.value || showRename.value || showRenameFile.value || showMove.value;
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

    const searchFilteredFiles = computed<FileItem[]>(() => {
        const q = searchQuery.value.trim().toLowerCase();
        if (!q) return fm.contents.files;
        return fm.contents.files.filter((f) => f.file_name.toLowerCase().includes(q));
    });

    // ── File-type filter (Tümü / Görsel / Video / PDF / Ses / Arşiv) ──
    type FileTypeFilter = 'all' | 'image' | 'video' | 'pdf' | 'audio' | 'archive';

    const fileTypeFilter = ref<FileTypeFilter>('all');

    function fileCategoryOf(mime: string): Exclude<FileTypeFilter, 'all'> | null {
        if (mime.startsWith('image/')) return 'image';
        if (mime.startsWith('video/')) return 'video';
        if (mime === 'application/pdf') return 'pdf';
        if (mime.startsWith('audio/')) return 'audio';
        if (
            mime.includes('zip') ||
            mime.includes('compressed') ||
            mime.includes('archive') ||
            mime === 'application/x-tar' ||
            mime === 'application/gzip'
        ) {
            return 'archive';
        }
        return null;
    }

    const filteredFiles = computed<FileItem[]>(() => {
        const list = searchFilteredFiles.value;
        if (fileTypeFilter.value === 'all') return list;
        return list.filter((f) => fileCategoryOf(f.mime_type) === fileTypeFilter.value);
    });

    interface FilterPill {
        key: FileTypeFilter;
        label: string;
        count: number;
    }

    const filterPills = computed<FilterPill[]>(() => {
        const counts: Record<FileTypeFilter, number> = {
            all: 0,
            image: 0,
            video: 0,
            pdf: 0,
            audio: 0,
            archive: 0,
        };
        for (const f of searchFilteredFiles.value) {
            counts.all++;
            const cat = fileCategoryOf(f.mime_type);
            if (cat) counts[cat]++;
        }
        return [
            { key: 'all', label: trans('sk-file-manager.labels.filter.all'), count: counts.all },
            { key: 'image', label: trans('sk-file-manager.labels.filter.image'), count: counts.image },
            { key: 'video', label: trans('sk-file-manager.labels.filter.video'), count: counts.video },
            { key: 'pdf', label: trans('sk-file-manager.labels.filter.pdf'), count: counts.pdf },
            { key: 'audio', label: trans('sk-file-manager.labels.filter.audio'), count: counts.audio },
            { key: 'archive', label: trans('sk-file-manager.labels.filter.archive'), count: counts.archive },
        ];
    });

    async function onSelectQuick(view: QuickView): Promise<void> {
        quickView.value = view;
        if (view === 'all') {
            fm.setSort('name', 'asc');
            await fm.loadContents(null);
            return;
        }
        if (view === 'recent') {
            fm.setSort('date', 'desc');
            await fm.loadContents(null);
            return;
        }
        if (view === 'favorites') {
            await fm.loadFavorites();
            return;
        }
        if (view === 'trash') {
            await fm.loadTrash();
            return;
        }
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

    const favoriteCount = computed(() => {
        if (quickView.value === 'favorites') {
            return fm.contents.folders.length + fm.contents.files.length;
        }
        let count = 0;
        for (const f of fm.contents.folders) {
            if (f.is_favorited === true) count++;
        }
        for (const f of fm.contents.files) {
            if (f.is_favorited === true) count++;
        }
        return count;
    });

    // Use storage_used (all collections) when available; fall back to current-view total_size.
    const usedBytes = computed(() => fm.contents.stats.storage_used ?? fm.contents.stats.total_size);
    const quotaBytes = computed(() => fm.contents.stats.storage_quota ?? 0);

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

    const showRenameFile = ref(false);
    const renameFileTarget = ref<FileItem | null>(null);
    const renameFileValue = ref('');

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
        if (props.readonly) return;
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

    function shareFile(file: FileItem): void {
        emit('share', file);
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

    async function copyFile(file: FileItem): Promise<void> {
        try {
            await runBusy(trans('sk-file-manager.labels.copying'), () => fm.copyFile(file.id));
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.file_copied'),
                life: 2500,
            });
        } catch {
            /* handled by interceptor */
        }
    }

    function openRenameFile(file: FileItem): void {
        renameFileTarget.value = file;
        renameFileValue.value = file.file_name;
        showRenameFile.value = true;
    }

    async function submitRenameFile(): Promise<void> {
        if (!renameFileTarget.value) return;
        const name = renameFileValue.value.trim();
        if (!name || name === renameFileTarget.value.file_name) {
            showRenameFile.value = false;
            return;
        }
        showRenameFile.value = false;
        try {
            await runBusy(trans('sk-file-manager.labels.renaming'), () =>
                fm.renameFile(renameFileTarget.value!.id, name),
            );
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.file_renamed'),
                life: 2500,
            });
        } catch {
            /* handled by interceptor */
        }
    }

    async function toggleFolderFavorite(folder: FolderSummary): Promise<void> {
        const currentlyFavorited = folder.is_favorited === true;
        try {
            await fm.toggleFavorite('folder', folder.id, currentlyFavorited);
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans(
                    currentlyFavorited ? 'sk-file-manager.favorite_removed' : 'sk-file-manager.favorite_added',
                ),
                life: 2000,
            });
        } catch {
            /* useApi interceptor tarafından yönetilir */
        }
    }

    async function toggleFileFavorite(file: FileItem): Promise<void> {
        const currentlyFavorited = file.is_favorited === true;
        try {
            await fm.toggleFavorite('file', String(file.id), currentlyFavorited);
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans(
                    currentlyFavorited ? 'sk-file-manager.favorite_removed' : 'sk-file-manager.favorite_added',
                ),
                life: 2000,
            });
        } catch {
            /* useApi interceptor tarafından yönetilir */
        }
    }

    // ── Trash actions ────────────────────────────────────────────
    async function restoreFolder(folder: FolderSummary): Promise<void> {
        try {
            await runBusy(trans('sk-file-manager.labels.restoring'), () => fm.restoreItem('folder', folder.id));
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.item_restored'),
                life: 2500,
            });
        } catch {
            /* useApi interceptor tarafından yönetilir */
        }
    }

    async function restoreFile(file: FileItem): Promise<void> {
        try {
            await runBusy(trans('sk-file-manager.labels.restoring'), () => fm.restoreItem('file', String(file.id)));
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.item_restored'),
                life: 2500,
            });
        } catch {
            /* useApi interceptor tarafından yönetilir */
        }
    }

    function confirmPermanentDeleteFolder(folder: FolderSummary): void {
        confirmAction({
            header: trans('sk-file-manager.confirm.permanent_delete_title'),
            message: trans('sk-file-manager.confirm.permanent_delete_message'),
            icon: 'pi pi-exclamation-triangle',
            acceptLabel: trans('sk-file-manager.labels.permanently_delete'),
            rejectLabel: trans('sk-file-manager.labels.close'),
            acceptClass: 'p-button-danger',
            onAccept: async () => {
                await runBusy(trans('sk-file-manager.labels.deleting'), () =>
                    fm.permanentlyDeleteItem('folder', folder.id),
                );
                toast.add({
                    severity: 'success',
                    summary: '',
                    group: 'bc',
                    detail: trans('sk-file-manager.item_permanently_deleted'),
                    life: 2500,
                });
            },
        });
    }

    function confirmPermanentDeleteFile(file: FileItem): void {
        confirmAction({
            header: trans('sk-file-manager.confirm.permanent_delete_title'),
            message: trans('sk-file-manager.confirm.permanent_delete_message'),
            icon: 'pi pi-exclamation-triangle',
            acceptLabel: trans('sk-file-manager.labels.permanently_delete'),
            rejectLabel: trans('sk-file-manager.labels.close'),
            acceptClass: 'p-button-danger',
            onAccept: async () => {
                await runBusy(trans('sk-file-manager.labels.deleting'), () =>
                    fm.permanentlyDeleteItem('file', String(file.id)),
                );
                toast.add({
                    severity: 'success',
                    summary: '',
                    group: 'bc',
                    detail: trans('sk-file-manager.item_permanently_deleted'),
                    life: 2500,
                });
            },
        });
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

        if (fm.currentView.value === 'trash') {
            return [
                {
                    label: trans('sk-file-manager.labels.restore'),
                    icon: 'pi pi-undo',
                    disabled: multi,
                    command: () => contextFolder.value && restoreFolder(contextFolder.value),
                },
                { separator: true },
                {
                    label: trans('sk-file-manager.labels.permanently_delete'),
                    icon: 'pi pi-times',
                    class: 'fm-menu-danger',
                    disabled: multi,
                    command: () => contextFolder.value && confirmPermanentDeleteFolder(contextFolder.value),
                },
            ];
        }

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
                label: trans(
                    contextFolder.value?.is_favorited
                        ? 'sk-file-manager.labels.remove_from_favorites'
                        : 'sk-file-manager.labels.add_to_favorites',
                ),
                icon: contextFolder.value?.is_favorited ? 'pi pi-star-fill' : 'pi pi-star',
                disabled: multi || props.readonly,
                command: () => contextFolder.value && toggleFolderFavorite(contextFolder.value),
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

        if (fm.currentView.value === 'trash') {
            return [
                {
                    label: trans('sk-file-manager.labels.restore'),
                    icon: 'pi pi-undo',
                    disabled: multi,
                    command: () => contextFile.value && restoreFile(contextFile.value),
                },
                { separator: true },
                {
                    label: trans('sk-file-manager.labels.permanently_delete'),
                    icon: 'pi pi-times',
                    class: 'fm-menu-danger',
                    disabled: multi,
                    command: () => contextFile.value && confirmPermanentDeleteFile(contextFile.value),
                },
            ];
        }

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
                disabled: props.readonly || multi,
                command: () => contextFile.value && copyFile(contextFile.value),
            },
            {
                label: trans('sk-file-manager.labels.rename'),
                icon: 'pi pi-pencil',
                disabled: props.readonly || multi,
                command: () => contextFile.value && openRenameFile(contextFile.value),
            },
            { separator: true },
            {
                label: trans(
                    contextFile.value?.is_favorited
                        ? 'sk-file-manager.labels.remove_from_favorites'
                        : 'sk-file-manager.labels.add_to_favorites',
                ),
                icon: contextFile.value?.is_favorited ? 'pi pi-star-fill' : 'pi pi-star',
                disabled: multi || props.readonly,
                command: () => contextFile.value && toggleFileFavorite(contextFile.value),
            },
            {
                label: trans('sk-file-manager.labels.details_action'),
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
            if (effectiveEnableTrash.value) {
                await runBusy(trans('sk-file-manager.labels.deleting'), () => fm.deleteFolder(folder.id));
                toast.add({
                    severity: 'success',
                    summary: '',
                    group: 'bc',
                    detail: trans('sk-file-manager.folder_deleted'),
                    life: 2500,
                });
            } else {
                await runBusy(trans('sk-file-manager.labels.deleting'), () =>
                    fm.permanentlyDeleteItem('folder', folder.id),
                );
                toast.add({
                    severity: 'success',
                    summary: '',
                    group: 'bc',
                    detail: trans('sk-file-manager.folder_force_deleted'),
                    life: 2500,
                });
            }
        });
    }

    function confirmDeleteFile(file: FileItem): void {
        confirmDelete(async () => {
            if (effectiveEnableTrash.value) {
                await runBusy(trans('sk-file-manager.labels.deleting'), () => fm.deleteFile(file.id));
                toast.add({
                    severity: 'success',
                    summary: '',
                    group: 'bc',
                    detail: trans('sk-file-manager.file_deleted'),
                    life: 2500,
                });
            } else {
                await runBusy(trans('sk-file-manager.labels.deleting'), () =>
                    fm.permanentlyDeleteItem('file', String(file.id)),
                );
                toast.add({
                    severity: 'success',
                    summary: '',
                    group: 'bc',
                    detail: trans('sk-file-manager.file_force_deleted'),
                    life: 2500,
                });
            }
        });
    }

    function confirmBulkDelete(): void {
        if (fm.selectionCount.value === 0) return;
        const inTrash = fm.currentView.value === 'trash';
        confirmDelete(async () => {
            if (inTrash || !effectiveEnableTrash.value) {
                await runBusy(trans('sk-file-manager.labels.deleting'), () => fm.bulkForceDelete());
                toast.add({
                    severity: 'success',
                    summary: '',
                    group: 'bc',
                    detail: trans('sk-file-manager.bulk_force_deleted'),
                    life: 2500,
                });
            } else {
                await runBusy(trans('sk-file-manager.labels.deleting'), () => fm.bulkDelete());
                toast.add({
                    severity: 'success',
                    summary: '',
                    group: 'bc',
                    detail: trans('sk-file-manager.bulk_deleted'),
                    life: 2500,
                });
            }
        });
    }

    function confirmEmptyTrash(): void {
        confirmAction({
            header: trans('sk-file-manager.confirm.empty_trash_title'),
            message: trans('sk-file-manager.confirm.empty_trash_message'),
            icon: 'pi pi-exclamation-triangle',
            acceptLabel: trans('sk-file-manager.labels.empty_trash'),
            rejectLabel: trans('sk-file-manager.labels.close'),
            acceptClass: 'p-button-danger',
            onAccept: async () => {
                await runBusy(trans('sk-file-manager.labels.deleting'), () => fm.emptyTrash());
                toast.add({
                    severity: 'success',
                    summary: '',
                    group: 'bc',
                    detail: trans('sk-file-manager.trash_emptied'),
                    life: 2500,
                });
            },
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
        if (props.readonly) return;
        event.preventDefault();
        isDropping.value = false;
        handleFiles(event.dataTransfer?.files ?? null);
    }

    function onDragOver(event: DragEvent): void {
        if (props.readonly) return;
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

    const hasSelectedFiles = computed(() => fm.selectedItems.value.some((it) => it.type === 'file'));

    function bulkDownload(): void {
        const fileItems = collectSelectedSources().filter((s) => s.type === 'file');
        if (fileItems.length === 0) {
            toast.add({
                severity: 'info',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.coming_soon'),
                life: 2500,
            });
            return;
        }
        for (const src of fileItems) {
            const file = fileItemById(src.id);
            if (file) downloadFile(file);
        }
    }

    function bulkShare(): void {
        const fileItems = collectSelectedSources().filter((s) => s.type === 'file');
        if (fileItems.length === 0) {
            toast.add({
                severity: 'info',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.coming_soon'),
                life: 2500,
            });
            return;
        }
        if (fileItems.length === 1) {
            const file = fileItemById(fileItems[0].id);
            if (file) shareFile(file);
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

    function bulkMove(): void {
        if (fm.selectionCount.value === 0) return;
        openMoveDialog(collectSelectedSources());
    }

    async function bulkRestore(): Promise<void> {
        if (fm.selectionCount.value === 0) return;
        const items = collectSelectedSources();
        try {
            await runCancellableBulk(
                trans('sk-file-manager.labels.restoring'),
                items,
                null,
                (item) => fm.restoreItem(item.type, item.id),
            );
            toast.add({
                severity: 'success',
                summary: '',
                group: 'bc',
                detail: trans('sk-file-manager.item_restored'),
                life: 2500,
            });
            fm.clearSelection();
        } catch {
            /* useApi interceptor tarafından yönetilir */
        }
    }

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
        class="fm-root relative"
        :style="height === 'auto' ? {} : height.endsWith('%') ? { height } : { minHeight: height }"
        @dragover="onDragOver"
        @dragleave="onDragLeave"
        @drop="onDrop"
    >
        <!-- fm-cols: sidebar 240 + content-wrap 1fr -->
        <div
            class="fm-cols-grid"
            style="display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 1rem; align-items: start"
        >
            <!-- Sidebar standalone card -->
            <FileManagerSidebar
                :tree="fm.tree.value"
                :current-folder-id="fm.currentFolderId.value"
                :quick-view="quickView"
                :used-bytes="usedBytes"
                :quota-bytes="quotaBytes"
                :readonly="readonly"
                :enable-trash="effectiveEnableTrash"
                @select-quick="onSelectQuick"
                @select-folder="onSelectSidebarFolder"
                @new-folder="openNewFolder"
            />

            <!-- Right column: stats + content panel -->
            <div class="flex min-w-0 flex-col gap-4">
                <!-- Stats grid (5 tiles) -->
                <FileManagerStats
                    :file-count="fm.contents.stats.file_count"
                    :total-size="fm.contents.stats.total_size"
                    :folder-count="folderTotalCount"
                    :favorite-count="favoriteCount"
                    :last-upload-at="lastUploadAt"
                />

                <!-- Content panel: single white card with folders + files sections -->
                <div
                    class="fm-content-panel relative flex min-w-0 flex-col gap-3 rounded-[6px] border border-surface-200 bg-surface-0 p-4 shadow-sm dark:border-surface-700 dark:bg-surface-900"
                    :class="{ 'border-primary-300 bg-primary-50/20 dark:bg-primary-950/10': isDropping }"
                >
                    <!-- Breadcrumb -->
                    <div
                        v-if="showBreadcrumb"
                        class="flex-shrink-0 rounded-[6px] border border-surface-200 bg-surface-50 px-3 py-1.5 dark:border-surface-700 dark:bg-surface-950"
                    >
                        <Breadcrumb
                            :trail="fm.breadcrumb.value"
                            :root-label="trans('sk-file-manager.labels.root')"
                            @navigate="(id) => fm.loadContents(id)"
                        />
                    </div>

                    <!-- Favorites empty state -->
                    <div
                        v-if="
                            quickView === 'favorites' &&
                                fm.contents.folders.length === 0 &&
                                fm.contents.files.length === 0 &&
                                !fm.loading.contents
                        "
                        class="flex min-h-[400px] flex-col items-center justify-center gap-3 p-8 text-center"
                    >
                        <i class="pi pi-star text-5xl text-amber-400" />
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            {{ trans('sk-file-manager.favorites_empty_title') }}
                        </h3>
                        <p class="max-w-md text-lg text-surface-500 dark:text-surface-400">
                            {{ trans('sk-file-manager.favorites_empty_subtitle') }}
                        </p>
                    </div>

                    <!-- Trash empty state -->
                    <div
                        v-else-if="
                            quickView === 'trash' &&
                                fm.contents.folders.length === 0 &&
                                fm.contents.files.length === 0 &&
                                !fm.loading.contents
                        "
                        class="flex min-h-[400px] flex-col items-center justify-center gap-3 p-8 text-center"
                    >
                        <i class="pi pi-trash text-5xl text-amber-400" />
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            {{ trans('sk-file-manager.trash_empty_title') }}
                        </h3>
                        <p class="max-w-md text-lg text-surface-500 dark:text-surface-400">
                            {{ trans('sk-file-manager.trash_empty_subtitle') }}
                        </p>
                    </div>

                    <template v-else>
                        <!-- Toolbar: filter pills left, search + actions + view toggle right -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Filter pills (left side) -->
                            <div
                                v-if="searchFilteredFiles.length > 0 && quickView !== 'trash'"
                                class="flex flex-wrap items-center gap-1.5"
                            >
                                <button
                                    v-for="pill in filterPills"
                                    :key="pill.key"
                                    type="button"
                                    class="flex items-center gap-1.5 rounded-full border px-3 py-1 text-lg font-semibold transition-colors"
                                    :class="
                                        fileTypeFilter === pill.key
                                            ? 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700 dark:bg-primary-950/40 dark:text-primary-200'
                                            : 'border-surface-200 bg-surface-0 text-surface-600 hover:border-surface-300 hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300 dark:hover:bg-surface-800'
                                    "
                                    @click="fileTypeFilter = pill.key"
                                >
                                    <span>{{ pill.label }}</span>
                                    <span
                                        class="rounded-full px-1.5 text-lg font-bold leading-tight"
                                        :class="
                                            fileTypeFilter === pill.key
                                                ? 'bg-primary-500/15 text-primary-700 dark:text-primary-200'
                                                : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
                                        "
                                    >
                                        {{ pill.count }}
                                    </span>
                                </button>
                            </div>

                            <!-- Right side: search + upload + view toggle (always visible) -->
                            <div class="ml-auto flex flex-wrap items-center gap-2">
                                <div class="relative w-full max-w-xs sm:w-60">
                                    <i
                                        class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400"
                                        style="font-size: 0.78rem"
                                    />
                                    <InputText
                                        v-model="searchQuery"
                                        type="text"
                                        size="small"
                                        class="w-full !pl-9"
                                        :placeholder="trans('sk-file-manager.labels.search_placeholder')"
                                    />
                                </div>
                                <Button
                                    v-if="!readonly && quickView !== 'trash'"
                                    size="small"
                                    severity="secondary"
                                    icon="pi pi-folder-plus"
                                    :label="trans('sk-file-manager.labels.new_folder')"
                                    :disabled="quickView === 'favorites'"
                                    @click="openNewFolder"
                                />
                                <Button
                                    v-if="quickView !== 'trash'"
                                    size="small"
                                    :icon="uploading ? 'pi pi-spin pi-spinner' : 'pi pi-cloud-upload'"
                                    :label="trans('sk-file-manager.labels.upload_new')"
                                    :disabled="readonly || uploading || quickView === 'favorites'"
                                    @click="triggerUpload"
                                />
                                <Button
                                    v-if="quickView === 'trash'"
                                    size="small"
                                    severity="danger"
                                    icon="pi pi-trash"
                                    :label="trans('sk-file-manager.labels.empty_trash')"
                                    :disabled="readonly || (fm.contents.folders.length === 0 && fm.contents.files.length === 0)"
                                    @click="confirmEmptyTrash"
                                />
                                <div
                                    class="inline-flex overflow-hidden rounded-[6px] border border-surface-200 dark:border-surface-700"
                                >
                                    <button
                                        v-tooltip.bottom="trans('sk-file-manager.labels.view_grid')"
                                        type="button"
                                        class="px-2.5 py-1.5 transition-colors"
                                        :class="
                                            viewMode === 'grid'
                                                ? 'bg-primary-500 text-white'
                                                : 'text-surface-600 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800'
                                        "
                                        :aria-pressed="viewMode === 'grid'"
                                        @click="setViewMode('grid')"
                                    >
                                        <i class="pi pi-th-large" style="font-size: 0.85rem" />
                                    </button>
                                    <button
                                        v-tooltip.bottom="trans('sk-file-manager.labels.view_list')"
                                        type="button"
                                        class="px-2.5 py-1.5 transition-colors"
                                        :class="
                                            viewMode === 'list'
                                                ? 'bg-primary-500 text-white'
                                                : 'text-surface-600 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800'
                                        "
                                        :aria-pressed="viewMode === 'list'"
                                        @click="setViewMode('list')"
                                    >
                                        <i class="pi pi-list" style="font-size: 0.85rem" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <FileGrid
                            :folders="filteredFolders"
                            :files="filteredFiles"
                            :pending="visiblePending"
                            :loading="fm.loading.contents"
                            :empty-label="trans('sk-file-manager.labels.no_results')"
                            :search-active="searchQuery.trim().length > 0"
                            :readonly="readonly"
                            :is-selected="fm.isSelected"
                            :view-mode="viewMode"
                            :current-view="quickView"
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
                            @upload="triggerUpload"
                            @toggle-folder-favorite="toggleFolderFavorite"
                            @toggle-file-favorite="toggleFileFavorite"
                        />
                    </template>

                    <div
                        v-if="fm.loading.contents"
                        class="absolute inset-0 z-20 flex items-center justify-center rounded-[6px] bg-white/50 dark:bg-surface-900/50"
                    >
                        <ProgressSpinner style="width: 32px; height: 32px" stroke-width="4" />
                    </div>
                </div>
            </div>

            <input
                ref="fileInput"
                type="file"
                multiple
                class="hidden"
                :accept="acceptedMimes?.join(',')"
                @change="onFileChange"
            >
        </div>

        <!-- Full-area drop zone overlay -->
        <div
            v-if="isDropping"
            class="pointer-events-none absolute inset-0 z-30 flex items-center justify-center bg-primary-500/10 backdrop-blur-sm"
        >
            <div
                class="flex flex-col items-center gap-3 rounded-[6px] border-2 border-dashed border-primary-400 bg-surface-0/95 px-10 py-8 text-primary-700 shadow-xl dark:border-primary-500 dark:bg-surface-900/95 dark:text-primary-200"
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
                class="flex w-88 max-w-[90%] flex-col gap-3 rounded-[6px] bg-surface-0 p-6 shadow-2xl dark:bg-surface-900"
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
            v-model:visible="showRenameFile"
            :header="trans('sk-file-manager.labels.rename_file_title')"
            modal
            :style="{ width: '24rem' }"
        >
            <InputText v-model="renameFileValue" class="w-full" autofocus @keyup.enter="submitRenameFile" />
            <template #footer>
                <Button severity="secondary" text label="Cancel" @click="showRenameFile = false" />
                <Button label="OK" @click="submitRenameFile" />
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

        <!-- Floating bulk-action bar (bottom, dark pill) -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                leave-active-class="transition duration-150 ease-in"
                enter-from-class="opacity-0 translate-y-4"
                enter-to-class="opacity-100 translate-y-0"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-4"
            >
                <div
                    v-if="fm.selectionCount.value > 0"
                    class="fm-bulk-bar fixed bottom-6 left-1/2 z-50 -translate-x-1/2"
                >
                    <div
                        class="flex items-center gap-1 rounded-full bg-slate-900 p-1.5 text-white shadow-2xl ring-1 ring-white/10 dark:bg-slate-950"
                    >
                        <span class="px-3 text-[13px] font-semibold tracking-tight">
                            {{ bulkLabel }}
                        </span>
                        <span class="mx-1 h-5 w-px bg-white/15" />

                        <!-- Çöp kutusu: sadece Geri Yükle + Kalıcı Sil -->
                        <template v-if="quickView === 'trash'">
                            <button
                                type="button"
                                class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[13px] font-medium transition-colors hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                                @click="bulkRestore"
                            >
                                <i class="pi pi-undo" style="font-size: 0.85rem" />
                                <span>{{ trans('sk-file-manager.labels.restore') }}</span>
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[13px] font-medium text-rose-300 transition-colors hover:bg-rose-500/15 hover:text-rose-200 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="readonly"
                                @click="confirmBulkDelete"
                            >
                                <i class="pi pi-trash" style="font-size: 0.85rem" />
                                <span>{{ trans('sk-file-manager.labels.permanently_delete') }}</span>
                            </button>
                        </template>

                        <!-- Normal görünüm: İndir + Paylaş + Taşı + Sil -->
                        <template v-else>
                            <button
                                type="button"
                                class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[13px] font-medium transition-colors hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!hasSelectedFiles"
                                @click="bulkDownload"
                            >
                                <i class="pi pi-download" style="font-size: 0.85rem" />
                                <span>{{ trans('sk-file-manager.labels.download') }}</span>
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[13px] font-medium transition-colors hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="!hasSelectedFiles"
                                @click="bulkShare"
                            >
                                <i class="pi pi-share-alt" style="font-size: 0.85rem" />
                                <span>{{ trans('sk-file-manager.labels.share') }}</span>
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[13px] font-medium transition-colors hover:bg-white/10 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="readonly"
                                @click="bulkMove"
                            >
                                <i class="pi pi-arrow-right-arrow-left" style="font-size: 0.85rem" />
                                <span>{{ trans('sk-file-manager.labels.move') }}</span>
                            </button>
                            <button
                                type="button"
                                class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[13px] font-medium text-rose-300 transition-colors hover:bg-rose-500/15 hover:text-rose-200 disabled:cursor-not-allowed disabled:opacity-40"
                                :disabled="readonly"
                                @click="confirmBulkDelete"
                            >
                                <i class="pi pi-trash" style="font-size: 0.85rem" />
                                <span>{{ trans('sk-file-manager.labels.delete') }}</span>
                            </button>
                        </template>

                        <span class="mx-1 h-5 w-px bg-white/15" />
                        <button
                            type="button"
                            class="flex h-8 w-8 items-center justify-center rounded-full text-white/80 transition-colors hover:bg-white/10 hover:text-white"
                            :aria-label="trans('sk-file-manager.labels.close')"
                            @click="fm.clearSelection"
                        >
                            <i class="pi pi-times" style="font-size: 0.85rem" />
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
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
