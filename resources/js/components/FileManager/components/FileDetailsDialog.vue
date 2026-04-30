<script setup lang="ts">
    import { trans } from 'laravel-vue-i18n';
    import Button from 'primevue/button';
    import { computed, onMounted, ref } from 'vue';
    import type { FileItem } from '../types';

    interface Props {
        file: FileItem;
        folderName?: string | null;
        onDownload?: () => void;
    }

    const props = withDefaults(defineProps<Props>(), { folderName: null, onDownload: undefined });

    function humanSize(bytes: number): string {
        if (!bytes) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / 1024 ** i;
        return `${value.toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
    }

    function formatDate(iso: string | null): string {
        if (!iso) return '—';
        const date = new Date(iso);
        return date.toLocaleString();
    }

    const isImage = computed(() => props.file.mime_type.startsWith('image/'));

    const dimensions = ref<string | null>(null);

    onMounted(() => {
        if (!isImage.value) return;
        const img = new Image();
        img.onload = () => {
            dimensions.value = `${img.naturalWidth} × ${img.naturalHeight}`;
        };
        img.src = props.file.url;
    });

    interface Row {
        label: string;
        value: string;
    }

    const rows = computed<Row[]>(() => {
        const out: Row[] = [
            { label: trans('sk-file-manager.labels.details.name'), value: props.file.file_name },
            { label: trans('sk-file-manager.labels.details.type'), value: props.file.mime_type },
            { label: trans('sk-file-manager.labels.details.size'), value: humanSize(props.file.size) },
            { label: trans('sk-file-manager.labels.details.created_at'), value: formatDate(props.file.created_at) },
        ];
        if (props.folderName) {
            out.push({ label: trans('sk-file-manager.labels.details.folder'), value: props.folderName });
        }
        if (dimensions.value) {
            out.push({ label: trans('sk-file-manager.labels.details.dimensions'), value: dimensions.value });
        }
        return out;
    });
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            class="flex h-40 w-full items-center justify-center overflow-hidden rounded-xl bg-surface-100 dark:bg-surface-800"
        >
            <img v-if="isImage" :src="file.url" :alt="file.file_name" class="h-full w-full object-contain">
            <i v-else class="pi pi-file text-surface-400" style="font-size: 4rem" />
        </div>

        <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2.5 text-sm">
            <template v-for="row in rows" :key="row.label">
                <dt class="font-medium text-surface-500 dark:text-surface-400">
                    {{ row.label }}
                </dt>
                <dd class="break-all text-surface-900 dark:text-surface-100">
                    {{ row.value }}
                </dd>
            </template>
        </dl>

        <div v-if="onDownload" class="flex justify-end">
            <Button
                icon="pi pi-download"
                :label="trans('sk-file-manager.labels.download')"
                severity="secondary"
                @click="onDownload"
            />
        </div>
    </div>
</template>
