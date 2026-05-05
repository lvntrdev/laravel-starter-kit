import { useApi } from '@/composables/useApi';
import { computed, reactive, ref } from 'vue';
import type {
    FileItem,
    FileManagerContext,
    FolderContents,
    FolderNode,
    FolderSummary,
    PendingUpload,
    QuickView,
    SelectedItem,
    SelectionKey,
    SortDirection,
    SortKey,
} from '../types';

interface Options {
    context: FileManagerContext;
    contextId?: string | null;
}

interface TreeResponse {
    tree: FolderNode[];
}

interface FolderResponse {
    folder: FolderSummary;
}

interface UploadResponse {
    files: FileItem[];
}

function selectionKey(type: 'folder' | 'file', id: string | number): SelectionKey {
    return `${type}:${String(id)}` as SelectionKey;
}

function generateTempId(): string {
    const cryptoObj = typeof globalThis !== 'undefined' ? globalThis.crypto : undefined;
    if (cryptoObj?.randomUUID) {
        return cryptoObj.randomUUID();
    }
    if (cryptoObj?.getRandomValues) {
        const bytes = cryptoObj.getRandomValues(new Uint8Array(16));
        return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
    }
    return `${Date.now().toString(16)}-${Math.random().toString(16).slice(2)}`;
}

