<script setup lang="ts">
    import { trans } from 'laravel-vue-i18n';
    import Button from 'primevue/button';
    import { computed } from 'vue';
    import type { FolderNode, QuickView } from '../types';

    interface Props {
        tree: FolderNode[];
        currentFolderId: string | null;
        quickView: QuickView;
        usedBytes: number;
        quotaBytes: number;
        readonly?: boolean;
    }

    const props = withDefaults(defineProps<Props>(), { readonly: false });
    const emit = defineEmits<{
        (e: 'select-quick', view: QuickView): void;
        (e: 'select-folder', folderId: string): void;
        (e: 'new-folder'): void;
    }>();

    function humanSize(bytes: number): string {
        if (!bytes) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / 1024 ** i;
        return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
    }

    const usagePercent = computed(() => {
        if (props.quotaBytes <= 0) return 0;
        return Math.min(100, Math.round((props.usedBytes / props.quotaBytes) * 100));
    });

    const usageLabel = computed(() =>
        trans('sk-file-manager.labels.sidebar.storage_used_of', {
            used: humanSize(props.usedBytes),
            total: humanSize(props.quotaBytes),
        }),
    );

    // SVG circular ring geometry — 100×100 viewBox, stroke-width 10
    const radius = 42;
    const circumference = 2 * Math.PI * radius;
    const dashOffset = computed(() => circumference - (usagePercent.value / 100) * circumference);

    const usageStroke = computed(() => {
        if (usagePercent.value >= 90) return 'stroke-rose-500';
        if (usagePercent.value >= 70) return 'stroke-amber-500';
        return 'stroke-primary-500';
    });

    interface QuickItem {
        key: QuickView;
        label: string;
        icon: string;
        iconClass: string;
        bgClass: string;
    }

    const quickItems = computed<QuickItem[]>(() => [
        {
            key: 'all',
            label: trans('sk-file-manager.labels.sidebar.all_files'),
            icon: 'pi pi-folder',
            iconClass: 'text-primary-500',
            bgClass: 'bg-primary-50 dark:bg-primary-950/40',
        },
        {
            key: 'recent',
            label: trans('sk-file-manager.labels.sidebar.recent'),
            icon: 'pi pi-clock',
            iconClass: 'text-emerald-500',
            bgClass: 'bg-emerald-50 dark:bg-emerald-950/40',
        },
        {
            key: 'favorites',
            label: trans('sk-file-manager.labels.sidebar.favorites'),
            icon: 'pi pi-heart',
            iconClass: 'text-rose-500',
            bgClass: 'bg-rose-50 dark:bg-rose-950/40',
        },
        {
            key: 'trash',
            label: trans('sk-file-manager.labels.sidebar.trash'),
            icon: 'pi pi-trash',
            iconClass: 'text-amber-500',
            bgClass: 'bg-amber-50 dark:bg-amber-950/40',
        },
    ]);

    // Deterministic colour for top-level folder dots
    const folderPalettes = [
        'bg-indigo-500',
        'bg-rose-500',
        'bg-emerald-500',
        'bg-amber-500',
        'bg-sky-500',
        'bg-purple-500',
    ];

    function dotColor(id: string): string {
        let hash = 0;
        for (let i = 0; i < id.length; i++) {
            hash = (hash * 31 + id.charCodeAt(i)) >>> 0;
        }
        return folderPalettes[hash % folderPalettes.length];
    }
</script>

