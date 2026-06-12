<script setup lang="ts">
    import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
    import { router } from '@inertiajs/vue3';
    import { trans } from 'laravel-vue-i18n';
    import { useConfirm } from '@/composables/useConfirm';
    import {
        ACCENT_COLORS,
        ACCENT_SWATCH,
        useAccentColor,
        type AccentColor,
    } from '@/composables/useAccentColor';

    interface AppearanceSettings {
        theme: string;
        available_themes: string[];
        accent_color: string;
        dark_mode_default: boolean;
        sidebar_style: string;
        logo_light_url: string | null;
        logo_dark_url: string | null;
        favicon_url: string | null;
    }

    interface Props {
        settings: AppearanceSettings;
    }

    const props = defineProps<Props>();

    const { confirmDelete } = useConfirm();
    // applyAccent drives the live preview; `accent` is the persisted per-user
    // override we DON'T touch here — this tab edits the global default only,
    // so we keep a local restore value to undo the preview on cancel/leave.
    const { applyAccent, accent: userAccent } = useAccentColor();

    // ── Editable form state (the saved appearance defaults) ──────────────
    const form = reactive({
        theme: props.settings.theme,
        accent_color: props.settings.accent_color as AccentColor,
        dark_mode_default: props.settings.dark_mode_default,
        sidebar_style: props.settings.sidebar_style,
    });

    const saving = ref(false);

    // The accent the user's own session was showing before we previewed, so we
    // can restore it if they navigate away without saving (preview is global).
    const restoreAccent = userAccent.value;

    // ── Dirty tracking ───────────────────────────────────────────────────
    const isDirty = computed(
        () =>
            form.theme !== props.settings.theme ||
            form.accent_color !== props.settings.accent_color ||
            form.dark_mode_default !== props.settings.dark_mode_default ||
            form.sidebar_style !== props.settings.sidebar_style,
    );

    // ── Theme cards ──────────────────────────────────────────────────────
    function selectTheme(theme: string): void {
        form.theme = theme;
    }

    // Per-theme description (falls back to the section subtitle for unmapped themes).
    function themeDesc(theme: string): string {
        const key = `sk-setting.appearance.theme_desc.${theme}`;
        const label = trans(key);
        return label === key ? trans('sk-setting.appearance.theme_section_subtitle') : label;
    }

    // ── Accent swatches ──────────────────────────────────────────────────
    // Full palette, mirroring the header popover: `Varsayılan` (default) plus
    // every accent (greys, colours and the four custom muted tones), shown as
    // chip + label cards.
    const swatchColors = ACCENT_COLORS;

    function selectAccent(color: AccentColor): void {
        form.accent_color = color;
    }

    // Live preview: whenever the picked accent changes, apply it LITERALLY so the
    // admin previews the exact system default they're setting — here `'default'`
    // is kit-blue (they are DEFINING the default), not "follow global". The admin's
    // own session accent (restoreAccent) is reinstated on leave so the preview
    // never sticks across the app for an unsaved change.
    watch(
        () => form.accent_color,
        (val) => applyAccent(val, { followGlobal: false }),
    );

    onBeforeUnmount(() => {
        // Reinstate the admin's actual view (their per-user accent, which follows
        // the global default when unset) so a previewed-but-unsaved default doesn't
        // linger in their session.
        applyAccent(restoreAccent);
    });

    // ── CSRF + upload helpers (mirror AvatarUpload's fetch pattern) ──────
    function csrf(): Record<string, string> {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };
        const match = document.cookie.match(/(^|;\s*)XSRF-TOKEN=([^;]*)/);
        if (match) headers['X-XSRF-TOKEN'] = decodeURIComponent(match[2]);
        return headers;
    }

    type Slot = 'light' | 'dark' | 'favicon';

    const uploading = reactive<Record<Slot, boolean>>({ light: false, dark: false, favicon: false });

    // Local preview URLs so an upload reflects instantly before the page reload
    // hands back the canonical URL from the server.
    const previews = reactive<Record<Slot, string | null>>({
        light: props.settings.logo_light_url,
        dark: props.settings.logo_dark_url,
        favicon: props.settings.favicon_url,
    });

    watch(
        () => props.settings,
        (s) => {
            previews.light = s.logo_light_url;
            previews.dark = s.logo_dark_url;
            previews.favicon = s.favicon_url;
        },
    );

    function uploadUrl(slot: Slot): string {
        return slot === 'favicon' ? '/settings/appearance/favicon' : `/settings/appearance/logo/${slot}`;
    }

    function fieldName(slot: Slot): string {
        return slot === 'favicon' ? 'favicon' : 'logo';
    }

    async function onFileSelected(slot: Slot, event: Event): Promise<void> {
        const input = event.target as HTMLInputElement;
        const file = input.files?.[0];
        input.value = '';
        if (!file) return;

        // Instant local preview.
        const reader = new FileReader();
        reader.onload = (e) => {
            previews[slot] = e.target?.result as string;
        };
        reader.readAsDataURL(file);

        uploading[slot] = true;
        try {
            const body = new FormData();
            body.append(fieldName(slot), file);

            const response = await fetch(uploadUrl(slot), {
                method: 'POST',
                headers: csrf(),
                credentials: 'same-origin',
                body,
            });

            if (response.ok) {
                const json = await response.json();
                previews[slot] =
                    (slot === 'favicon' ? json.data?.favicon_url : json.data?.logo_url) ?? previews[slot];
                router.reload({ only: ['settings'] });
            } else {
                previews[slot] = currentUrl(slot);
            }
        } catch {
            previews[slot] = currentUrl(slot);
        } finally {
            uploading[slot] = false;
        }
    }

    function currentUrl(slot: Slot): string | null {
        if (slot === 'light') return props.settings.logo_light_url;
        if (slot === 'dark') return props.settings.logo_dark_url;
        return props.settings.favicon_url;
    }

    function removeFile(slot: Slot): void {
        const message = trans(
            slot === 'favicon'
                ? 'sk-setting.appearance.favicon_remove_confirm'
                : 'sk-setting.appearance.logo_remove_confirm',
        );

        confirmDelete(async () => {
            uploading[slot] = true;
            try {
                const response = await fetch(uploadUrl(slot), {
                    method: 'DELETE',
                    headers: csrf(),
                    credentials: 'same-origin',
                });
                if (response.ok || response.status === 204) {
                    previews[slot] = null;
                    router.reload({ only: ['settings'] });
                }
            } catch {
                // surface nothing; the next reload reconciles state.
            } finally {
                uploading[slot] = false;
            }
        }, message);
    }

    // ── File pickers (one hidden input per slot) ─────────────────────────
    const inputs = reactive<Record<Slot, HTMLInputElement | null>>({
        light: null,
        dark: null,
        favicon: null,
    });

    function pick(slot: Slot): void {
        inputs[slot]?.click();
    }

    // ── Save ─────────────────────────────────────────────────────────────
    function save(): void {
        router.put('/settings/appearance', { ...form }, {
            preserveScroll: true,
            onStart: () => {
                saving.value = true;
            },
            onFinish: () => {
                saving.value = false;
            },
        });
    }
