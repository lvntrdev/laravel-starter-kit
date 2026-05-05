<script setup lang="ts">
    import type {
        TranslatableEditorFieldConfig,
        TranslatableTextareaFieldConfig,
        TranslatableTextFieldConfig,
    } from '@lvntr/components/FormBuilder/core';
    import EditorInput from '@lvntr/components/FormBuilder/inputs/EditorInput.vue';
    import { usePage } from '@inertiajs/vue3';
    import type { SharedPageProps } from '@/types';

    interface Props {
        field: TranslatableTextFieldConfig | TranslatableTextareaFieldConfig | TranslatableEditorFieldConfig;
        modelValue: Record<string, string> | null | undefined;
        errors?: Record<string, string>;
        disabled?: boolean;
        autocomplete?: string;
    }

    const props = withDefaults(defineProps<Props>(), {
        modelValue: undefined,
        errors: undefined,
        disabled: false,
        autocomplete: undefined,
    });

    const emit = defineEmits<{
        'update:modelValue': [Record<string, string>];
        update: [Record<string, string>];
    }>();

    // ── Locale resolution ────────────────────────────────────────────────────────

    const page = usePage<SharedPageProps>();

    const resolvedLocales = computed<Array<{ code: string; name: string }>>(() => {
        const available = page.props.availableLocales ?? {};
        let locales = Object.entries(available).map(([code, name]) => ({ code, name }));

        if (props.field.onlyLocales?.length) {
            locales = locales.filter((l) => props.field.onlyLocales!.includes(l.code));
        }
        if (props.field.exceptLocales?.length) {
            locales = locales.filter((l) => !props.field.exceptLocales!.includes(l.code));
        }

        return locales;
    });

    const isSingle = computed(() => resolvedLocales.value.length <= 1);
    const layout = computed(() => props.field.translatableLayout ?? 'inline');
    const fieldType = computed(() => props.field.type);

    // ── Internal reactive value ──────────────────────────────────────────────────

    const value = ref<Record<string, string>>({});

    function normalizeValue(raw: Record<string, string> | null | undefined): Record<string, string> {
        const base = raw ?? {};
        const normalized: Record<string, string> = {};
        for (const loc of resolvedLocales.value) {
            normalized[loc.code] = base[loc.code] ?? '';
        }
        return normalized;
    }

    // init
    value.value = normalizeValue(props.modelValue);

    watch(
        () => props.modelValue,
        (next) => {
            value.value = normalizeValue(next);
        },
    );

    watch(resolvedLocales, () => {
        value.value = normalizeValue(props.modelValue);
    });

    function handleUpdate(locale: string, newVal: string): void {
        const updated = { ...value.value, [locale]: newVal };
        value.value = updated;
        emit('update:modelValue', updated);
        emit('update', updated);
    }

    // ── Tabs state ───────────────────────────────────────────────────────────────

    const activeTab = ref<string>('');

    watch(
        resolvedLocales,
        (locs) => {
            if (!activeTab.value && locs.length > 0) {
                activeTab.value = locs[0].code;
            }
        },
        { immediate: true },
    );

    // ── Helpers ──────────────────────────────────────────────────────────────────

    function getFlagEmoji(code: string): string {
        const flagMap: Record<string, string> = {
            tr: '🇹🇷',
            en: '🇬🇧',
            de: '🇩🇪',
            fr: '🇫🇷',
            es: '🇪🇸',
            it: '🇮🇹',
            ru: '🇷🇺',
            ar: '🇸🇦',
            zh: '🇨🇳',
            ja: '🇯🇵',
            ko: '🇰🇷',
            pt: '🇵🇹',
            nl: '🇳🇱',
            pl: '🇵🇱',
        };
        if (code in flagMap) return flagMap[code];
        // Generic fallback: regional indicator letters from ISO country code
        const upper = code.slice(0, 2).toUpperCase();
        return [...upper].map((c) => String.fromCodePoint(c.codePointAt(0)! + 127397)).join('');
    }

    function formatLocaleLabel(loc: { code: string; name: string }): string {
        if (props.field.localeLabelStyle === 'name') return loc.name;
        if (props.field.localeLabelStyle === 'flag') return getFlagEmoji(loc.code);
        return loc.code.toUpperCase();
    }

    function errorFor(locale: string): string | undefined {
        return props.errors?.[`${props.field.key}.${locale}`];
    }

    function hasErrorInLocale(locale: string): boolean {
        return !!errorFor(locale);
    }

    // ── Editor field accessor ─────────────────────────────────────────────────────

    const asTranslatableEditor = computed(() => props.field as TranslatableEditorFieldConfig);
    const asTranslatableTextarea = computed(() => props.field as TranslatableTextareaFieldConfig);
    const asTranslatableText = computed(() => props.field as TranslatableTextFieldConfig);
