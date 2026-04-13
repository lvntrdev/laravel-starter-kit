<script setup lang="ts">
    import { computed, onBeforeUnmount, ref } from 'vue';
    import type { FileItem, FolderSummary, SelectionKey } from '../types';

    interface Props {
        folders: FolderSummary[];
        files: FileItem[];
        loading?: boolean;
        emptyLabel?: string;
        isSelected: (type: 'folder' | 'file', id: string | number) => boolean;
    }

    const props = defineProps<Props>();
    const emit = defineEmits<{
        (e: 'open-folder', folderId: string): void;
        (e: 'open-file', file: FileItem): void;
        (e: 'context-folder', event: MouseEvent, folder: FolderSummary): void;
        (e: 'context-file', event: MouseEvent, file: FileItem): void;
        (e: 'context-empty', event: MouseEvent): void;
        (e: 'toggle-select', type: 'folder' | 'file', id: string | number, event: MouseEvent): void;
        (e: 'set-selection', keys: SelectionKey[]): void;
        (e: 'clear-selection'): void;
        (e: 'download-file', file: FileItem): void;
    }>();

    const isEmpty = computed(() => !props.loading && props.folders.length === 0 && props.files.length === 0);

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

    // ── Mime → color palette & icon ──────────────────────────────
    type Palette = { preview: string; icon: string; iconColor: string; badge: string; iconClass: string };

    function paletteFor(mime: string): Palette {
        if (mime.startsWith('image/')) {
            return {
                preview: 'bg-slate-900',
                icon: 'pi pi-image',
                iconColor: 'text-pink-400',
                badge: 'bg-pink-500',
                iconClass: 'text-pink-500',
            };
        }
        if (mime === 'application/pdf') {
            return {
                preview: 'bg-rose-50 dark:bg-rose-950/40',
                icon: 'pi pi-file-pdf',
                iconColor: 'text-rose-500',
                badge: 'bg-rose-500',
                iconClass: 'text-rose-500',
            };
        }
        if (mime.startsWith('video/')) {
            return {
                preview: 'bg-slate-800',
                icon: 'pi pi-video',
                iconColor: 'text-white',
                badge: 'bg-purple-500',
                iconClass: 'text-purple-500',
            };
        }
        if (mime.startsWith('audio/')) {
            return {
                preview: 'bg-amber-50 dark:bg-amber-950/40',
                icon: 'pi pi-volume-up',
                iconColor: 'text-amber-500',
                badge: 'bg-amber-500',
                iconClass: 'text-amber-500',
            };
        }
        if (mime.includes('word')) {
            return {
                preview: 'bg-blue-50 dark:bg-blue-950/40',
                icon: 'pi pi-file-word',
                iconColor: 'text-blue-500',
                badge: 'bg-blue-500',
                iconClass: 'text-blue-500',
            };
        }
        if (mime.includes('excel') || mime.includes('spreadsheet')) {
            return {
                preview: 'bg-emerald-50 dark:bg-emerald-950/40',
                icon: 'pi pi-file-excel',
                iconColor: 'text-emerald-500',
                badge: 'bg-emerald-500',
                iconClass: 'text-emerald-500',
            };
        }
        if (mime.includes('zip') || mime.includes('compressed') || mime.includes('archive')) {
            return {
                preview: 'bg-amber-100 dark:bg-amber-950/40',
                icon: 'pi pi-box',
                iconColor: 'text-amber-600',
                badge: 'bg-amber-600',
                iconClass: 'text-amber-600',
            };
        }
        if (mime.startsWith('text/')) {
            return {
                preview: 'bg-slate-50 dark:bg-slate-900',
                icon: 'pi pi-file-edit',
                iconColor: 'text-slate-500',
                badge: 'bg-slate-500',
                iconClass: 'text-slate-500',
            };
        }
        return {
            preview: 'bg-slate-50 dark:bg-slate-900',
            icon: 'pi pi-file',
            iconColor: 'text-slate-500',
            badge: 'bg-slate-500',
            iconClass: 'text-slate-500',
        };
    }

    // Deterministic folder color from name hash
    const folderPalettes = [
        { bg: 'bg-indigo-50 dark:bg-indigo-950/40', icon: 'text-indigo-500' },
        { bg: 'bg-rose-50 dark:bg-rose-950/40', icon: 'text-rose-500' },
        { bg: 'bg-emerald-50 dark:bg-emerald-950/40', icon: 'text-emerald-500' },
        { bg: 'bg-amber-50 dark:bg-amber-950/40', icon: 'text-amber-500' },
        { bg: 'bg-sky-50 dark:bg-sky-950/40', icon: 'text-sky-500' },
        { bg: 'bg-purple-50 dark:bg-purple-950/40', icon: 'text-purple-500' },
    ];

    function folderPalette(id: string): { bg: string; icon: string } {
        let hash = 0;
        for (let i = 0; i < id.length; i++) {
            hash = (hash * 31 + id.charCodeAt(i)) >>> 0;
        }
        return folderPalettes[hash % folderPalettes.length];
    }

    function isImage(mime: string): boolean {
        return mime.startsWith('image/');
    }

    function isVideo(mime: string): boolean {
        return mime.startsWith('video/');
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
        if (target.closest('.fm-tile') || target.closest('button, a, input')) return;

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

    function onGridContextMenu(event: MouseEvent): void {
        const target = event.target as HTMLElement;
        if (target.closest('.fm-tile') || target.closest('button, a, input')) return;
        event.preventDefault();
        emit('clear-selection');
        emit('context-empty', event);
    }
</script>

<template>
    <div
        ref="gridRef"
        class="fm-file-grid relative h-full w-full overflow-auto p-5"
        @mousedown="onGridMouseDown"
        @contextmenu="onGridContextMenu"
    >
        <div v-if="isEmpty" class="text-muted-color flex h-48 items-center justify-center text-base">
            {{ emptyLabel ?? 'This folder is empty.' }}
        </div>

        <template v-else>
            <!-- Folders -->
            <div
                v-if="folders.length > 0"
                class="mb-5 grid gap-4"
                style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr))"
            >
                <div
                    v-for="folder in folders"
                    :key="`folder-${folder.id}`"
                    role="button"
                    tabindex="0"
                    :data-fm-key="`folder:${folder.id}`"
                    class="fm-tile fm-folder-tile group relative flex flex-col gap-3 rounded-2xl p-5 text-left transition-all"
                    :class="
                        isSelected('folder', folder.id)
                            ? 'bg-primary-100 ring-2 ring-primary-400 dark:bg-primary-950/50'
                            : `${folderPalette(folder.id).bg} hover:-translate-y-0.5`
                    "
                    @click="(ev) => emit('toggle-select', 'folder', folder.id, ev)"
                    @dblclick="emit('open-folder', folder.id)"
                    @keydown.enter.prevent="emit('open-folder', folder.id)"
                    @contextmenu.prevent="(ev) => emit('context-folder', ev, folder)"
                >
                    <!-- 3-dot menu -->
                    <button
                        type="button"
                        class="absolute right-3 top-3 flex h-6 w-6 items-center justify-center rounded text-surface-400 opacity-0 transition-opacity hover:bg-white/60 hover:text-surface-700 group-hover:opacity-100 dark:hover:bg-surface-800 dark:hover:text-surface-200"
                        @click.stop="(ev) => emit('context-folder', ev, folder)"
                    >
                        <i class="pi pi-ellipsis-v" style="font-size: 0.9rem" />
                    </button>

                    <!-- Icon square -->
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-xl bg-white/70 dark:bg-surface-900/60"
                    >
                        <i
                            class="pi pi-folder-open"
                            :class="folderPalette(folder.id).icon"
                            style="font-size: 1.75rem"
                        />
                    </div>

                    <div class="min-w-0">
                        <div class="truncate text-base font-semibold" :title="folder.name">
                            {{ folder.name }}
                        </div>
                        <div class="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                            {{ folder.file_count ?? 0 }} files · {{ humanSize(folder.total_size ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Files -->
            <div
                v-if="files.length > 0"
                class="grid gap-4"
                style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr))"
            >
                <div
                    v-for="file in files"
                    :key="`file-${file.id}`"
                    role="button"
                    tabindex="0"
                    :data-fm-key="`file:${file.id}`"
                    class="fm-tile fm-file-tile group relative flex flex-col overflow-hidden rounded-2xl border text-left transition-all"
                    :class="
                        isSelected('file', file.id)
                            ? 'border-primary-500 ring-2 ring-primary-200 dark:ring-primary-800'
                            : 'border-surface-200 hover:-translate-y-0.5 dark:border-surface-700'
                    "
                    @click="(ev) => emit('toggle-select', 'file', file.id, ev)"
                    @dblclick="emit('open-file', file)"
                    @keydown.enter.prevent="emit('open-file', file)"
                    @contextmenu.prevent="(ev) => emit('context-file', ev, file)"
                >
                    <!-- Preview area -->
                    <div
                        class="fm-preview relative flex h-32 items-center justify-center overflow-hidden"
                        :class="paletteFor(file.mime_type).preview"
                    >
                        <img
                            v-if="isImage(file.mime_type)"
                            :src="file.url"
                            :alt="file.name"
                            class="h-full w-full object-cover"
                        >
                        <template v-else>
                            <i
                                class="pi"
                                :class="[paletteFor(file.mime_type).icon, paletteFor(file.mime_type).iconColor]"
                                style="font-size: 2.75rem"
                            />
                            <span
                                v-if="isVideo(file.mime_type)"
                                class="absolute flex h-12 w-12 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm"
                            >
                                <i class="pi pi-play text-white" style="font-size: 1.2rem" />
                            </span>
                        </template>
                    </div>

                    <!-- Info bar -->
                    <div class="flex items-center gap-2.5 bg-surface-0 px-3 py-2.5 dark:bg-surface-900">
                        <i
                            class="pi shrink-0"
                            :class="[paletteFor(file.mime_type).icon, paletteFor(file.mime_type).iconClass]"
                            style="font-size: 1rem"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium" :title="file.file_name">
                                {{ file.file_name }}
                            </div>
                            <div class="text-[11px] text-surface-500 dark:text-surface-400">
                                {{ humanSize(file.size) }}
                                <span v-if="file.created_at" class="mx-0.5">·</span>
                                <span v-if="file.created_at">{{ relativeDate(file.created_at) }}</span>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded p-1 text-surface-400 opacity-0 transition-opacity hover:bg-surface-100 hover:text-surface-700 group-hover:opacity-100 dark:hover:bg-surface-800 dark:hover:text-surface-200"
                            @click.stop="emit('download-file', file)"
                        >
                            <i class="pi pi-download" style="font-size: 0.9rem" />
                        </button>
                    </div>
                </div>
            </div>
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
    .fm-folder-tile {
        min-height: 130px;
    }
    .fm-file-tile {
        min-height: 190px;
    }
</style>
