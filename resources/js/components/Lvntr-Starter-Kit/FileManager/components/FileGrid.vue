<script setup lang="ts">
    import { trans } from 'laravel-vue-i18n';
    import { computed, onBeforeUnmount, ref } from 'vue';
    import { folderPaletteAt, paletteForMime } from '../palette';
    import type { FileItem, FolderSummary, PendingUpload, SelectionKey, ViewMode } from '../types';

    interface Props {
        folders: FolderSummary[];
        files: FileItem[];
        pending?: PendingUpload[];
        loading?: boolean;
        emptyLabel?: string;
        isSelected: (type: 'folder' | 'file', id: string | number) => boolean;
        hasSelection?: boolean;
        viewMode?: ViewMode;
        searchActive?: boolean;
        readonly?: boolean;
    }

    const props = withDefaults(defineProps<Props>(), {
        pending: () => [],
        emptyLabel: '',
        hasSelection: false,
        viewMode: 'grid',
        searchActive: false,
        readonly: false,
    });
    const emit = defineEmits<{
        (e: 'open-folder', folderId: string): void;
        (e: 'open-file', file: FileItem): void;
        (e: 'context-folder', event: MouseEvent, folder: FolderSummary): void;
        (e: 'context-file', event: MouseEvent, file: FileItem): void;
        (e: 'context-empty', event: MouseEvent): void;
        (e: 'toggle-select', type: 'folder' | 'file', id: string | number, event: MouseEvent): void;
        (e: 'check-toggle', type: 'folder' | 'file', id: string | number): void;
        (e: 'set-selection', keys: SelectionKey[]): void;
        (e: 'clear-selection'): void;
        (e: 'download-file', file: FileItem): void;
        (e: 'delete-file', file: FileItem): void;
        (e: 'dismiss-pending', tempId: string): void;
        (e: 'drop-on-folder', targetFolderId: string, event: DragEvent): void;
        (e: 'internal-drag-start', type: 'folder' | 'file', id: string | number): void;
        (e: 'internal-drag-end'): void;
        (e: 'upload'): void;
        (e: 'toggle-folder-favorite', folder: FolderSummary): void;
        (e: 'toggle-file-favorite', file: FileItem): void;
    }>();

    const isEmpty = computed(
        () => !props.loading && props.folders.length === 0 && props.files.length === 0 && props.pending.length === 0,
    );

    function humanSize(bytes: number): string {
        if (!bytes) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / 1024 ** i;
        return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
    }

    function relativeDate(iso: string | null): string {
        if (!iso) return '';
        const date = new Date(iso);
        const now = Date.now();
        const diff = Math.max(0, now - date.getTime());
        const mins = Math.floor(diff / 60000);
        if (mins < 60) return `${mins}m`;
        const hours = Math.floor(mins / 60);
        if (hours < 24) return `${hours}h`;
        const days = Math.floor(hours / 24);
        if (days === 1) return 'yesterday';
        if (days < 7) return `${days}d`;
        return date.toLocaleDateString();
    }

    /** Rozet için uzantı — dosya adından, yoksa mime alt türünden. */
    function extOf(file: FileItem): string {
        const dot = file.file_name.lastIndexOf('.');
        if (dot > 0 && dot < file.file_name.length - 1) {
            return file.file_name.slice(dot + 1).toUpperCase().slice(0, 5);
        }
        return (file.mime_type.split('/')[1] ?? 'FILE').toUpperCase().slice(0, 5);
    }

    function isImage(mime: string): boolean {
        return mime.startsWith('image/');
    }

    function isVideo(mime: string): boolean {
        return mime.startsWith('video/');
    }

    // Kullanım çubuğu — en büyük kardeş klasöre oranla görsel gösterge.
    const maxFolderSize = computed(() =>
        props.folders.reduce((max, f) => Math.max(max, f.total_size ?? 0), 0),
    );

    function folderBarWidth(folder: FolderSummary): string {
        if (maxFolderSize.value <= 0) return '0%';
        return `${Math.min(100, Math.round(((folder.total_size ?? 0) / maxFolderSize.value) * 100))}%`;
    }

    function selectAllFiles(): void {
        emit('set-selection', props.files.map((f) => `file:${String(f.id)}` as SelectionKey));
    }

    // ── Rubber-band selection ────────────────────────────────────
    const gridRef = ref<HTMLElement | null>(null);
    const band = ref<{ x: number; y: number; w: number; h: number } | null>(null);
    const isDragging = ref(false);

    interface DragState {
        startX: number;
        startY: number;
        additive: boolean;
    }

    let drag: DragState | null = null;

    function onGridMouseDown(event: MouseEvent): void {
        if (event.button !== 0) return;
        const target = event.target as HTMLElement;
        if (target.closest('.fm-tile') || target.closest('.fm-row') || target.closest('button, a, input')) return;

        const el = gridRef.value;
        if (!el) return;
        const rect = el.getBoundingClientRect();
        const startX = event.clientX - rect.left + el.scrollLeft;
        const startY = event.clientY - rect.top + el.scrollTop;

        drag = {
            startX,
            startY,
            additive: event.shiftKey || event.metaKey || event.ctrlKey,
        };
        if (!drag.additive) emit('clear-selection');

        window.addEventListener('mousemove', onWindowMouseMove);
        window.addEventListener('mouseup', onWindowMouseUp, { once: true });
    }

    function onWindowMouseMove(event: MouseEvent): void {
        if (!drag || !gridRef.value) return;
        const el = gridRef.value;
        const rect = el.getBoundingClientRect();
        const currentX = event.clientX - rect.left + el.scrollLeft;
        const currentY = event.clientY - rect.top + el.scrollTop;

        const x = Math.min(drag.startX, currentX);
        const y = Math.min(drag.startY, currentY);
        const w = Math.abs(currentX - drag.startX);
        const h = Math.abs(currentY - drag.startY);

        if (!isDragging.value && (w > 4 || h > 4)) isDragging.value = true;
        band.value = { x, y, w, h };

        if (isDragging.value) {
            const hits = computeHits(el, { x, y, w, h });
            emit('set-selection', hits);
        }
    }

    function computeHits(root: HTMLElement, box: { x: number; y: number; w: number; h: number }): SelectionKey[] {
        const hits: SelectionKey[] = [];
        const tiles = root.querySelectorAll<HTMLElement>('[data-fm-key]');
        const rootRect = root.getBoundingClientRect();

        tiles.forEach((tile) => {
            const tileRect = tile.getBoundingClientRect();
            const tx = tileRect.left - rootRect.left + root.scrollLeft;
            const ty = tileRect.top - rootRect.top + root.scrollTop;
            const intersects =
                box.x < tx + tileRect.width && box.x + box.w > tx && box.y < ty + tileRect.height && box.y + box.h > ty;

            if (intersects) {
                const key = tile.dataset.fmKey as SelectionKey | undefined;
                if (key) hits.push(key);
            }
        });
        return hits;
    }

    function onWindowMouseUp(): void {
        band.value = null;
        isDragging.value = false;
        drag = null;
        window.removeEventListener('mousemove', onWindowMouseMove);
    }

    onBeforeUnmount(() => {
        window.removeEventListener('mousemove', onWindowMouseMove);
    });

    // ── Click / open logic ───────────────────────────────────────
    function onFolderTileClick(folder: FolderSummary, event: MouseEvent): void {
        if (event.ctrlKey || event.metaKey || event.shiftKey) {
            emit('toggle-select', 'folder', folder.id, event);
        }
    }

    function onFolderDblClick(folder: FolderSummary): void {
        emit('open-folder', folder.id);
    }

    function onFileTileClick(file: FileItem, event: MouseEvent): void {
        if (event.ctrlKey || event.metaKey || event.shiftKey) {
            emit('toggle-select', 'file', file.id, event);
            return;
        }
        emit('open-file', file);
    }

    // ── Internal drag-drop (move) ────────────────────────────────
    const dropTargetId = ref<string | null>(null);

    function onTileDragStart(type: 'folder' | 'file', id: string | number, event: DragEvent): void {
        if (props.readonly) {
            event.preventDefault();
            return;
        }
        if (!event.dataTransfer) return;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('application/x-fm-item', JSON.stringify({ type, id: String(id) }));
        emit('internal-drag-start', type, id);
    }

    function onTileDragEnd(): void {
        dropTargetId.value = null;
        emit('internal-drag-end');
    }

    function isInternalDrag(event: DragEvent): boolean {
        return !!event.dataTransfer?.types.includes('application/x-fm-item');
    }

    function onFolderDragOver(folder: FolderSummary, event: DragEvent): void {
        if (!isInternalDrag(event)) return;
        event.preventDefault();
        event.stopPropagation();
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
        dropTargetId.value = folder.id;
    }

    function onFolderDragLeave(folder: FolderSummary): void {
        if (dropTargetId.value === folder.id) dropTargetId.value = null;
    }

    function onFolderDrop(folder: FolderSummary, event: DragEvent): void {
        if (props.readonly) return;
        if (!isInternalDrag(event)) return;
        event.preventDefault();
        event.stopPropagation();
        dropTargetId.value = null;
        emit('drop-on-folder', folder.id, event);
    }

    function onGridContextMenu(event: MouseEvent): void {
        const target = event.target as HTMLElement;
        if (target.closest('.fm-tile') || target.closest('.fm-row') || target.closest('button, a, input')) return;
        event.preventDefault();
        emit('clear-selection');
        emit('context-empty', event);
    }

    // Hover aksiyon butonu — önizleme üstü beyaz kareler.
    const actBtnClass =
        'grid h-7 w-7 place-items-center rounded-[5px] border border-surface-200 bg-white/95 text-surface-500 transition-colors hover:text-primary-600 dark:border-surface-600 dark:bg-surface-900/90 dark:text-surface-300 dark:hover:text-primary-300';
    // Liste satırı aksiyon butonu — çerçevesiz mini ikon.
    const rowActBtnClass =
        'grid h-7 w-7 place-items-center rounded-[5px] text-surface-400 transition-colors hover:bg-surface-200/60 hover:text-surface-700 dark:hover:bg-surface-700/60 dark:hover:text-surface-200';
