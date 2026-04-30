<script setup lang="ts">
    import { trans } from 'laravel-vue-i18n';
    import { computed } from 'vue';

    interface Props {
        fileCount: number;
        totalSize: number;
        folderCount: number;
        favoriteCount: number;
        lastUploadAt: string | null;
    }

    const props = defineProps<Props>();

    function humanSize(bytes: number): string {
        if (!bytes) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / 1024 ** i;
        return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
    }

    function formatRelative(iso: string | null): string {
        if (!iso) return trans('sk-file-manager.labels.stats.never_uploaded');
        const date = new Date(iso);
        const diffMs = Date.now() - date.getTime();
        const minutes = Math.max(0, Math.floor(diffMs / 60000));
        if (minutes < 1) return trans('sk-file-manager.labels.stats.time_just_now');
        if (minutes < 60) return trans('sk-file-manager.labels.stats.time_minutes', { count: String(minutes) });
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return trans('sk-file-manager.labels.stats.time_hours', { count: String(hours) });
        const days = Math.floor(hours / 24);
        if (days < 30) return trans('sk-file-manager.labels.stats.time_days', { count: String(days) });
        return date.toLocaleDateString();
    }

    interface Card {
        label: string;
        value: string;
        icon: string;
        iconClass: string;
        bgClass: string;
    }

    const cards = computed<Card[]>(() => [
        {
            label: trans('sk-file-manager.labels.stats.total_files'),
            value: String(props.fileCount),
            icon: 'pi pi-file',
            iconClass: 'text-sky-500',
            bgClass: 'bg-sky-100 dark:bg-sky-900/40',
        },
        {
            label: trans('sk-file-manager.labels.stats.total_size'),
            value: humanSize(props.totalSize),
            icon: 'pi pi-database',
            iconClass: 'text-emerald-500',
            bgClass: 'bg-emerald-100 dark:bg-emerald-900/40',
        },
        {
            label: trans('sk-file-manager.labels.stats.folder_count'),
            value: String(props.folderCount),
            icon: 'pi pi-folder',
            iconClass: 'text-amber-500',
            bgClass: 'bg-amber-100 dark:bg-amber-900/40',
        },
        {
            label: trans('sk-file-manager.labels.stats.favorites'),
            value: trans('sk-file-manager.labels.stats.item_count', { count: String(props.favoriteCount) }),
            icon: 'pi pi-heart-fill',
            iconClass: 'text-rose-500',
            bgClass: 'bg-rose-100 dark:bg-rose-900/40',
        },
        {
            label: trans('sk-file-manager.labels.stats.recent_upload'),
            value: formatRelative(props.lastUploadAt),
            icon: 'pi pi-clock',
            iconClass: 'text-violet-500',
            bgClass: 'bg-violet-100 dark:bg-violet-900/40',
        },
    ]);
</script>

<template>
    <div class="fm-stats grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(170px, 1fr))">
        <div
            v-for="card in cards"
            :key="card.label"
            class="flex items-center gap-3 rounded-2xl border border-surface-200 bg-surface-0 px-4 py-3 transition-shadow hover:shadow-sm dark:border-surface-700 dark:bg-surface-900"
        >
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" :class="card.bgClass">
                <i :class="[card.icon, card.iconClass]" style="font-size: 1.1rem" />
            </span>
            <div class="flex min-w-0 flex-col">
                <span class="text-xs font-medium text-surface-500 dark:text-surface-400">{{ card.label }}</span>
                <span class="truncate text-base font-semibold text-surface-900 dark:text-surface-100">
                    {{ card.value }}
                </span>
            </div>
        </div>
    </div>
</template>