export function useFileManager(options: Options) {
    const api = useApi();

    const tree = ref<FolderNode[]>([]);
    const currentFolderId = ref<string | null>(null);
    const currentView = ref<QuickView>('all');
    const contents = reactive<FolderContents>({
        folder: null,
        folders: [],
        files: [],
        stats: { file_count: 0, total_size: 0 },
    });
    const breadcrumb = ref<FolderSummary[]>([]);
    const loading = reactive({ tree: false, contents: false });

    const sort = ref<SortKey>('name');
    const direction = ref<SortDirection>('asc');

    const selectedKeys = ref<Set<SelectionKey>>(new Set());

    const pendingUploads = ref<PendingUpload[]>([]);
    const favoritePending = ref<Set<string>>(new Set());

    const contextQuery = computed(() => {
        const params = new URLSearchParams({ context: options.context });
        if (options.contextId) {
            params.set('context_id', options.contextId);
        }
        return params.toString();
    });

    function qs(extra: Record<string, string | null | undefined> = {}): string {
        const params = new URLSearchParams(contextQuery.value);
        for (const [key, value] of Object.entries(extra)) {
            if (value != null && value !== '') {
                params.set(key, value);
            }
        }
        return params.toString();
    }

    function contextPayload<T extends Record<string, unknown>>(
        payload: T,
    ): T & { context: string; context_id?: string | null } {
        return { context: options.context, context_id: options.contextId ?? null, ...payload };
    }

    async function loadTree(): Promise<void> {
        loading.tree = true;
        try {
            const res = await api.get<TreeResponse>(`/file-manager/tree?${contextQuery.value}`);
            tree.value = res.tree;
        } finally {
            loading.tree = false;
        }
    }

    function findFolder(nodes: FolderNode[], id: string, trail: FolderSummary[] = []): FolderSummary[] | null {
        for (const node of nodes) {
            const current = [...trail, { id: node.id, parent_id: node.parent_id, name: node.name }];
            if (node.id === id) {
                return current;
            }
            const deeper = findFolder(node.children, id, current);
            if (deeper) {
                return deeper;
            }
        }
        return null;
    }

    async function loadContents(folderId: string | null): Promise<void> {
        loading.contents = true;
        try {
            const query = qs({
                folder_id: folderId ?? '',
                sort: sort.value,
                direction: direction.value,
            });
            const res = await api.get<FolderContents>(`/file-manager/contents?${query}`);
            contents.folder = res.folder;
            contents.folders = res.folders;
            contents.files = res.files;
            contents.stats = res.stats ?? { file_count: 0, total_size: 0 };
            currentFolderId.value = folderId;
            currentView.value = 'all';
            breadcrumb.value = folderId ? (findFolder(tree.value, folderId) ?? []) : [];
            clearSelection();
        } finally {
            loading.contents = false;
        }
    }

    async function refresh(): Promise<void> {
        await loadTree();
        await loadContents(currentFolderId.value);
    }

    function setSort(key: SortKey, dir: SortDirection = 'asc'): Promise<void> {
        sort.value = key;
        direction.value = dir;
        return loadContents(currentFolderId.value);
    }

    function toggleSortDirection(): Promise<void> {
        direction.value = direction.value === 'asc' ? 'desc' : 'asc';
        return loadContents(currentFolderId.value);
    }

    // ── Selection ────────────────────────────────────────────────
    function isSelected(type: 'folder' | 'file', id: string | number): boolean {
        return selectedKeys.value.has(selectionKey(type, id));
    }

    function toggleSelect(type: 'folder' | 'file', id: string | number): void {
        const key = selectionKey(type, id);
        const next = new Set(selectedKeys.value);
        if (next.has(key)) {
            next.delete(key);
        } else {
            next.add(key);
        }
        selectedKeys.value = next;
    }

    function setSelection(keys: SelectionKey[]): void {
        selectedKeys.value = new Set(keys);
    }

    function clearSelection(): void {
        selectedKeys.value = new Set();
    }

    function selectAll(): void {
        const all = new Set<SelectionKey>();
        for (const folder of contents.folders) {
            all.add(selectionKey('folder', folder.id));
        }
        for (const file of contents.files) {
            all.add(selectionKey('file', file.id));
        }
        selectedKeys.value = all;
    }

    const selectionCount = computed(() => selectedKeys.value.size);

    const selectedItems = computed<SelectedItem[]>(() => {
        const out: SelectedItem[] = [];
        for (const key of selectedKeys.value) {
            const [type, id] = key.split(':') as ['folder' | 'file', string];
            out.push({ type, id });
        }
        return out;
    });

    // ── View Refresh ─────────────────────────────────────────────
    /**
     * Mevcut görünümü yeniden yükler.
     * Sanal görünümlerde (favorites, trash) mutasyon sonrası loadContents(root)
     * çağrısı currentView'ı 'all'a düşürür — bu helper bunu önler.
     */
    async function refreshCurrentView(): Promise<void> {
        switch (currentView.value) {
            case 'favorites':
                await loadFavorites();
                break;
            case 'trash':
                await loadTrash();
                break;
            default:
                await loadContents(currentFolderId.value);
                break;
        }
    }

    // ── Mutations ────────────────────────────────────────────────
    async function createFolder(name: string, parentId: string | null = currentFolderId.value): Promise<void> {
        await api.post<FolderResponse>('/file-manager/folders', contextPayload({ name, parent_id: parentId }));
        await refresh();
    }

    async function renameFolder(folderId: string, name: string): Promise<void> {
        await api.patch<FolderResponse>(`/file-manager/folders/${folderId}`, contextPayload({ name }));
        await refresh();
    }

    async function renameFile(fileId: string | number, name: string): Promise<void> {
        await api.patch(`/file-manager/files/${fileId}`, contextPayload({ name }));
        await refreshCurrentView();
    }

    async function copyFile(fileId: string | number, targetFolderId: string | null = null): Promise<void> {
        await api.post(`/file-manager/files/${fileId}/copy`, contextPayload({ target_folder_id: targetFolderId }));
        await refreshCurrentView();
    }

    async function loadFavorites(): Promise<void> {
        loading.contents = true;
        try {
            const res = await api.get<FolderContents>(`/file-manager/favorites/contents?${contextQuery.value}`);
            contents.folder = res.folder;
            contents.folders = res.folders;
            contents.files = res.files;
            contents.stats = res.stats ?? { file_count: 0, total_size: 0 };
            currentFolderId.value = null;
            currentView.value = 'favorites';
            breadcrumb.value = [];
            clearSelection();
        } finally {
            loading.contents = false;
        }
    }

    async function addFavorite(favoritableType: 'folder' | 'file', favoritableId: string): Promise<void> {
        await api.post(
            '/file-manager/favorites',
            contextPayload({
                favoritable_type: favoritableType,
                favoritable_id: favoritableId,
            }),
        );
    }

    async function removeFavorite(favoritableType: 'folder' | 'file', favoritableId: string): Promise<void> {
        await api.delete(
            `/file-manager/favorites?${qs({
                favoritable_type: favoritableType,
                favoritable_id: favoritableId,
            })}`,
        );
    }

    async function toggleFavorite(
        favoritableType: 'folder' | 'file',
        favoritableId: string,
        currentlyFavorited: boolean,
    ): Promise<void> {
        // Çift tıklama koruması: aynı item için in-flight istek varsa no-op
        const pendingKey = `${favoritableType}:${favoritableId}`;
        if (favoritePending.value.has(pendingKey)) {
            return;
        }

        favoritePending.value.add(pendingKey);

        // Optimistic update: flip flag in current contents, revert on failure
        const list = favoritableType === 'folder' ? contents.folders : contents.files;
        const target = (list as Array<FolderSummary | FileItem>).find((item) =>
            favoritableType === 'folder'
                ? (item as FolderSummary).id === favoritableId
                : String((item as FileItem).id) === favoritableId,
        );
        if (target) target.is_favorited = !currentlyFavorited;

        try {
            if (currentlyFavorited) {
                await removeFavorite(favoritableType, favoritableId);
            } else {
                await addFavorite(favoritableType, favoritableId);
            }
            // Favoriler view'ındayken unfavorite edildiyse listeyi yenile
            if (currentView.value === 'favorites' && currentlyFavorited) {
                await loadFavorites();
            }
        } catch (err) {
            // Hata durumunda optimistic update'i geri al
            if (target) target.is_favorited = currentlyFavorited;
            throw err;
        } finally {
            favoritePending.value.delete(pendingKey);
        }
    }

    async function loadTrash(): Promise<void> {
        loading.contents = true;
        try {
            const res = await api.get<FolderContents>(`/file-manager/trash/contents?${contextQuery.value}`);
            contents.folder = res.folder;
            contents.folders = res.folders;
            contents.files = res.files;
            contents.stats = res.stats ?? { file_count: 0, total_size: 0 };
            currentFolderId.value = null;
            currentView.value = 'trash';
            breadcrumb.value = [];
            clearSelection();
        } finally {
            loading.contents = false;
        }
    }

    async function restoreItem(itemType: 'folder' | 'file', itemId: string): Promise<void> {
        await api.post(
            '/file-manager/items/restore',
            contextPayload({
                item_type: itemType,
                item_id: itemId,
            }),
        );
        if (currentView.value === 'trash') {
            await loadTrash();
        } else {
            await refresh();
        }
    }

    async function permanentlyDeleteItem(itemType: 'folder' | 'file', itemId: string): Promise<void> {
        await api.delete(
            `/file-manager/items/permanent?${qs({
                item_type: itemType,
                item_id: itemId,
            })}`,
        );
        if (currentView.value === 'trash') {
            await loadTrash();
        } else {
            await refresh();
        }
    }

    async function deleteFolder(folderId: string): Promise<void> {
        await api.delete(`/file-manager/folders/${folderId}?${contextQuery.value}`);
        if (currentFolderId.value === folderId) {
            currentFolderId.value = null;
        }
        await refresh();
    }

    async function deleteFile(mediaId: number | string): Promise<void> {
        await api.delete(`/file-manager/files/${mediaId}?${contextQuery.value}`);
        await refreshCurrentView();
    }

    async function bulkDelete(): Promise<void> {
        if (selectionCount.value === 0) return;
        await api.post('/file-manager/items/bulk-delete', contextPayload({ items: selectedItems.value }));
        clearSelection();
        await refresh();
    }

    async function bulkForceDelete(): Promise<void> {
        if (selectionCount.value === 0) return;
        await api.post(
            '/file-manager/items/bulk-delete',
            contextPayload({ items: selectedItems.value, force_delete: true }),
        );
        clearSelection();
        await refresh();
    }

    async function emptyTrash(): Promise<void> {
        await api.delete(`/file-manager/trash/empty?${contextQuery.value}`);
        await loadTrash();
    }

    async function moveItem(
        itemType: 'folder' | 'file',
        itemId: string | number,
        targetFolderId: string | null,
    ): Promise<void> {
        await api.patch(
            '/file-manager/items/move',
            contextPayload({
                item_type: itemType,
                item_id: String(itemId),
                target_folder_id: targetFolderId,
            }),
        );
        await refresh();
    }

    function updatePending(tempId: string, patch: Partial<PendingUpload>): void {
        pendingUploads.value = pendingUploads.value.map((p) => (p.tempId === tempId ? { ...p, ...patch } : p));
    }

    function removePending(tempId: string): void {
        pendingUploads.value = pendingUploads.value.filter((p) => p.tempId !== tempId);
    }

    function xsrfToken(): string {
        return decodeURIComponent(
            document.cookie
                .split('; ')
                .find((c) => c.startsWith('XSRF-TOKEN='))
                ?.split('=')[1] ?? '',
        );
    }

    function extractValidationMessage(envelope: unknown): string | null {
        if (!envelope || typeof envelope !== 'object') return null;
        const errors = (envelope as { errors?: Record<string, unknown> }).errors;
        if (!errors || typeof errors !== 'object') return null;
        for (const value of Object.values(errors)) {
            if (Array.isArray(value) && value.length > 0 && typeof value[0] === 'string') {
                return value[0];
            }
            if (typeof value === 'string') return value;
        }
        return null;
    }

    function uploadSingle(file: File, folderId: string | null, tempId: string): Promise<FileItem[]> {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('context', options.context);
            if (options.contextId) formData.append('context_id', options.contextId);
            if (folderId) formData.append('folder_id', folderId);
            formData.append('files[]', file);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/file-manager/files');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-XSRF-TOKEN', xsrfToken());
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.withCredentials = true;

            xhr.upload.addEventListener('progress', (event) => {
                if (!event.lengthComputable) return;
                const pct = Math.min(99, Math.round((event.loaded / event.total) * 100));
                updatePending(tempId, { progress: pct });
            });

            xhr.onload = () => {
                if (xhr.status === 413) {
                    reject(new Error('sk-file-manager.errors.too_large'));
                    return;
                }
                try {
                    const envelope = JSON.parse(xhr.responseText);
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve((envelope.data as UploadResponse).files);
                    } else {
                        reject(
                            new Error(
                                extractValidationMessage(envelope) ??
                                    envelope?.message ??
                                    `Upload failed (${xhr.status})`,
                            ),
                        );
                    }
                } catch {
                    reject(new Error(xhr.status === 0 ? 'Network error' : `Upload failed (${xhr.status})`));
                }
            };
            xhr.onerror = () => reject(new Error('Network error'));
            xhr.send(formData);
        });
    }

    async function uploadFiles(
        files: FileList | File[],
        folderId: string | null = currentFolderId.value,
    ): Promise<{ uploaded: FileItem[]; errors: string[] }> {
        const list = Array.from(files);
        if (list.length === 0) return { uploaded: [], errors: [] };

        const queued = list.map<PendingUpload>((file) => ({
            tempId: `pending:${generateTempId()}`,
            name: file.name,
            size: file.size,
            mimeType: file.type || 'application/octet-stream',
            progress: 0,
            error: null,
            folderId,
        }));
        pendingUploads.value = [...pendingUploads.value, ...queued];

        const uploaded: FileItem[] = [];
        const errors: string[] = [];

        const tasks = list.map(async (file, idx) => {
            const { tempId } = queued[idx];
            try {
                const result = await uploadSingle(file, folderId, tempId);
                updatePending(tempId, { progress: 100 });
                uploaded.push(...result);
            } catch (err) {
                const message = (err as Error).message ?? 'Upload failed';
                updatePending(tempId, { error: message });
                errors.push(message);
            }
        });
        await Promise.allSettled(tasks);

        for (const p of queued) {
            if (!p.error) removePending(p.tempId);
        }
        await refreshCurrentView();
        return { uploaded, errors };
    }

    function dismissPending(tempId: string): void {
        removePending(tempId);
    }

    return {
        tree,
        contents,
        currentFolderId,
        currentView,
        breadcrumb,
        loading,
        sort,
        direction,
        selectedKeys,
        selectionCount,
        selectedItems,
        pendingUploads,
        favoritePending,
        loadTree,
        loadContents,
        loadFavorites,
        loadTrash,
        restoreItem,
        permanentlyDeleteItem,
        addFavorite,
        removeFavorite,
        toggleFavorite,
        refresh,
        setSort,
        toggleSortDirection,
        isSelected,
        toggleSelect,
        setSelection,
        clearSelection,
        selectAll,
        createFolder,
        renameFolder,
        renameFile,
        copyFile,
        deleteFolder,
        deleteFile,
        bulkDelete,
        bulkForceDelete,
        emptyTrash,
        moveItem,
        uploadFiles,
        dismissPending,
    };
}

export type FileManagerStore = ReturnType<typeof useFileManager>;
