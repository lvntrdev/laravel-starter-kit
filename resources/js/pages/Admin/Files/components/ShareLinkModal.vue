<script setup lang="ts">
    import { useFileShare } from '@/composables/useFileShare';
    import { formatDateTime } from '@lvntr/components/utils/datetime';
    import { trans } from 'laravel-vue-i18n';
    import Button from 'primevue/button';
    import Dialog from 'primevue/dialog';
    import InputNumber from 'primevue/inputnumber';
    import Select from 'primevue/select';
    import { useToast } from 'primevue/usetoast';
    import { computed, ref, watch } from 'vue';

    interface Props {
        /** Medialibrary media ID */
        mediaId: number;
        /** Dosya adı — modal başlığı için */
        fileName: string;
        visible: boolean;
        /**
         * Maksimum TTL saat değeri.
         * Backend config('file-manager.share.max_ttl_hours') ile override edilebilir;
         * host uygulama bu prop'u Inertia shared data üzerinden geçirebilir.
         * Varsayılan: 720 (30 gün).
         */
        maxTtlHours?: number;
    }

    const props = withDefaults(defineProps<Props>(), {
        maxTtlHours: 720,
    });
    const emit = defineEmits<{
        'update:visible': [value: boolean];
    }>();

    const toast = useToast();
    const { createShare, revokeShare } = useFileShare();

    // ── TTL seçimi ─────────────────────────────────────────────
    type TtlPreset = '1' | '24' | '168' | 'custom';

    interface TtlOption {
        label: string;
        value: TtlPreset;
    }

    const ttlOptions = computed<TtlOption[]>(() => [
        { label: trans('sk-file-manager.share.ttl.1h'), value: '1' },
        { label: trans('sk-file-manager.share.ttl.24h'), value: '24' },
        { label: trans('sk-file-manager.share.ttl.7d'), value: '168' },
        { label: trans('sk-file-manager.share.ttl.custom'), value: 'custom' },
    ]);

    const selectedPreset = ref<TtlPreset>('24');
    const customHours = ref<number>(48);

    const effectiveTtlHours = computed<number>(() => {
        if (selectedPreset.value === 'custom') return Math.max(1, customHours.value ?? 1);
        return parseInt(selectedPreset.value, 10);
    });

    // ── Üretilen link state ─────────────────────────────────────
    interface GeneratedLink {
        url: string;
        expires_at: string;
        token_hash: string;
    }

    const loading = ref(false);
    const generatedLink = ref<GeneratedLink | null>(null);
    const copied = ref(false);

    // Modal kapandığında state'i sıfırla
    watch(
        () => props.visible,
        (val) => {
            if (!val) {
                generatedLink.value = null;
                copied.value = false;
                selectedPreset.value = '24';
                customHours.value = 48;
                loading.value = false;
            }
        },
    );

    function closeModal(): void {
        emit('update:visible', false);
    }

    // ── Link üretimi ────────────────────────────────────────────
    async function generateLink(): Promise<void> {
        loading.value = true;
        generatedLink.value = null;
        copied.value = false;

        const result = await createShare(props.mediaId, effectiveTtlHours.value);

        loading.value = false;

        if (result) {
            generatedLink.value = result;
        }
    }

    // ── Clipboard ───────────────────────────────────────────────
    async function copyToClipboard(): Promise<void> {
        if (!generatedLink.value) return;

        try {
            await navigator.clipboard.writeText(generatedLink.value.url);
            copied.value = true;
            toast.add({
                severity: 'success',
                summary: '',
                detail: trans('sk-file-manager.share.link_copied'),
                group: 'bc',
                life: 2500,
            });
            setTimeout(() => {
                copied.value = false;
            }, 3000);
        } catch {
            // Clipboard API başarısız (eski tarayıcı / HTTPS dışı)
            toast.add({
                severity: 'warn',
                summary: '',
                detail: trans('sk-file-manager.share.copy_failed_manual'),
                group: 'bc',
                life: 4000,
            });
        }
    }

    // ── Revoke ──────────────────────────────────────────────────
    const revoking = ref(false);

    async function handleRevoke(): Promise<void> {
        if (!generatedLink.value) return;
        revoking.value = true;
        const success = await revokeShare(props.mediaId, generatedLink.value.token_hash);
        revoking.value = false;
        if (success) {
            generatedLink.value = null;
        }
    }

    // ── Sona erme tarihi biçimlendirme ──────────────────────────
    function formatExpiry(iso: string): string {
        return formatDateTime(iso, {
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            second: 'numeric',
        });
    }