</script>

<template>
    <div
        ref="gridRef"
        class="fm-file-grid relative flex w-full flex-1 flex-col gap-6"
        @mousedown="onGridMouseDown"
        @contextmenu="onGridContextMenu"
    >
        <!-- Boş: arama sonuçsuz -->
        <div
            v-if="isEmpty && searchActive"
            class="fm-empty flex flex-1 flex-col items-center justify-center gap-2.5 px-6 py-14 text-center"
        >
            <span
                class="mb-1 grid h-16 w-16 place-items-center rounded-full bg-primary-50 text-primary-500 dark:bg-primary-950/40 dark:text-primary-300"
            >
                <i class="pi pi-search" style="font-size: 1.6rem" />
            </span>
            <h3 class="text-[15px] font-semibold text-surface-900 dark:text-surface-100">
                {{ emptyLabel }}
            </h3>
        </div>

        <!-- Boş: klasör gerçekten boş -->
        <div v-else-if="isEmpty" class="fm-empty flex flex-1 flex-col items-center justify-center gap-2.5 px-6 py-14 text-center">
            <span
                class="mb-1 grid h-16 w-16 place-items-center rounded-full bg-primary-50 text-primary-500 dark:bg-primary-950/40 dark:text-primary-300"
            >
                <i class="pi pi-cloud-upload" style="font-size: 1.6rem" />
            </span>
            <h3 class="text-[15px] font-semibold text-surface-900 dark:text-surface-100">
                {{ trans('sk-file-manager.labels.empty_folder_title') }}
            </h3>
            <p class="max-w-[360px] text-[12.5px] leading-relaxed text-surface-400 dark:text-surface-500">
                {{ trans('sk-file-manager.labels.empty_folder_subtitle') }}
            </p>
        </div>

        <template v-else>
            <!-- Klasörler -->
            <section v-if="folders.length > 0">
                <div class="mb-3 flex items-baseline gap-2">
                    <h3 class="text-[13px] font-semibold tracking-tight text-surface-900 dark:text-surface-100">
                        {{ trans('sk-file-manager.labels.folders_section') }}
                    </h3>
                    <span class="text-xs text-surface-400 dark:text-surface-500">{{ folders.length }}</span>
                </div>

                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(248px, 1fr))">
                    <div
                        v-for="(folder, folderIndex) in folders"
                        :key="`folder-${folder.id}`"
                        role="button"
                        tabindex="0"
                        :draggable="!props.readonly"
                        :data-fm-key="`folder:${folder.id}`"
                        class="fm-tile group relative flex flex-col gap-4 overflow-hidden rounded-[5px] border p-5 text-left transition-colors"
                        :class="[
                            isSelected('folder', folder.id)
                                ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-950/30'
                                : 'border-surface-200 bg-surface-50 hover:border-surface-300 hover:bg-surface-0 dark:border-surface-700 dark:bg-surface-900 dark:hover:border-surface-500/50 dark:hover:bg-surface-800/50',
                            dropTargetId === folder.id ? 'ring-2 ring-primary-500 ring-offset-2 dark:ring-offset-surface-900' : '',
                        ]"
                        @click="(ev) => onFolderTileClick(folder, ev)"
                        @dblclick="onFolderDblClick(folder)"
                        @keydown.enter.prevent="onFolderDblClick(folder)"
                        @contextmenu.prevent="(ev) => emit('context-folder', ev, folder)"
                        @dragstart="(ev) => onTileDragStart('folder', folder.id, ev)"
                        @dragend="onTileDragEnd"
                        @dragover="(ev) => onFolderDragOver(folder, ev)"
                        @dragleave="() => onFolderDragLeave(folder)"
                        @drop="(ev) => onFolderDrop(folder, ev)"
                    >
                        <!-- Renk kenarı -->
                        <span
                            class="pointer-events-none absolute inset-y-0 left-0 w-[3px] opacity-85"
                            :class="folderPaletteAt(folderIndex).solid"
                        />

                        <div class="flex items-center gap-2.5">
                            <span
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-[5px] text-white"
                                :class="folderPaletteAt(folderIndex).solid"
                            >
                                <i class="pi pi-folder" style="font-size: 1.05rem" />
                            </span>
                            <span
                                class="min-w-0 flex-1 truncate text-[13.5px] font-semibold text-surface-900 dark:text-surface-100"
                                :title="folder.name"
                            >
                                {{ folder.name }}
                            </span>

                            <!-- Seçim -->
                            <button
                                type="button"
                                class="grid h-5 w-5 shrink-0 place-items-center rounded-[5px] border-[1.5px] transition-all"
                                :class="
                                    isSelected('folder', folder.id)
                                        ? 'border-primary-500 bg-primary-500 text-white opacity-100'
                                        : 'border-surface-300 bg-surface-0 text-transparent opacity-0 hover:border-primary-400 group-hover:opacity-100 dark:border-surface-600 dark:bg-surface-900'
                                "
                                :aria-pressed="isSelected('folder', folder.id)"
                                @click.stop="emit('check-toggle', 'folder', folder.id)"
                            >
                                <i class="pi pi-check" style="font-size: 0.65rem" />
                            </button>

                            <!-- Favori -->
                            <button
                                type="button"
                                class="grid h-7 w-7 shrink-0 place-items-center rounded-[5px] transition-all"
                                :class="[
                                    folder.is_favorited
                                        ? 'text-rose-500 opacity-100'
                                        : 'text-surface-400 opacity-0 group-hover:opacity-100 hover:text-rose-500',
                                    props.readonly ? 'cursor-not-allowed' : '',
                                ]"
                                :disabled="props.readonly"
                                :aria-label="
                                    trans(
                                        folder.is_favorited
                                            ? 'sk-file-manager.labels.remove_from_favorites'
                                            : 'sk-file-manager.labels.add_to_favorites',
                                    )
                                "
                                :aria-pressed="folder.is_favorited === true"
                                @click.stop="!props.readonly && emit('toggle-folder-favorite', folder)"
                            >
                                <i :class="folder.is_favorited ? 'pi pi-heart-fill' : 'pi pi-heart'" style="font-size: 0.8rem" />
                            </button>

                            <!-- Menü -->
                            <button
                                type="button"
                                class="grid h-7 w-7 shrink-0 place-items-center rounded-[5px] text-surface-400 opacity-0 transition-all hover:bg-surface-200/60 hover:text-surface-700 group-hover:opacity-100 dark:hover:bg-surface-700/60 dark:hover:text-surface-200"
                                aria-haspopup="menu"
                                @click.stop="(ev) => emit('context-folder', ev, folder)"
                            >
                                <i class="pi pi-ellipsis-v" style="font-size: 0.8rem" />
                            </button>
                        </div>

                        <div class="flex items-center justify-between text-[11.5px] text-surface-400 dark:text-surface-500">
                            <span>{{ trans('sk-file-manager.labels.stats.item_count', { count: String(folder.file_count ?? 0) }) }}</span>
                            <span>{{ humanSize(folder.total_size ?? 0) }}</span>
                        </div>

                        <div class="h-1 overflow-hidden rounded-full bg-surface-200 dark:bg-surface-700">
                            <span
                                class="block h-full rounded-full opacity-65"
                                :class="folderPaletteAt(folderIndex).solid"
                                :style="{ width: folderBarWidth(folder) }"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Dosyalar -->
            <section v-if="files.length > 0 || pending.length > 0">
                <div class="mb-3 flex items-baseline gap-2">
                    <h3 class="text-[13px] font-semibold tracking-tight text-surface-900 dark:text-surface-100">
                        {{ trans('sk-file-manager.labels.files_section') }}
                    </h3>
                    <span class="text-xs text-surface-400 dark:text-surface-500">{{ files.length + pending.length }}</span>
                    <span class="flex-1" />
                    <button
                        v-if="!hasSelection && files.length > 0"
                        type="button"
                        class="text-xs text-surface-400 transition-colors hover:text-primary-600 dark:text-surface-500 dark:hover:text-primary-300"
                        @click="selectAllFiles"
                    >
                        {{ trans('sk-file-manager.labels.select_all') }}
                    </button>
                </div>

                <!-- Izgara -->
                <div
                    v-if="viewMode === 'grid'"
                    class="grid gap-3.5"
                    style="grid-template-columns: repeat(auto-fill, minmax(184px, 1fr))"
                >
                    <div
                        v-for="file in files"
                        :key="`file-${file.id}`"
                        role="button"
                        tabindex="0"
                        :draggable="!props.readonly"
                        :data-fm-key="`file:${file.id}`"
                        class="fm-tile group relative flex flex-col overflow-hidden rounded-[5px] border text-left transition-colors"
                        :class="
                            isSelected('file', file.id)
                                ? 'border-primary-500'
                                : 'border-surface-200 bg-surface-50 hover:border-surface-300 dark:border-surface-700 dark:bg-surface-900 dark:hover:border-surface-500/50'
                        "
                        @click="(ev) => onFileTileClick(file, ev)"
                        @keydown.enter.prevent="emit('open-file', file)"
                        @contextmenu.prevent="(ev) => emit('context-file', ev, file)"
                        @dragstart="(ev) => onTileDragStart('file', file.id, ev)"
                        @dragend="onTileDragEnd"
                    >
                        <!-- Önizleme -->
                        <div
                            class="relative grid aspect-[5/4] place-items-center overflow-hidden border-b border-surface-200 dark:border-surface-700"
                            :class="isImage(file.mime_type) || isVideo(file.mime_type) ? '' : paletteForMime(file.mime_type).previewTint"
                        >
                            <img
                                v-if="isImage(file.mime_type)"
                                :src="file.url"
                                :alt="file.file_name"
                                class="h-full w-full object-cover"
                            >
                            <div
                                v-else-if="isVideo(file.mime_type)"
                                class="grid h-full w-full place-items-center bg-gradient-to-br from-[#232a3d] to-[#11141d]"
                            >
                                <span class="grid h-10 w-10 place-items-center rounded-full bg-white/95 pl-[3px] text-surface-900">
                                    <i class="pi pi-play" style="font-size: 0.8rem" />
                                </span>
                            </div>
                            <span
                                v-else
                                class="grid h-14 w-14 place-items-center rounded-[5px] text-white"
                                :class="paletteForMime(file.mime_type).solid"
                            >
                                <i :class="paletteForMime(file.mime_type).icon" style="font-size: 1.5rem" />
                            </span>

                            <!-- Uzantı rozeti -->
                            <span
                                class="absolute left-2 top-2 rounded-[5px] border border-surface-200 bg-white/95 px-1.5 py-0.5 font-mono text-[9.5px] font-bold tracking-wide text-surface-700 dark:border-surface-600 dark:bg-surface-900/85 dark:text-surface-200"
                            >
                                {{ extOf(file) }}
                            </span>

                            <!-- Seçim -->
                            <button
                                type="button"
                                class="absolute left-2 top-2 z-10 grid h-5 w-5 place-items-center rounded-[5px] border-[1.5px] transition-all"
                                :class="
                                    isSelected('file', file.id)
                                        ? 'border-primary-500 bg-primary-500 text-white opacity-100'
                                        : 'border-surface-300 bg-surface-0 text-transparent opacity-0 hover:border-primary-400 group-hover:opacity-100 dark:border-surface-600 dark:bg-surface-900'
                                "
                                :aria-pressed="isSelected('file', file.id)"
                                @click.stop="emit('check-toggle', 'file', file.id)"
                            >
                                <i class="pi pi-check" style="font-size: 0.65rem" />
                            </button>

                            <!-- Hover aksiyonları -->
                            <div
                                class="absolute right-2 top-2 flex -translate-y-0.5 gap-1.5 opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100"
                            >
                                <button
                                    type="button"
                                    :class="actBtnClass"
                                    :aria-label="trans('sk-file-manager.labels.preview')"
                                    @click.stop="emit('open-file', file)"
                                >
                                    <i class="pi pi-eye" style="font-size: 0.75rem" />
                                </button>
                                <button
                                    type="button"
                                    :class="[actBtnClass, file.is_favorited ? '!text-rose-500' : '']"
                                    :disabled="props.readonly"
                                    :aria-label="
                                        trans(
                                            file.is_favorited
                                                ? 'sk-file-manager.labels.remove_from_favorites'
                                                : 'sk-file-manager.labels.add_to_favorites',
                                        )
                                    "
                                    :aria-pressed="file.is_favorited === true"
                                    @click.stop="!props.readonly && emit('toggle-file-favorite', file)"
                                >
                                    <i :class="file.is_favorited ? 'pi pi-heart-fill' : 'pi pi-heart'" style="font-size: 0.75rem" />
                                </button>
                                <button
                                    type="button"
                                    :class="actBtnClass"
                                    :aria-label="trans('sk-file-manager.labels.download')"
                                    @click.stop="emit('download-file', file)"
                                >
                                    <i class="pi pi-download" style="font-size: 0.75rem" />
                                </button>
                                <button
                                    v-if="!props.readonly"
                                    type="button"
                                    :class="[actBtnClass, 'hover:!text-rose-600 dark:hover:!text-rose-400']"
                                    :aria-label="trans('sk-file-manager.labels.delete')"
                                    @click.stop="emit('delete-file', file)"
                                >
                                    <i class="pi pi-trash" style="font-size: 0.75rem" />
                                </button>
                            </div>
                        </div>

                        <!-- Alt bilgi -->
                        <div class="flex items-center gap-2 px-2.5 py-2">
                            <span
                                class="grid h-6 w-6 shrink-0 place-items-center rounded-[5px]"
                                :class="[paletteForMime(file.mime_type).tint, paletteForMime(file.mime_type).text]"
                            >
                                <i :class="paletteForMime(file.mime_type).icon" style="font-size: 0.7rem" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div
                                    class="truncate text-[12.5px] font-semibold text-surface-900 dark:text-surface-100"
                                    :title="file.file_name"
                                >
                                    {{ file.file_name }}
                                </div>
                                <div class="text-[11px] text-surface-400 dark:text-surface-500">
                                    {{ humanSize(file.size) }}<template v-if="file.created_at"> · {{ relativeDate(file.created_at) }}</template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Yüklenen dosya kartları -->
                    <div
                        v-for="item in pending"
                        :key="item.tempId"
                        class="fm-tile fm-pending-tile relative flex flex-col overflow-hidden rounded-[5px] border text-left"
                        :class="
                            item.error
                                ? 'border-rose-300 dark:border-rose-800'
                                : 'border-surface-200 bg-surface-50 dark:border-surface-700 dark:bg-surface-900'
                        "
                    >
                        <div
                            class="relative grid aspect-[5/4] place-items-center overflow-hidden border-b border-surface-200 dark:border-surface-700"
                            :class="item.error ? 'bg-rose-50 dark:bg-rose-950/40' : paletteForMime(item.mimeType).previewTint"
                        >
                            <i
                                v-if="item.error"
                                class="pi pi-exclamation-triangle text-rose-500"
                                style="font-size: 2rem"
                            />
                            <span
                                v-else
                                class="grid h-14 w-14 place-items-center rounded-[5px] text-white"
                                :class="paletteForMime(item.mimeType).solid"
                            >
                                <i :class="paletteForMime(item.mimeType).icon" style="font-size: 1.5rem" />
                            </span>
                            <div v-if="!item.error" class="absolute inset-x-0 bottom-0 h-1 bg-surface-200/70 dark:bg-surface-700/70">
                                <div
                                    class="h-full bg-primary-500 transition-all duration-200"
                                    :style="{ width: `${item.progress}%` }"
                                />
                            </div>
                        </div>
                        <div class="flex items-center gap-2 px-2.5 py-2">
                            <i
                                class="pi shrink-0"
                                :class="
                                    item.error
                                        ? 'pi-times-circle text-rose-500'
                                        : `pi-spin pi-spinner ${paletteForMime(item.mimeType).text}`
                                "
                                style="font-size: 0.85rem"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[12.5px] font-semibold text-surface-900 dark:text-surface-100" :title="item.name">
                                    {{ item.name }}
                                </div>
                                <div
                                    class="text-[11px]"
                                    :class="item.error ? 'text-rose-500' : 'text-surface-400 dark:text-surface-500'"
                                >
                                    <template v-if="item.error">
                                        {{ item.error }}
                                    </template>
                                    <template v-else>
                                        {{ trans('sk-file-manager.labels.uploading') }} · {{ item.progress }}%
                                    </template>
                                </div>
                            </div>
                            <button
                                v-if="item.error"
                                type="button"
                                class="grid h-7 w-7 shrink-0 place-items-center rounded-[5px] text-surface-400 transition-colors hover:bg-surface-200/60 hover:text-surface-700 dark:hover:bg-surface-700/60"
                                :title="trans('sk-file-manager.labels.dismiss')"
                                @click.stop="emit('dismiss-pending', item.tempId)"
                            >
                                <i class="pi pi-times" style="font-size: 0.8rem" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Liste -->
                <div
                    v-else
                    class="divide-y divide-surface-200 overflow-hidden rounded-[5px] border border-surface-200 dark:divide-surface-700 dark:border-surface-700"
                >
                    <div
                        v-for="file in files"
                        :key="`row-file-${file.id}`"
                        :data-fm-key="`file:${file.id}`"
                        :draggable="!props.readonly"
                        class="fm-row group flex cursor-pointer items-center gap-3 px-3.5 py-2.5 transition-colors"
                        :class="
                            isSelected('file', file.id)
                                ? 'bg-primary-50 dark:bg-primary-950/30'
                                : 'bg-surface-50 hover:bg-surface-100/70 dark:bg-surface-900 dark:hover:bg-surface-800/50'
                        "
                        @click="(ev) => onFileTileClick(file, ev)"
                        @contextmenu.prevent="(ev) => emit('context-file', ev, file)"
                        @dragstart="(ev) => onTileDragStart('file', file.id, ev)"
                        @dragend="onTileDragEnd"
                    >
                        <button
                            type="button"
                            class="grid h-5 w-5 shrink-0 place-items-center rounded-[5px] border-[1.5px] transition-all"
                            :class="
                                isSelected('file', file.id)
                                    ? 'border-primary-500 bg-primary-500 text-white opacity-100'
                                    : 'border-surface-300 bg-surface-0 text-transparent opacity-0 hover:border-primary-400 group-hover:opacity-100 dark:border-surface-600 dark:bg-surface-900'
                            "
                            :aria-pressed="isSelected('file', file.id)"
                            @click.stop="emit('check-toggle', 'file', file.id)"
                        >
                            <i class="pi pi-check" style="font-size: 0.65rem" />
                        </button>

                        <span
                            class="grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-[5px]"
                            :class="[paletteForMime(file.mime_type).tint, paletteForMime(file.mime_type).text]"
                        >
                            <img
                                v-if="isImage(file.mime_type)"
                                :src="file.url"
                                :alt="file.file_name"
                                class="h-full w-full object-cover"
                            >
                            <i v-else :class="paletteForMime(file.mime_type).icon" style="font-size: 0.85rem" />
                        </span>

                        <span
                            class="min-w-0 flex-1 truncate text-[13px] font-semibold text-surface-900 dark:text-surface-100"
                            :title="file.file_name"
                        >
                            {{ file.file_name }}
                        </span>
                        <span class="hidden w-14 shrink-0 font-mono text-[11px] text-surface-400 dark:text-surface-500 md:block">
                            {{ extOf(file) }}
                        </span>
                        <span class="w-20 shrink-0 text-right text-xs text-surface-400 dark:text-surface-500">
                            {{ humanSize(file.size) }}
                        </span>
                        <span class="hidden w-24 shrink-0 text-right text-xs text-surface-400 dark:text-surface-500 lg:block">
                            {{ relativeDate(file.created_at) }}
                        </span>

                        <div class="flex shrink-0 gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                            <button
                                type="button"
                                :class="rowActBtnClass"
                                :aria-label="trans('sk-file-manager.labels.preview')"
                                @click.stop="emit('open-file', file)"
                            >
                                <i class="pi pi-eye" style="font-size: 0.75rem" />
                            </button>
                            <button
                                type="button"
                                :class="[rowActBtnClass, file.is_favorited ? '!text-rose-500 opacity-100' : '']"
                                :disabled="props.readonly"
                                :aria-pressed="file.is_favorited === true"
                                @click.stop="!props.readonly && emit('toggle-file-favorite', file)"
                            >
                                <i :class="file.is_favorited ? 'pi pi-heart-fill' : 'pi pi-heart'" style="font-size: 0.75rem" />
                            </button>
                            <button
                                type="button"
                                :class="rowActBtnClass"
                                :aria-label="trans('sk-file-manager.labels.download')"
                                @click.stop="emit('download-file', file)"
                            >
                                <i class="pi pi-download" style="font-size: 0.75rem" />
                            </button>
                            <button
                                v-if="!props.readonly"
                                type="button"
                                :class="[rowActBtnClass, 'hover:!text-rose-600 dark:hover:!text-rose-400']"
                                :aria-label="trans('sk-file-manager.labels.delete')"
                                @click.stop="emit('delete-file', file)"
                            >
                                <i class="pi pi-trash" style="font-size: 0.75rem" />
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <div
            v-if="band"
            class="pointer-events-none absolute border-2 border-primary-500/80 bg-primary-500/10"
            :style="{
                left: `${band.x}px`,
                top: `${band.y}px`,
                width: `${band.w}px`,
                height: `${band.h}px`,
            }"
        />
    </div>
</template>

<style scoped>
    .fm-tile {
        cursor: pointer;
        user-select: none;
    }
    .fm-pending-tile {
        cursor: default;
    }
</style>