<template>
    <aside
        class="fm-sidebar flex h-full w-64 shrink-0 flex-col gap-5 overflow-y-auto border-r border-surface-200 bg-surface-0 p-4 dark:border-surface-700 dark:bg-surface-900"
    >
        <!-- Storage usage card -->
        <div
            class="flex flex-col items-center gap-3 rounded-2xl border border-surface-200 bg-gradient-to-br from-primary-50 to-surface-0 p-4 text-center dark:border-surface-700 dark:from-primary-950/30 dark:to-surface-900"
        >
            <div class="relative h-28 w-28">
                <svg viewBox="0 0 100 100" class="h-full w-full -rotate-90">
                    <circle
                        cx="50"
                        cy="50"
                        :r="radius"
                        fill="none"
                        class="stroke-surface-200 dark:stroke-surface-700"
                        stroke-width="10"
                    />
                    <circle
                        cx="50"
                        cy="50"
                        :r="radius"
                        fill="none"
                        :class="usageStroke"
                        stroke-width="10"
                        stroke-linecap="round"
                        :stroke-dasharray="circumference"
                        :stroke-dashoffset="dashOffset"
                        class="transition-[stroke-dashoffset] duration-500 ease-out"
                    />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-2xl font-bold text-surface-900 dark:text-surface-100"> {{ usagePercent }}% </span>
                </div>
            </div>
            <div class="flex flex-col gap-0.5">
                <span class="font-semibold text-surface-900 dark:text-surface-100">
                    {{ trans('sk-file-manager.labels.sidebar.storage_usage') }}
                </span>
                <span class="text-xs text-surface-500 dark:text-surface-400">{{ usageLabel }}</span>
            </div>
        </div>

        <!-- Quick access -->
        <div class="flex flex-col gap-1.5">
            <h3 class="px-2 text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">
                {{ trans('sk-file-manager.labels.sidebar.quick_access') }}
            </h3>
            <button
                v-for="item in quickItems"
                :key="item.key"
                type="button"
                class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all"
                :class="
                    quickView === item.key && currentFolderId === null
                        ? 'bg-primary-50 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/40 dark:text-primary-200 dark:ring-primary-900'
                        : 'text-surface-700 hover:bg-surface-100 dark:text-surface-200 dark:hover:bg-surface-800'
                "
                @click="emit('select-quick', item.key)"
            >
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-transform group-hover:scale-105"
                    :class="item.bgClass"
                >
                    <i :class="[item.icon, item.iconClass]" style="font-size: 0.95rem" />
                </span>
                <span class="truncate">{{ item.label }}</span>
            </button>
        </div>

        <!-- Folders -->
        <div class="flex min-h-0 flex-col gap-1.5">
            <div class="flex items-center justify-between px-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">
                    {{ trans('sk-file-manager.labels.sidebar.folders') }}
                </h3>
                <Button
                    severity="secondary"
                    text
                    rounded
                    size="small"
                    icon="pi pi-plus"
                    class="!h-7 !w-7 !p-0"
                    :aria-label="trans('sk-file-manager.labels.sidebar.add_folder')"
                    :disabled="readonly"
                    @click="emit('new-folder')"
                />
            </div>

            <div v-if="tree.length === 0" class="px-2 py-3 text-xs text-surface-400 dark:text-surface-500">
                {{ trans('sk-file-manager.labels.sidebar.no_folders') }}
            </div>

            <button
                v-for="folder in tree"
                v-else
                :key="folder.id"
                type="button"
                class="group flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all"
                :class="
                    currentFolderId === folder.id
                        ? 'bg-primary-50 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-950/40 dark:text-primary-200 dark:ring-primary-900'
                        : 'text-surface-700 hover:bg-surface-100 dark:text-surface-200 dark:hover:bg-surface-800'
                "
                @click="emit('select-folder', folder.id)"
            >
                <span class="inline-block h-2.5 w-2.5 shrink-0 rounded-full" :class="dotColor(folder.id)" />
                <span class="truncate" :title="folder.name">{{ folder.name }}</span>
            </button>

            <button
                type="button"
                class="mt-1 flex items-center gap-2 rounded-xl border border-dashed border-surface-300 px-3 py-2.5 text-sm font-medium text-surface-500 transition-colors hover:border-primary-400 hover:bg-primary-50/50 hover:text-primary-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-surface-600 dark:text-surface-400 dark:hover:border-primary-500 dark:hover:bg-primary-950/20 dark:hover:text-primary-300"
                :disabled="readonly"
                @click="emit('new-folder')"
            >
                <i class="pi pi-plus" style="font-size: 0.85rem" />
                <span>{{ trans('sk-file-manager.labels.sidebar.add_folder') }}</span>
            </button>
        </div>
    </aside>
</template>