</script>

<template>
    <Dialog
        :visible="visible"
        :header="trans('sk-file-manager.share.modal_title', { name: fileName })"
        modal
        :style="{ width: '32rem' }"
        :closable="true"
        @update:visible="emit('update:visible', $event)"
    >
        <div class="flex flex-col gap-5">
            <!-- TTL seçimi -->
            <div class="flex flex-col gap-2">
                <label class="text-base font-medium text-surface-700 dark:text-surface-200">
                    {{ trans('sk-file-manager.share.ttl_label') }}
                </label>
                <Select
                    v-model="selectedPreset"
                    :options="ttlOptions"
                    option-label="label"
                    option-value="value"
                    class="w-full"
                />
                <div v-if="selectedPreset === 'custom'" class="flex items-center gap-2">
                    <InputNumber
                        v-model="customHours"
                        :min="1"
                        :max="props.maxTtlHours"
                        :allow-empty="false"
                        class="w-full"
                        input-class="w-full"
                    />
                    <span class="shrink-0 text-base text-surface-500 dark:text-surface-400">
                        {{ trans('sk-file-manager.share.ttl_hours_suffix') }}
                    </span>
                </div>
            </div>

            <!-- Üret butonu -->
            <Button
                :label="
                    loading
                        ? trans('sk-file-manager.share.generating')
                        : generatedLink
                          ? trans('sk-file-manager.share.regenerate')
                          : trans('sk-file-manager.share.generate')
                "
                :icon="loading ? 'pi pi-spin pi-spinner' : 'pi pi-link'"
                :disabled="loading"
                class="w-full"
                @click="generateLink"
            />

            <!-- Üretilen link -->
            <div
                v-if="generatedLink"
                class="flex flex-col gap-3 rounded-lg border border-surface-200 bg-surface-50 p-4 dark:border-surface-700 dark:bg-surface-800"
            >
                <!-- URL satırı -->
                <div class="flex items-start gap-2">
                    <input
                        :value="generatedLink.url"
                        readonly
                        class="min-w-0 flex-1 rounded border border-surface-200 bg-surface-0 px-3 py-2 font-mono text-base text-surface-700 focus:outline-none dark:border-surface-600 dark:bg-surface-900 dark:text-surface-200"
                        :aria-label="trans('sk-file-manager.share.link_aria_label')"
                        @focus="($event.target as HTMLInputElement).select()"
                    >
                    <Button
                        :icon="copied ? 'pi pi-check' : 'pi pi-copy'"
                        :severity="copied ? 'success' : 'secondary'"
                        :aria-label="trans('sk-file-manager.share.copy')"
                        @click="copyToClipboard"
                    />
                </div>

                <!-- Sona erme tarihi -->
                <p class="text-base text-surface-500 dark:text-surface-400">
                    <i class="pi pi-clock mr-1.5" style="font-size: 0.8rem" />
                    {{ trans('sk-file-manager.share.expires_at', { date: formatExpiry(generatedLink.expires_at) }) }}
                </p>

                <!-- Revoke butonu -->
                <div class="flex justify-end border-t border-surface-200 pt-3 dark:border-surface-700">
                    <Button
                        :label="revoking ? trans('sk-file-manager.share.revoking') : trans('sk-file-manager.share.revoke')"
                        :icon="revoking ? 'pi pi-spin pi-spinner' : 'pi pi-ban'"
                        severity="danger"
                        outlined
                        size="small"
                        :disabled="revoking"
                        @click="handleRevoke"
                    />
                </div>
            </div>
        </div>

        <template #footer>
            <Button
                :label="trans('sk-file-manager.labels.close')"
                severity="secondary"
                text
                @click="closeModal"
            />
        </template>
    </Dialog>
</template>
