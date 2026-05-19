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
        enableTrash?: boolean;
    }

    const props = withDefaults(defineProps<Props>(), { readonly: false, enableTrash: true });
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

    const quickItems = computed<QuickItem[]>(() => {
        const items: QuickItem[] = [
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
        ];

        if (props.enableTrash) {
            items.push({
                key: 'trash',
                label: trans('sk-file-manager.labels.sidebar.trash'),
                icon: 'pi pi-trash',
                iconClass: 'text-amber-500',
                bgClass: 'bg-amber-50 dark:bg-amber-950/40',
            });
        }

        return items;
    });

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
        class="fm-sidebar flex h-full flex-col gap-4 overflow-y-auto rounded-[6px] border border-surface-200 bg-surface-0 p-3 shadow-sm dark:border-surface-700 dark:bg-surface-900"
    >
        <!-- Quick access -->
        <div class="flex flex-col gap-0.5">
            <h3 class="px-2 pb-1 text-base font-semibold uppercase tracking-widest text-surface-400 dark:text-surface-500">
                {{ trans('sk-file-manager.labels.sidebar.quick_access') }}
            </h3>
            <button
                v-for="item in quickItems"
                :key="item.key"
                type="button"
                class="flex items-center gap-2.5 rounded-[6px] px-2.5 py-2 text-left text-base font-medium transition-colors"
                :class="
                    quickView === item.key && currentFolderId === null
                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-200'
                        : 'text-surface-600 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800'
                "
                @click="emit('select-quick', item.key)"
            >
                <span
                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-[4px]"
                    :class="item.bgClass"
                >
                    <i :class="[item.icon, item.iconClass]" style="font-size: 0.78rem" />
                </span>
                <span class="truncate">{{ item.label }}</span>
            </button>
        </div>

        <!-- Folders -->
        <div class="flex min-h-0 flex-col gap-0.5">
            <div class="flex items-center justify-between px-2 pb-1">
                <h3 class="text-base font-semibold uppercase tracking-widest text-surface-400 dark:text-surface-500">
                    {{ trans('sk-file-manager.labels.sidebar.folders') }}
                </h3>
                <Button
                    severity="secondary"
                    text
                    rounded
                    size="small"
                    icon="pi pi-plus"
                    class="!h-6 !w-6 !p-0"
                    :aria-label="trans('sk-file-manager.labels.sidebar.add_folder')"
                    :disabled="readonly"
                    @click="emit('new-folder')"
                />
            </div>

            <div v-if="tree.length === 0" class="px-2 py-2 text-base text-surface-400 dark:text-surface-500">
                {{ trans('sk-file-manager.labels.sidebar.no_folders') }}
            </div>

            <button
                v-for="folder in tree"
                v-else
                :key="folder.id"
                type="button"
                class="flex items-center gap-2.5 rounded-[6px] px-2.5 py-2 text-left text-base font-medium transition-colors"
                :class="
                    currentFolderId === folder.id
                        ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-200'
                        : 'text-surface-600 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800'
                "
                @click="emit('select-folder', folder.id)"
            >
                <span class="inline-block h-2 w-2 shrink-0 rounded-full" :class="dotColor(folder.id)" />
                <span class="truncate" :title="folder.name">{{ folder.name }}</span>
            </button>

            <button
                type="button"
                class="mt-1 flex items-center gap-2 rounded-[6px] border border-dashed border-surface-300 px-2.5 py-2 text-base font-medium text-surface-500 transition-colors hover:border-primary-400 hover:bg-primary-50/50 hover:text-primary-600 disabled:cursor-not-allowed disabled:opacity-50 dark:border-surface-600 dark:text-surface-400 dark:hover:border-primary-500 dark:hover:bg-primary-950/20 dark:hover:text-primary-300"
                :disabled="readonly"
                @click="emit('new-folder')"
            >
                <i class="pi pi-plus" style="font-size: 0.75rem" />
                <span>{{ trans('sk-file-manager.labels.sidebar.add_folder') }}</span>
            </button>
        </div>

        <!-- Storage usage — horizontal bar, only when quota is defined -->
        <div
            v-if="quotaBytes > 0"
            class="mt-auto rounded-[6px] border border-surface-200 bg-surface-50 p-3 dark:border-surface-700 dark:bg-surface-800/40"
        >
            <div class="mb-2 flex items-center justify-between">
                <span class="text-base font-semibold text-surface-700 dark:text-surface-200">
                    {{ trans('sk-file-manager.labels.sidebar.storage_usage') }}
                </span>
                <span class="text-base font-bold" :class="usagePercent >= 90 ? 'text-rose-500' : usagePercent >= 70 ? 'text-amber-500' : 'text-primary-500'">
                    {{ usagePercent }}%
                </span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-surface-200 dark:bg-surface-700">
                <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="usagePercent >= 90 ? 'bg-rose-500' : usagePercent >= 70 ? 'bg-amber-500' : 'bg-gradient-to-r from-primary-500 to-violet-400'"
                    :style="{ width: usagePercent + '%' }"
                />
            </div>
            <div class="mt-1.5 flex justify-between text-base text-surface-400 dark:text-surface-500">
                <span>{{ trans('sk-file-manager.labels.sidebar.storage_used_of', { used: humanSize(usedBytes), total: humanSize(quotaBytes) }) }}</span>
            </div>
        </div>
    </aside>
</template>