</script>

<template>
    <!-- Single card: header · grouped rows (label left / content right) · footer -->
    <section class="overflow-hidden rounded-md border border-surface-200 bg-surface-0 dark:border-surface-700 dark:bg-surface-900">
        <!-- Card header -->
        <header class="border-b border-surface-200 px-6 py-[18px] dark:border-surface-700">
            <h2 class="text-base font-semibold tracking-tight text-surface-900 dark:text-surface-100">
                {{ $t('sk-setting.appearance.title') }}
            </h2>
            <p class="mt-1 text-[13px] text-surface-500 dark:text-surface-400">
                {{ $t('sk-setting.appearance.subtitle') }}
            </p>
        </header>

        <!-- Card body: settings groups, divided -->
        <div class="divide-y divide-surface-200 px-6 dark:divide-surface-700">
            <!-- ── Logo ─────────────────────────────────────────────────── -->
            <div class="grid grid-cols-1 gap-x-11 gap-y-6 py-[26px] md:grid-cols-[216px_1fr]">
                <div>
                    <div class="text-sm font-bold tracking-tight text-surface-900 dark:text-surface-100">
                        {{ $t('sk-setting.appearance.logo_section_title') }}
                    </div>
                    <div class="mt-[7px] text-xs leading-relaxed text-surface-500 dark:text-surface-400">
                        {{ $t('sk-setting.appearance.logo_section_subtitle') }}
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div
                        v-for="slot in (['light', 'dark'] as const)"
                        :key="slot"
                        class="flex flex-col gap-4 rounded-md border border-surface-200 bg-surface-0 p-4 dark:border-surface-700 dark:bg-surface-900"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="grid h-16 w-[92px] shrink-0 place-items-center overflow-hidden rounded-md border border-surface-200 bg-surface-50 dark:border-surface-700"
                                :class="slot === 'dark' ? 'bg-surface-800 dark:bg-surface-950' : 'dark:bg-surface-800'"
                            >
                                <img
                                    v-if="previews[slot]"
                                    :src="previews[slot]!"
                                    alt=""
                                    class="max-h-full max-w-full object-contain p-1.5"
                                >
                                <i v-else class="pi pi-image text-xl text-surface-400" />
                            </div>

                            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="text-sm font-bold text-surface-900 dark:text-surface-100">
                                    {{
                                        slot === 'light'
                                            ? $t('sk-setting.appearance.logo_light_label')
                                            : $t('sk-setting.appearance.logo_dark_label')
                                    }}
                                </span>
                                <small class="text-surface-600 dark:text-surface-300">
                                    {{
                                        slot === 'light'
                                            ? $t('sk-setting.appearance.logo_light_hint')
                                            : $t('sk-setting.appearance.logo_dark_hint')
                                    }}
                                </small>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                :label="$t('sk-setting.appearance.upload_label')"
                                icon="pi pi-upload"
                                size="small"
                                outlined
                                :loading="uploading[slot]"
                                @click="pick(slot)"
                            />
                            <Button
                                v-if="previews[slot]"
                                type="button"
                                icon="pi pi-trash"
                                size="small"
                                severity="danger"
                                outlined
                                :aria-label="$t('sk-setting.appearance.remove_label')"
                                :disabled="uploading[slot]"
                                @click="removeFile(slot)"
                            />
                            <input
                                :ref="(el) => (inputs[slot] = el as HTMLInputElement | null)"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                class="hidden"
                                @change="onFileSelected(slot, $event)"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Favicon ──────────────────────────────────────────────── -->
            <div class="grid grid-cols-1 gap-x-11 gap-y-6 py-[26px] md:grid-cols-[216px_1fr]">
                <div>
                    <div class="text-sm font-bold tracking-tight text-surface-900 dark:text-surface-100">
                        {{ $t('sk-setting.appearance.favicon_section_title') }}
                    </div>
                    <div class="mt-[7px] text-xs leading-relaxed text-surface-500 dark:text-surface-400">
                        {{ $t('sk-setting.appearance.favicon_section_subtitle') }}
                    </div>
                </div>

                <div
                    class="flex flex-col gap-4 rounded-md border border-surface-200 bg-surface-0 p-4 sm:flex-row sm:items-center dark:border-surface-700 dark:bg-surface-900"
                >
                    <div
                        class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-md border border-surface-200 bg-surface-50 dark:border-surface-700 dark:bg-surface-800"
                    >
                        <img
                            v-if="previews.favicon"
                            :src="previews.favicon!"
                            alt=""
                            class="max-h-full max-w-full object-contain p-2"
                        >
                        <i v-else class="pi pi-globe text-xl text-surface-400" />
                    </div>

                    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                        <span class="text-sm font-bold text-surface-900 dark:text-surface-100">
                            {{ $t('sk-setting.appearance.favicon_label') }}
                        </span>
                        <small class="text-surface-600 dark:text-surface-300">
                            {{ $t('sk-setting.appearance.favicon_hint') }}
                        </small>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Button
                            type="button"
                            :label="$t('sk-setting.appearance.upload_label')"
                            icon="pi pi-upload"
                            size="small"
                            outlined
                            :loading="uploading.favicon"
                            @click="pick('favicon')"
                        />
                        <Button
                            v-if="previews.favicon"
                            type="button"
                            icon="pi pi-trash"
                            size="small"
                            severity="danger"
                            outlined
                            :aria-label="$t('sk-setting.appearance.remove_label')"
                            :disabled="uploading.favicon"
                            @click="removeFile('favicon')"
                        />
                        <input
                            :ref="(el) => (inputs.favicon = el as HTMLInputElement | null)"
                            type="file"
                            accept="image/png,image/x-icon,.ico"
                            class="hidden"
                            @change="onFileSelected('favicon', $event)"
                        >
                    </div>
                </div>
            </div>

            <!-- ── Tema ─────────────────────────────────────────────────── -->
            <div class="grid grid-cols-1 gap-x-11 gap-y-6 py-[26px] md:grid-cols-[216px_1fr]">
                <div>
                    <div class="text-sm font-bold tracking-tight text-surface-900 dark:text-surface-100">
                        {{ $t('sk-setting.appearance.theme_section_title') }}
                    </div>
                    <div class="mt-[7px] text-xs leading-relaxed text-surface-500 dark:text-surface-400">
                        {{ $t('sk-setting.appearance.theme_section_subtitle') }}
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button
                        v-for="theme in props.settings.available_themes"
                        :key="theme"
                        type="button"
                        class="relative flex w-[280px] max-w-full items-center gap-3 rounded-md border border-surface-200 py-3 pl-3 pr-4 text-left transition-colors dark:border-surface-700"
                        :class="
                            form.theme === theme
                                ? 'border-primary-500! bg-primary-500/5 ring-[3px] ring-primary-500/15'
                                : 'hover:border-surface-300 dark:hover:border-surface-600'
                        "
                        @click="selectTheme(theme)"
                    >
                        <span class="flex h-[38px] w-12 shrink-0 overflow-hidden rounded-md border border-surface-200 dark:border-surface-700">
                            <span
                                class="h-full w-[15px] shrink-0"
                                :class="theme === 'main' ? 'bg-surface-300 dark:bg-surface-600' : 'bg-primary-500'"
                            />
                            <span class="h-full flex-1 bg-surface-50 dark:bg-surface-800" />
                        </span>
                        <span class="flex min-w-0 flex-col gap-0.5">
                            <span
                                class="text-sm font-bold capitalize"
                                :class="form.theme === theme ? 'text-primary-500' : 'text-surface-900 dark:text-surface-100'"
                            >
                                {{ theme }}
                            </span>
                            <span class="text-[11.5px] text-surface-500 dark:text-surface-400">
                                {{ themeDesc(theme) }}
                            </span>
                        </span>
                        <span
                            class="ml-auto grid h-5 w-5 shrink-0 place-items-center rounded-full border transition-colors"
                            :class="
                                form.theme === theme
                                    ? 'border-primary-500 bg-primary-500'
                                    : 'border-surface-300 dark:border-surface-600'
                            "
                        >
                            <i v-show="form.theme === theme" class="pi pi-check text-[10px] text-white" />
                        </span>
                    </button>
                </div>
            </div>

            <!-- ── Varsayılan Renk ──────────────────────────────────────── -->
            <div class="grid grid-cols-1 gap-x-11 gap-y-6 py-[26px] md:grid-cols-[216px_1fr]">
                <div>
                    <div class="text-sm font-bold tracking-tight text-surface-900 dark:text-surface-100">
                        {{ $t('sk-setting.appearance.accent_section_title') }}
                    </div>
                    <div class="mt-[7px] text-xs leading-relaxed text-surface-500 dark:text-surface-400">
                        {{ $t('sk-setting.appearance.accent_section_subtitle') }}
                    </div>
                </div>

                <div class="grid grid-cols-[repeat(auto-fill,minmax(82px,1fr))] gap-2.5">
                    <!-- Varsayılan (default = kit primary) -->
                    <button
                        type="button"
                        class="flex flex-col gap-2 rounded-md border p-[9px] transition-colors"
                        :class="
                            form.accent_color === 'default'
                                ? 'border-primary-500! bg-primary-500/5 ring-[3px] ring-primary-500/15'
                                : 'border-surface-200 hover:border-surface-300 dark:border-surface-700 dark:hover:border-surface-600'
                        "
                        :title="$t('sk-layout.color_default')"
                        :aria-label="$t('sk-layout.color_default')"
                        @click="selectAccent('default')"
                    >
                        <span class="grid h-[34px] place-items-center rounded-md border border-dashed border-surface-300 text-surface-400 dark:border-surface-600">
                            <i class="pi pi-ban text-[13px]" />
                        </span>
                        <span
                            class="text-center text-[11px] font-semibold"
                            :class="form.accent_color === 'default' ? 'text-surface-900 dark:text-surface-100' : 'text-surface-500 dark:text-surface-400'"
                        >
                            {{ $t('sk-layout.color_default') }}
                        </span>
                    </button>

                    <button
                        v-for="color in swatchColors"
                        :key="color"
                        type="button"
                        class="flex flex-col gap-2 rounded-md border p-[9px] transition-colors"
                        :class="
                            form.accent_color === color
                                ? 'border-primary-500! bg-primary-500/5 ring-[3px] ring-primary-500/15'
                                : 'border-surface-200 hover:border-surface-300 dark:border-surface-700 dark:hover:border-surface-600'
                        "
                        :title="color"
                        :aria-label="color"
                        @click="selectAccent(color)"
                    >
                        <span
                            class="grid h-[34px] place-items-center rounded-md"
                            :style="{ background: ACCENT_SWATCH[color] }"
                        >
                            <i
                                v-show="form.accent_color === color"
                                class="pi pi-check text-[13px] text-white [text-shadow:0_1px_2px_rgba(0,0,0,.3)]"
                            />
                        </span>
                        <span
                            class="text-center text-[11px] font-semibold capitalize"
                            :class="form.accent_color === color ? 'text-surface-900 dark:text-surface-100' : 'text-surface-500 dark:text-surface-400'"
                        >
                            {{ color }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ── Arayüz Varsayılanları ────────────────────────────────── -->
            <div class="grid grid-cols-1 gap-x-11 gap-y-6 py-[26px] md:grid-cols-[216px_1fr]">
                <div>
                    <div class="text-sm font-bold tracking-tight text-surface-900 dark:text-surface-100">
                        {{ $t('sk-setting.appearance.interface_section_title') }}
                    </div>
                    <div class="mt-[7px] text-xs leading-relaxed text-surface-500 dark:text-surface-400">
                        {{ $t('sk-setting.appearance.interface_section_subtitle') }}
                    </div>
                </div>

                <div class="flex flex-col gap-2.5">
                    <!-- Koyu mod -->
                    <div class="flex items-center gap-3.5 rounded-md border border-surface-200 bg-surface-0 px-4 py-3.5 dark:border-surface-700 dark:bg-surface-900">
                        <span class="grid size-9 shrink-0 place-items-center rounded-md bg-primary-500/10 text-primary-500">
                            <i class="pi pi-moon text-[15px]" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[13.5px] font-bold text-surface-900 dark:text-surface-100">
                                {{ $t('sk-setting.appearance.dark_mode_label') }}
                            </div>
                            <div class="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                                {{ $t('sk-setting.appearance.dark_mode_hint') }}
                            </div>
                        </div>
                        <ToggleSwitch v-model="form.dark_mode_default" />
                    </div>

                    <!-- Yan menü stili -->
                    <div class="flex items-center gap-3.5 rounded-md border border-surface-200 bg-surface-0 px-4 py-3.5 dark:border-surface-700 dark:bg-surface-900">
                        <span class="grid size-9 shrink-0 place-items-center rounded-md bg-primary-500/10 text-primary-500">
                            <i class="pi pi-objects-column text-[15px]" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[13.5px] font-bold text-surface-900 dark:text-surface-100">
                                {{ $t('sk-setting.appearance.sidebar_section_title') }}
                            </div>
                            <div class="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                                {{ $t('sk-setting.appearance.sidebar_section_subtitle') }}
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-2.5">
                            <button
                                v-for="opt in [
                                    { value: 'colored', label: $t('sk-setting.appearance.sidebar_colored_label') },
                                    { value: 'light', label: $t('sk-setting.appearance.sidebar_light_label') },
                                ]"
                                :key="opt.value"
                                type="button"
                                class="flex items-center gap-2.5 rounded-md border px-3.5 py-2 transition-colors"
                                :class="
                                    form.sidebar_style === opt.value
                                        ? 'border-primary-500! bg-primary-500/8'
                                        : 'border-surface-200 hover:border-surface-300 dark:border-surface-700 dark:hover:border-surface-600'
                                "
                                @click="form.sidebar_style = opt.value"
                            >
                                <span
                                    class="grid size-[18px] shrink-0 place-items-center rounded-[5px] border transition-colors"
                                    :class="
                                        form.sidebar_style === opt.value
                                            ? 'border-primary-500! bg-primary-500'
                                            : 'border-surface-300 dark:border-surface-600'
                                    "
                                >
                                    <i v-show="form.sidebar_style === opt.value" class="pi pi-check text-[9px] text-white" />
                                </span>
                                <span
                                    class="text-[13.5px] font-medium"
                                    :class="form.sidebar_style === opt.value ? 'text-surface-900 dark:text-surface-100' : 'text-surface-600 dark:text-surface-300'"
                                >
                                    {{ opt.label }}
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card footer: unsaved hint + save -->
        <footer class="flex items-center gap-2.5 border-t border-surface-200 px-6 py-[15px] dark:border-surface-700">
            <small v-if="isDirty" class="mr-auto flex items-center gap-1.5 text-amber-600 dark:text-amber-400">
                <i class="pi pi-exclamation-circle text-xs" />
                {{ $t('sk-setting.appearance.unsaved') }}
            </small>
            <span v-else class="mr-auto" />
            <Button
                type="button"
                :label="$t('sk-button.update')"
                icon="pi pi-save"
                :loading="saving"
                :disabled="!isDirty"
                @click="save"
            />
        </footer>
    </section>
</template>
