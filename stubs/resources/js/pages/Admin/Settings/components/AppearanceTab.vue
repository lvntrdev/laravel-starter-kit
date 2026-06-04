<script setup lang="ts">
    import { computed, onBeforeUnmount, ref } from 'vue';
    import { router } from '@inertiajs/vue3';
    import { trans } from 'laravel-vue-i18n';
    import { useToast } from 'primevue/usetoast';
    import { useApi, useTheme } from '@/composables';
    import { ApiError } from '@/composables/useApi';
    import adminSettings from '@/routes/settings';

    /**
     * Appearance settings tab — app-wide theme preset selector.
     *
     * Two distinct actions:
     *   • Preview (instant, runtime only): selecting a card calls
     *     `useTheme().setTheme()`, which swaps the live PrimeVue preset via
     *     `usePreset`. This is NOT persisted — a page reload re-reads the
     *     saved `theme` shared-prop and reverts. To avoid leaving a stale
     *     preview applied when the user navigates away, an unsaved preview is
     *     restored to the saved theme on unmount.
     *   • Save (permanent): PUTs `{ theme }` to the appearance endpoint. On
     *     success the saved baseline is updated and the preview is no longer
     *     considered "dirty".
     *
     * Görünüm ayarları sekmesi — uygulama-geneli tema önayarı seçici.
     * Önizle = anlık, yalnızca runtime (DB'ye yazmaz); Kaydet = kalıcı (PUT).
     */

    const { currentTheme, themeNames, setTheme } = useTheme();
    const api = useApi({ toast: false });
    const toast = useToast();

    /** The persisted (saved) theme — the baseline a reload would restore to. */
    const savedTheme = ref<string>(currentTheme.value);

    /** The currently selected/previewed theme. Seeded from the saved theme. */
    const selected = ref<string>(currentTheme.value);

    const saving = ref(false);

    /** True when the live preview differs from the persisted theme. */
    const isDirty = computed(() => selected.value !== savedTheme.value);

    interface ThemeOption {
        name: string;
        label: string;
    }

    /** Selectable themes derived from the frontend registry — no hardcoded list. */
    const themeOptions = computed<ThemeOption[]>(() =>
        themeNames.map((name) => ({
            name,
            label: trans(`sk-setting.appearance.themes.${name}`),
        })),
    );

    /** Apply a theme as a live preview (runtime swap, not persisted). */
    function previewTheme(name: string) {
        if (selected.value === name) return;
        selected.value = setTheme(name);
    }

    /** Revert the live preview back to the saved theme without persisting. */
    function discardPreview() {
        selected.value = setTheme(savedTheme.value);
    }

    /** Persist the selected theme app-wide. */
    async function save() {
        if (saving.value || !isDirty.value) return;

        saving.value = true;
        try {
            await api.put(adminSettings.update.appearance.url(), { theme: selected.value });

            savedTheme.value = selected.value;
            toast.add({
                severity: 'success',
                summary: trans('sk-setting.appearance.title'),
                detail: trans('sk-setting.appearance.saved'),
                group: 'bc',
                life: 4000,
            });

            // Re-sync the shared `theme` prop so other surfaces and a later
            // reload reflect the new baseline.
            router.reload({ only: ['theme'] });
        } catch (error) {
            const detail =
                error instanceof ApiError
                    ? error.body.errors?.theme?.[0] ?? error.message
                    : trans('sk-message.network_error');

            toast.add({
                severity: 'error',
                summary: trans('sk-message.error_summary'),
                detail,
                group: 'bc',
                life: 5000,
            });
        } finally {
            saving.value = false;
        }
    }

    // Leaving the tab with an unsaved preview must not strand a stale theme.
    onBeforeUnmount(() => {
        if (isDirty.value) {
            setTheme(savedTheme.value);
        }
    });
</script>

<template>
    <Card>
        <template #title>
            {{ $t('sk-setting.tabs.appearance') }}
        </template>
        <template #subtitle>
            {{ $t('sk-setting.appearance.preview_hint') }}
        </template>
        <template #content>
            <div class="space-y-4">
                <!-- Theme card grid (radiogroup) -->
                <div
                    role="radiogroup"
                    :aria-label="$t('sk-setting.appearance.theme_label')"
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <button
                        v-for="option in themeOptions"
                        :key="option.name"
                        type="button"
                        role="radio"
                        :aria-checked="selected === option.name"
                        class="group relative flex items-center gap-3 rounded-lg border p-3 text-left transition-colors"
                        :class="
                            selected === option.name
                                ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10'
                                : 'border-surface-200 bg-surface-0 hover:border-surface-300 dark:border-surface-700 dark:bg-surface-800 dark:hover:border-surface-600'
                        "
                        @click="previewTheme(option.name)"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-surface-200 dark:border-surface-700"
                            :class="selected === option.name ? 'bg-primary-500' : 'bg-surface-100 dark:bg-surface-800'"
                        >
                            <i
                                class="pi pi-palette text-sm"
                                :class="selected === option.name ? 'text-primary-contrast' : 'text-surface-400'"
                            />
                        </span>

                        <span class="flex min-w-0 flex-1 flex-col">
                            <span class="truncate text-sm font-medium text-surface-800 dark:text-surface-100">
                                {{ option.label }}
                            </span>
                            <span
                                v-if="savedTheme === option.name"
                                class="text-xs text-surface-400"
                            >
                                {{ $t('sk-setting.appearance.active') }}
                            </span>
                        </span>

                        <i
                            v-if="selected === option.name"
                            class="pi pi-check-circle text-primary-500"
                            aria-hidden="true"
                        />
                    </button>
                </div>

                <!-- Dirty / preview indicator -->
                <div
                    v-if="isDirty"
                    class="flex items-center gap-2 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-700 dark:bg-amber-500/10 dark:text-amber-400"
                >
                    <i class="pi pi-info-circle" aria-hidden="true" />
                    <span>{{ $t('sk-setting.appearance.previewing') }}</span>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        :label="$t('sk-button.save')"
                        icon="pi pi-check"
                        size="small"
                        :loading="saving"
                        :disabled="!isDirty"
                        @click="save"
                    />
                    <Button
                        v-if="isDirty"
                        type="button"
                        :label="$t('sk-setting.appearance.discard')"
                        icon="pi pi-undo"
                        size="small"
                        outlined
                        :disabled="saving"
                        @click="discardPreview"
                    />
                </div>
            </div>
        </template>
    </Card>
</template>
