<script setup lang="ts">
    import { computed, ref, watch } from 'vue';
    import { router } from '@inertiajs/vue3';
    import { useConfirm } from '@/composables/useConfirm';

    /**
     * Generic single-slot image upload box (logo / favicon / brand assets).
     *
     * Mirrors AvatarUpload's CSRF + fetch + optimistic-preview pattern, but for
     * rectangular/settings-style image slots. The same `uploadUrl` is used for
     * the POST (upload) and the DELETE (remove); on success a partial Inertia
     * reload (`reloadOnly`) hands back the canonical server URL.
     */
    interface Props {
        /** Canonical server URL (null when no image set). */
        previewUrl?: string | null;
        /** Endpoint — POST to upload, DELETE to remove. */
        uploadUrl: string;
        /** FormData field name for the uploaded file (e.g. `logo`, `favicon`). */
        fieldName: string;
        /** Key under `json.data` holding the new URL after upload (e.g. `logo_url`). */
        responseKey: string;
        /** `<input accept>` value. */
        accept: string;
        /** Already-translated label + hint. */
        label: string;
        hint: string;
        /** Already-translated button/confirm strings. */
        uploadLabel: string;
        removeLabel: string;
        removeConfirm: string;
        /** Preview box style. `logo-dark` renders the dark backdrop variant. */
        variant?: 'logo-light' | 'logo-dark' | 'favicon';
        /** Internal arrangement: stacked card (logo grid) vs single row (favicon). */
        layout?: 'stacked' | 'row';
        /** Inertia partial-reload prop keys after a change. */
        reloadOnly?: string[];
    }

    const props = withDefaults(defineProps<Props>(), {
        previewUrl: null,
        variant: 'logo-light',
        layout: 'row',
        reloadOnly: () => ['settings'],
    });

    const { confirmDelete } = useConfirm();
    const currentUrl = ref<string | null>(props.previewUrl);
    const uploading = ref(false);
    const fileInput = ref<HTMLInputElement | null>(null);

    watch(
        () => props.previewUrl,
        (val) => {
            currentUrl.value = val ?? null;
        },
    );

    // ── Preview box styling derived from the variant ─────────────────────
    const boxClass = computed(() =>
        props.variant === 'favicon'
            ? 'size-16 bg-surface-50 dark:bg-surface-800'
            : props.variant === 'logo-dark'
              ? 'h-16 w-[92px] bg-surface-800 dark:bg-surface-950'
              : 'h-16 w-[92px] bg-surface-50 dark:bg-surface-800',
    );
    const imgPad = computed(() => (props.variant === 'favicon' ? 'p-2' : 'p-1.5'));
    const placeholderIcon = computed(() => (props.variant === 'favicon' ? 'pi pi-globe' : 'pi pi-image'));

    function csrf(): Record<string, string> {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        const match = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/);
        if (match) headers['X-XSRF-TOKEN'] = decodeURIComponent(match[2]);
        return headers;
    }

    function pick(): void {
        fileInput.value?.click();
    }

    async function onFileSelected(event: Event): Promise<void> {
        const input = event.target as HTMLInputElement;
        const file = input.files?.[0];
        input.value = '';
        if (!file) return;

        // Instant local preview.
        const reader = new FileReader();
        reader.onload = (e) => {
            currentUrl.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);

        uploading.value = true;
        try {
            const body = new FormData();
            body.append(props.fieldName, file);

            const response = await fetch(props.uploadUrl, {
                method: 'POST',
                headers: csrf(),
                credentials: 'same-origin',
                body,
            });

            if (response.ok) {
                const json = await response.json();
                currentUrl.value = json.data?.[props.responseKey] ?? currentUrl.value;
                router.reload({ only: props.reloadOnly });
            } else {
                currentUrl.value = props.previewUrl ?? null;
            }
        } catch {
            currentUrl.value = props.previewUrl ?? null;
        } finally {
            uploading.value = false;
        }
    }

    function removeImage(): void {
        confirmDelete(async () => {
            uploading.value = true;
            try {
                const response = await fetch(props.uploadUrl, {
                    method: 'DELETE',
                    headers: csrf(),
                    credentials: 'same-origin',
                });
                if (response.ok || response.status === 204) {
                    currentUrl.value = null;
                    router.reload({ only: props.reloadOnly });
                }
            } catch {
                // surface nothing; the next reload reconciles state.
            } finally {
                uploading.value = false;
            }
        }, props.removeConfirm);
    }
</script>

<template>
    <div
        class="flex flex-col gap-4 rounded-md border border-surface-200 bg-surface-0 p-4 dark:border-surface-700 dark:bg-surface-900"
        :class="layout === 'row' ? 'sm:flex-row sm:items-center' : ''"
    >
        <!-- Stacked layout (logo cards): preview + text on one row, buttons below -->
        <template v-if="layout === 'stacked'">
            <div class="flex items-center gap-3">
                <div
                    class="grid shrink-0 place-items-center overflow-hidden rounded-md border border-surface-200 dark:border-surface-700"
                    :class="boxClass"
                >
                    <img v-if="currentUrl" :src="currentUrl" alt="" class="max-h-full max-w-full object-contain" :class="imgPad">
                    <i v-else :class="placeholderIcon" class="text-xl text-surface-400" />
                </div>
                <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <span class="text-sm font-bold text-surface-900 dark:text-surface-100">{{ label }}</span>
                    <small class="text-surface-600 dark:text-surface-300">{{ hint }}</small>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    :label="uploadLabel"
                    icon="pi pi-upload"
                    size="small"
                    outlined
                    :loading="uploading"
                    @click="pick"
                />
                <Button
                    v-if="currentUrl"
                    type="button"
                    icon="pi pi-trash"
                    size="small"
                    severity="danger"
                    outlined
                    :aria-label="removeLabel"
                    :disabled="uploading"
                    @click="removeImage"
                />
            </div>
        </template>

        <!-- Row layout (favicon): preview, text, buttons inline -->
        <template v-else>
            <div
                class="grid shrink-0 place-items-center overflow-hidden rounded-md border border-surface-200 dark:border-surface-700"
                :class="boxClass"
            >
                <img v-if="currentUrl" :src="currentUrl" alt="" class="max-h-full max-w-full object-contain" :class="imgPad">
                <i v-else :class="placeholderIcon" class="text-xl text-surface-400" />
            </div>
            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                <span class="text-sm font-bold text-surface-900 dark:text-surface-100">{{ label }}</span>
                <small class="text-surface-600 dark:text-surface-300">{{ hint }}</small>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <Button
                    type="button"
                    :label="uploadLabel"
                    icon="pi pi-upload"
                    size="small"
                    outlined
                    :loading="uploading"
                    @click="pick"
                />
                <Button
                    v-if="currentUrl"
                    type="button"
                    icon="pi pi-trash"
                    size="small"
                    severity="danger"
                    outlined
                    :aria-label="removeLabel"
                    :disabled="uploading"
                    @click="removeImage"
                />
            </div>
        </template>

        <input ref="fileInput" type="file" :accept="accept" class="hidden" @change="onFileSelected">
    </div>
</template>