</script>

<template>
    <div class="sk-translatable-field" :class="{ 'sk-translatable-field--single': isSingle }">
        <!-- Tek dil -->
        <template v-if="isSingle">
            <template v-if="resolvedLocales.length === 1">
                <InputText
                    v-if="fieldType === 'translatable-text'"
                    :model-value="value[resolvedLocales[0].code] ?? ''"
                    :type="asTranslatableText.inputType ?? 'text'"
                    :placeholder="asTranslatableText.placeholder"
                    :maxlength="asTranslatableText.maxLength"
                    :disabled="disabled"
                    :invalid="!!errorFor(resolvedLocales[0].code)"
                    :autocomplete="autocomplete"
                    class="w-full"
                    v-bind="field.componentProps"
                    @update:model-value="(v) => handleUpdate(resolvedLocales[0].code, String(v ?? ''))"
                />
                <Textarea
                    v-else-if="fieldType === 'translatable-textarea'"
                    :model-value="value[resolvedLocales[0].code] ?? ''"
                    :placeholder="asTranslatableTextarea.placeholder"
                    :rows="asTranslatableTextarea.rows ?? 4"
                    :auto-resize="asTranslatableTextarea.autoResize ?? false"
                    :disabled="disabled"
                    :invalid="!!errorFor(resolvedLocales[0].code)"
                    class="w-full"
                    v-bind="field.componentProps"
                    @update:model-value="(v) => handleUpdate(resolvedLocales[0].code, String(v ?? ''))"
                />
                <EditorInput
                    v-else-if="fieldType === 'translatable-editor'"
                    :model-value="value[resolvedLocales[0].code] ?? ''"
                    :min-height="asTranslatableEditor.minHeight ?? '10rem'"
                    :toolbar="asTranslatableEditor.toolbar ?? 'standard'"
                    :disabled="disabled"
                    :invalid="!!errorFor(resolvedLocales[0].code)"
                    class="w-full"
                    @update:model-value="(v) => handleUpdate(resolvedLocales[0].code, v)"
                />
                <small v-if="errorFor(resolvedLocales[0].code)" class="p-error block mt-1">
                    {{ errorFor(resolvedLocales[0].code) }}
                </small>
            </template>
            <!-- resolvedLocales boşsa hiçbir şey render etme -->
        </template>

        <!-- Çok dil, inline -->
        <template v-else-if="layout === 'inline'">
            <div
                v-for="loc in resolvedLocales"
                :key="loc.code"
                class="sk-translatable-field__row"
            >
                <InputGroup>
                    <InputGroupAddon class="sk-translatable-field__locale">
                        {{ formatLocaleLabel(loc) }}
                    </InputGroupAddon>
                    <InputText
                        v-if="fieldType === 'translatable-text'"
                        :model-value="value[loc.code] ?? ''"
                        :type="asTranslatableText.inputType ?? 'text'"
                        :placeholder="asTranslatableText.placeholder"
                        :maxlength="asTranslatableText.maxLength"
                        :disabled="disabled"
                        :invalid="hasErrorInLocale(loc.code)"
                        :autocomplete="autocomplete"
                        class="w-full"
                        v-bind="field.componentProps"
                        @update:model-value="(v) => handleUpdate(loc.code, String(v ?? ''))"
                    />
                    <Textarea
                        v-else-if="fieldType === 'translatable-textarea'"
                        :model-value="value[loc.code] ?? ''"
                        :placeholder="asTranslatableTextarea.placeholder"
                        :rows="asTranslatableTextarea.rows ?? 4"
                        :auto-resize="asTranslatableTextarea.autoResize ?? false"
                        :disabled="disabled"
                        :invalid="hasErrorInLocale(loc.code)"
                        class="w-full"
                        v-bind="field.componentProps"
                        @update:model-value="(v) => handleUpdate(loc.code, String(v ?? ''))"
                    />
                    <EditorInput
                        v-else-if="fieldType === 'translatable-editor'"
                        :model-value="value[loc.code] ?? ''"
                        :min-height="asTranslatableEditor.minHeight ?? '10rem'"
                        :toolbar="asTranslatableEditor.toolbar ?? 'standard'"
                        :disabled="disabled"
                        :invalid="hasErrorInLocale(loc.code)"
                        class="w-full"
                        @update:model-value="(v) => handleUpdate(loc.code, v)"
                    />
                </InputGroup>
                <small v-if="errorFor(loc.code)" class="p-error block mt-1">{{ errorFor(loc.code) }}</small>
            </div>
        </template>

        <!-- Çok dil, tabs -->
        <template v-else>
            <Tabs :value="activeTab" @update:value="(v) => (activeTab = String(v))">
                <TabList>
                    <Tab
                        v-for="loc in resolvedLocales"
                        :key="loc.code"
                        :value="loc.code"
                    >
                        {{ formatLocaleLabel(loc) }}
                        <i
                            v-if="hasErrorInLocale(loc.code)"
                            class="pi pi-exclamation-circle text-red-500 ml-1"
                            aria-hidden="true"
                        />
                    </Tab>
                </TabList>
                <TabPanels>
                    <TabPanel
                        v-for="loc in resolvedLocales"
                        :key="loc.code"
                        :value="loc.code"
                    >
                        <InputText
                            v-if="fieldType === 'translatable-text'"
                            :model-value="value[loc.code] ?? ''"
                            :type="asTranslatableText.inputType ?? 'text'"
                            :placeholder="asTranslatableText.placeholder"
                            :maxlength="asTranslatableText.maxLength"
                            :disabled="disabled"
                            :invalid="hasErrorInLocale(loc.code)"
                            :autocomplete="autocomplete"
                            class="w-full"
                            v-bind="field.componentProps"
                            @update:model-value="(v) => handleUpdate(loc.code, String(v ?? ''))"
                        />
                        <Textarea
                            v-else-if="fieldType === 'translatable-textarea'"
                            :model-value="value[loc.code] ?? ''"
                            :placeholder="asTranslatableTextarea.placeholder"
                            :rows="asTranslatableTextarea.rows ?? 4"
                            :auto-resize="asTranslatableTextarea.autoResize ?? false"
                            :disabled="disabled"
                            :invalid="hasErrorInLocale(loc.code)"
                            class="w-full"
                            v-bind="field.componentProps"
                            @update:model-value="(v) => handleUpdate(loc.code, String(v ?? ''))"
                        />
                        <EditorInput
                            v-else-if="fieldType === 'translatable-editor'"
                            :model-value="value[loc.code] ?? ''"
                            :min-height="asTranslatableEditor.minHeight ?? '10rem'"
                            :toolbar="asTranslatableEditor.toolbar ?? 'standard'"
                            :disabled="disabled"
                            :invalid="hasErrorInLocale(loc.code)"
                            class="w-full"
                            @update:model-value="(v) => handleUpdate(loc.code, v)"
                        />
                        <small v-if="errorFor(loc.code)" class="p-error block mt-1">{{ errorFor(loc.code) }}</small>
                    </TabPanel>
                </TabPanels>
            </Tabs>
        </template>
    </div>
</template>

<style scoped>
    .sk-translatable-field__row + .sk-translatable-field__row {
        margin-top: 0.5rem;
    }

    .sk-translatable-field__locale {
        min-width: 3rem;
        justify-content: center;
        font-weight: 600;
    }
</style>
