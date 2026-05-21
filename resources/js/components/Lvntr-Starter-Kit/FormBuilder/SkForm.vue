<script setup lang="ts">
    import { useForm, usePage } from '@inertiajs/vue3';
    import type {
        DatePickerFieldConfig,
        ExistingMedia,
        FieldConfig,
        FileUploadFieldConfig,
        FormBuilderConfig,
        OptionFilter,
        SelectFieldConfig,
        SelectOption,
        SectionFieldConfig,
        TranslatableTextFieldConfig,
    } from './core';
    import type { SharedPageProps } from '@/types';
    import { useApi } from '@/composables/useApi';
    import { useCan } from '@/composables/useCan';
    import { useDefinition } from '@/composables/useDefinition';
    import { trans } from 'laravel-vue-i18n';
    import SkFormFieldRenderer from './SkFormFieldRenderer.vue';
    import type { RenderCtx } from './SkFormFieldRenderer.vue';

    /**
     * Render a field's label: translates it via laravel-vue-i18n when the label is
     * a translation key (default), or returns it as-is when the field opted out
     * via `.trans(false)` — i.e. already holds a pre-resolved string.
     */
    function displayLabel(field: FieldConfig): string {
        return field.translateLabel === false ? field.label : trans(field.label);
    }

    interface Props {
        config: FormBuilderConfig;
        /**
         * Validation errors — only used in v-model mode.
         * In internal mode (config.submit set), errors come from the internal Inertia form.
         */
        errors?: Record<string, string>;
    }

    const props = withDefaults(defineProps<Props>(), {
        errors: () => ({}),
    });

    /**
     * v-model — used in external form mode (when config.submit is NOT set).
     * Optional: when config.submit is set, FormBuilder manages form state internally.
     */
    const modelValue = defineModel<Record<string, unknown>>({ default: () => ({}) });

    const emit = defineEmits<{
        /** Fired after a successful Inertia submit (internal mode only). */
        success: [];
        /** Fired when the cancel button is clicked and onCancel is 'emit' (default). */
        cancel: [];
    }>();

    // ── Dialog / Back button logic ────────────────────────────────────────────
    const isDialogMode = computed(() => props.config.inDialog === true);
    const showCancelButton = computed(() => {
        // Explicit hideCancel takes priority
        if (props.config.actionLabels?.hideCancel !== undefined) {
            return !props.config.actionLabels.hideCancel;
        }
        return isDialogMode.value || props.config.showBack === true;
    });

    const cancelLabel = computed(() => {
        const key = props.config.actionLabels?.cancel ?? (isDialogMode.value ? 'sk-button.cancel' : 'sk-button.back');
        return trans(key);
    });

    const cancelIcon = computed(() => {
        if (props.config.actionLabels?.cancelIcon !== undefined) return props.config.actionLabels.cancelIcon;
        return isDialogMode.value ? undefined : 'pi pi-arrow-left';
    });

    function handleCancel(): void {
        const behavior = props.config.onCancel ?? (isDialogMode.value ? 'emit' : 'back');
        if (behavior === 'back') {
            window.history.back();
        } else {
            emit('cancel');
        }
    }

    const api = useApi();
    const { can } = useCan();
    const { options: definitionOptions, load: loadDefinitions } = useDefinition();

    // ── Permission gate ─────────────────────────────────────────────────────────
    /**
     * When config.permission is set and the current user lacks it, the form
     * becomes read-only: all fields are disabled and the submit button is hidden.
     */
    const isReadOnly = computed(() => !!props.config.permission && !can(props.config.permission));

    // ── Remote data loading ─────────────────────────────────────────────────────
    const restoringDefaults = ref(false);
    const dataLoading = ref(false);
    const remoteData = ref<Record<string, unknown> | null>(null);

    async function fetchRemoteData(): Promise<void> {
        if (!props.config.dataUrl) return;
        dataLoading.value = true;
        try {
            const response = await api.get<Record<string, unknown>>(props.config.dataUrl);
            remoteData.value = props.config.dataKey
                ? (response[props.config.dataKey] as Record<string, unknown>)
                : response;
        } catch (e) {
            console.error('[SkForm] Failed to fetch remote data:', e);
        } finally {
            dataLoading.value = false;
        }
    }

    /**
     * Collect all definitionKey values from select fields to preload them.
     * Uses flatFields so section-nested select fields are also included.
     */
    const definitionKeys = computed(() =>
        Array.from(iterateAllFields(props.config.fields))
            .filter((f): f is SelectFieldConfig => {
                if (!SELECT_TYPES.has(f.type)) return false;
                const sf = f as SelectFieldConfig;
                return !!(sf.definitionKey ?? sf.enumKey);
            })
            .map((f) => ((f as SelectFieldConfig).definitionKey ?? (f as SelectFieldConfig).enumKey)!),
    );

    onMounted(async () => {
        if (props.config.dataUrl) {
            fetchRemoteData();
        }
        if (definitionKeys.value.length > 0) {
            await loadDefinitions(definitionKeys.value);
        }
    });

    // ── Field iteration helper (section flattening) ───────────────────────────

    /**
     * Generator that yields all non-section leaf fields, recursively opening
     * sections. Used to build flat lists for defaults/options/submit logic.
     */
    function* iterateAllFields(fields: FieldConfig[]): Iterable<FieldConfig> {
        for (const f of fields) {
            if (f.type === 'section') {
                yield* iterateAllFields((f as SectionFieldConfig).fields);
            } else {
                yield f;
            }
        }
    }

    // ── Resolve existingMediaKey from loaded data ─────────────────────────────

    /**
     * Resolves `existingMediaKey` for file-upload fields.
     * Recursive: also resolves fields nested inside sections.
     */
    const resolvedFields = computed<FieldConfig[]>(() => {
        const data = remoteData.value ?? props.config.initialData;
        if (!data) return props.config.fields;

        // Capture as non-nullable for use inside the nested closure
        const resolvedData: Record<string, unknown> = data;

        function resolveField(field: FieldConfig): FieldConfig {
            if (field.type === 'section') {
                const sec = field as SectionFieldConfig;
                return { ...sec, fields: sec.fields.map(resolveField) };
            }
            if (field.type !== 'file-upload') return field;
            const f = field as FileUploadFieldConfig;
            if (!f.existingMediaKey) return field;
            const media = resolvedData[f.existingMediaKey];
            const resolved: ExistingMedia[] = Array.isArray(media) ? media : media ? [media as ExistingMedia] : [];
            return { ...f, existingMedia: resolved };
        }

        return props.config.fields.map(resolveField);
    });

    /**
     * Flat list of all leaf input fields (sections opened, non-input types excluded).
     * Used for derivedDefaults, currentValues, hasFileFields, dateOnlyFields, etc.
     */
    const flatFields = computed<FieldConfig[]>(() => Array.from(iterateAllFields(resolvedFields.value)));

    // ── Field type sets ─────────────────────────────────────────────────────────
    const NON_INPUT_TYPES = new Set(['title', 'slot', 'section']);
    const SELECT_TYPES = new Set(['select', 'multiselect', 'radio', 'select-button', 'checkbox-group']);
    const INLINE_LABEL_TYPES = new Set(['checkbox', 'toggle-button', 'toggle-switch']);

    // ── Internal form mode (Inertia useForm) ─────────────────────────────────────

    // ── Translatable field helpers ──────────────────────────────────────────────

    function isTranslatableField(field: FieldConfig): boolean {
        return (
            field.type === 'translatable-text' ||
            field.type === 'translatable-textarea' ||
            field.type === 'translatable-editor'
        );
    }

    function sk_locale_keys_from_page(): string[] {
        const page = usePage<SharedPageProps>();
        return Object.keys(page.props.availableLocales ?? {});
    }

    function applyLocaleFilters(locales: string[], field: FieldConfig): string[] {
        const cfg = field as Partial<TranslatableTextFieldConfig>;
        let out = locales;
        if (cfg.onlyLocales?.length) out = out.filter((l) => cfg.onlyLocales!.includes(l));
        if (cfg.exceptLocales?.length) out = out.filter((l) => !cfg.exceptLocales!.includes(l));
        return out;
    }

    function buildTranslatableDefault(field: FieldConfig): Record<string, string> {
        const locales = sk_locale_keys_from_page();
        const filtered = applyLocaleFilters(locales, field);
        return Object.fromEntries(filtered.map((l) => [l, '']));
    }

    function translatableErrorsFor(field: FieldConfig): Record<string, string> | undefined {
        if (!isTranslatableField(field)) return undefined;
        const result: Record<string, string> = {};
        const prefix = `${field.key}.`;
        for (const [k, v] of Object.entries(activeErrors.value)) {
            if (k.startsWith(prefix)) result[k] = String(v);
        }
        return Object.keys(result).length > 0 ? result : undefined;
    }

    /**
     * Auto-derive initial form values.
     * Priority: initialData[key] → field.defaultValue → null
     * Uses flatFields so section-nested fields are included; section nodes themselves are excluded.
     */
    const derivedDefaults = computed(() => {
        const initial = remoteData.value ?? props.config.initialData ?? {};
        return Object.fromEntries(
            flatFields.value
                .filter((f) => !NON_INPUT_TYPES.has(f.type))
                .map((f) => {
                    let fromData = initial[f.key];
                    if (fromData !== undefined && fromData !== null) {
                        if (f.type === 'date-picker' && typeof fromData === 'string') {
                            fromData = new Date(fromData);
                        }
                        return [f.key, fromData];
                    }
                    // Translatable field'lar için locale bazlı boş Record üret
                    if (isTranslatableField(f)) {
                        return [f.key, buildTranslatableDefault(f)];
                    }
                    return [f.key, f.defaultValue ?? null];
                }),
        );
    });

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const internalForm = useForm(derivedDefaults.value as any);

    const isInternalMode = computed(() => !!props.config.submit);
    const isEditMode = computed(() => ['put', 'patch'].includes(props.config.submit?.method ?? ''));

    /**
     * Shallow equality for plain value records — Date instances compared by time.
     * Used to skip redundant resets when derivedDefaults recomputes with identical data.
     */
    function shallowRecordEqual(a: Record<string, unknown>, b: Record<string, unknown>): boolean {
        const aKeys = Object.keys(a);
        const bKeys = Object.keys(b);
        if (aKeys.length !== bKeys.length) {
            return false;
        }
        for (const key of aKeys) {
            const av = a[key];
            const bv = b[key];
            if (av instanceof Date && bv instanceof Date) {
                if (av.getTime() !== bv.getTime()) {
                    return false;
                }
                continue;
            }
            if (av !== bv) {
                return false;
            }
        }
        return true;
    }

    /**
     * When field defaults change (e.g. a different record is loaded into a dialog),
     * re-populate the internal form and clear validation errors.
     *
     * Guard: page.props refresh (e.g. Inertia back()) can rebuild formConfig and
     * produce a new derivedDefaults object with identical values. Skip the reset
     * in that case so the user's in-progress edits are not clobbered by stale
     * remoteData captured at the initial mount.
     */
    watch(derivedDefaults, (newValues, oldValues) => {
        if (!isInternalMode.value) {
            return;
        }
        if (oldValues && shallowRecordEqual(newValues, oldValues)) {
            return;
        }
        // Protect user input: once the form is dirty, treat derivedDefaults
        // changes as "new baseline" rather than an overwrite. This prevents
        // a late-arriving async payload or a parent re-render from wiping
        // the values the user is actively typing.
        if (internalForm.isDirty) {
            internalForm.defaults(newValues);
            return;
        }
        restoringDefaults.value = true;
        internalForm.defaults(newValues);
        for (const [key, value] of Object.entries(newValues)) {
            (internalForm as unknown as Record<string, unknown>)[key] = value;
        }
        internalForm.clearErrors();
        nextTick(() => {
            restoringDefaults.value = false;
        });
    });

    // ── Unified value & error access ─────────────────────────────────────────────

    /** Unified read/write for form field values (internal or external mode). */
    function getValue(key: string): unknown {
        if (isInternalMode.value) {
            return (internalForm as unknown as Record<string, unknown>)[key];
        }
        return modelValue.value[key];
    }

    function setValue(key: string, value: unknown): void {
        if (isInternalMode.value) {
            (internalForm as unknown as Record<string, unknown>)[key] = value;
        } else {
            modelValue.value = { ...modelValue.value, [key]: value };
        }
    }

    /**
     * Snapshot of all current values — used for visible/disabled callbacks and dynamic options.
     * Uses flatFields (section-nested fields included, section nodes excluded).
     */
    const currentValues = computed<Record<string, unknown>>(() => {
        if (isInternalMode.value) {
            return Object.fromEntries(
                flatFields.value.map((f) => [f.key, (internalForm as unknown as Record<string, unknown>)[f.key]]),
            );
        }
        return modelValue.value;
    });

    const activeErrors = computed<Record<string, string>>(() =>
        isInternalMode.value ? (internalForm.errors as Record<string, string>) : props.errors,
    );

    // ── Submit & Reset ────────────────────────────────────────────────────────────

    /** Check if the form contains any file upload fields (including section-nested). */
    const hasFileFields = computed(() => flatFields.value.some((f) => f.type === 'file-upload'));

    function handleSubmit(): void {
        if (!props.config.submit || isReadOnly.value) {
            return;
        }
        const { url, method, preserveScroll = true } = props.config.submit;

        const dateOnlyFields = flatFields.value.filter(
            (f): f is DatePickerFieldConfig => f.type === 'date-picker' && !f.showTime,
        );

        function toLocalDateStr(d: Date): string {
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        }

        internalForm
            .transform((data: Record<string, unknown>) => {
                if (dateOnlyFields.length === 0) return data;
                const result = { ...data };
                for (const field of dateOnlyFields) {
                    const val = result[field.key];
                    if (val instanceof Date) {
                        result[field.key] = toLocalDateStr(val);
                    } else if (Array.isArray(val)) {
                        result[field.key] = val.map((v) => (v instanceof Date ? toLocalDateStr(v) : v));
                    }
                }
                return result;
            })
            [method](url, {
                preserveScroll,
                forceFormData: hasFileFields.value,
                onSuccess: () => emit('success'),
            });
    }

    function reset(): void {
        if (!isInternalMode.value) {
            return;
        }
        internalForm.reset();
        internalForm.clearErrors();
    }

    defineExpose({ reset, dataLoading, remoteData, currentValues, setValue });

    // ── Dynamic Options ───────────────────────────────────────────────────────────

    const dynamicOptions = ref<Record<string, SelectOption[]>>({});
    const loadingOptions = ref<Set<string>>(new Set());
    const lastOptionUrl = ref<Record<string, string | null>>({});

    const dynamicSelectFields = computed<SelectFieldConfig[]>(() =>
        flatFields.value.filter(
            (f): f is SelectFieldConfig =>
                ['select', 'multiselect', 'radio', 'select-button'].includes(f.type) &&
                !!(f as SelectFieldConfig).optionsUrl,
        ),
    );

    async function fetchOptions(field: SelectFieldConfig, url: string): Promise<void> {
        loadingOptions.value = new Set([...loadingOptions.value, field.key]);
        try {
            const options = await api.get<SelectOption[]>(url);
            dynamicOptions.value = { ...dynamicOptions.value, [field.key]: options };
        } catch {
            dynamicOptions.value = { ...dynamicOptions.value, [field.key]: [] };
        } finally {
            const next = new Set(loadingOptions.value);
            next.delete(field.key);
            loadingOptions.value = next;
        }
    }

    async function syncDynamicOptions(optionUrls: Record<string, string | null>, isInitial = false): Promise<void> {
        const skipReset = isInitial || restoringDefaults.value;
        for (const field of dynamicSelectFields.value) {
            const url = optionUrls[field.key] ?? null;
            const prev = lastOptionUrl.value[field.key] ?? undefined;
            if (url === prev) {
                continue;
            }

            lastOptionUrl.value = { ...lastOptionUrl.value, [field.key]: url };

            if (!url) {
                dynamicOptions.value = { ...dynamicOptions.value, [field.key]: [] };
                continue;
            }

            if (!skipReset && prev !== undefined) {
                setValue(field.key, null);
            }

            await fetchOptions(field, url);
        }
    }

    const dynamicOptionUrls = computed<Record<string, string | null>>(() =>
        Object.fromEntries(
            dynamicSelectFields.value.map((field) => {
                const url =
                    typeof field.optionsUrl === 'function'
                        ? field.optionsUrl(currentValues.value)
                        : (field.optionsUrl ?? null);

                return [field.key, url];
            }),
        ),
    );

    onMounted(() => syncDynamicOptions(dynamicOptionUrls.value, true));

    watch(dynamicOptionUrls, (optionUrls) => {
        void syncDynamicOptions(optionUrls);
    });

    // ── Field Helpers ─────────────────────────────────────────────────────────────

    function applyFilter(items: SelectOption[], filter?: OptionFilter): SelectOption[] {
        if (!filter) return items;
        if (filter.only) {
            const allowed = new Set(filter.only.map(String));
            return items.filter((item) => allowed.has(String(item.value)));
        }
        if (filter.except) {
            const excluded = new Set(filter.except.map(String));
            return items.filter((item) => !excluded.has(String(item.value)));
        }
        return items;
    }

    function getOptions(field: FieldConfig): SelectOption[] {
        if (!SELECT_TYPES.has(field.type)) {
            return [];
        }
        const sf = field as SelectFieldConfig;
        const defKey = sf.definitionKey ?? sf.enumKey;
        if (defKey) {
            return applyFilter(definitionOptions(defKey), sf.definitionFilter ?? sf.enumFilter);
        }
        return sf.optionsUrl ? (dynamicOptions.value[field.key] ?? []) : (sf.options ?? []);
    }

    function isVisible(field: FieldConfig): boolean {
        return field.visible ? field.visible(currentValues.value) : true;
    }

    function isDisabled(field: FieldConfig): boolean {
        if (isReadOnly.value) {
            return true;
        }
        return field.disabled ? field.disabled(currentValues.value) : false;
    }

    function isLoading(field: FieldConfig): boolean {
        return loadingOptions.value.has(field.key);
    }

    function hasInlineLabel(field: FieldConfig): boolean {
        return INLINE_LABEL_TYPES.has(field.type);
    }

    function hasInlineFieldLabel(field: FieldConfig): boolean {
        return !hasInlineLabel(field) && field.labelPlacement === 'inline';
    }

    function isControlRight(field: FieldConfig): boolean {
        return field.controlPosition === 'right';
    }

    // ── Actions ───────────────────────────────────────────────────────────────────

    const showTopActions = computed(() => {
        if (isDialogMode.value) return false;
        const pos = props.config.actionsPosition;
        return pos === 'top' || pos === 'both';
    });

    const showBottomActions = computed(() => {
        if (isDialogMode.value) return true;
        const pos = props.config.actionsPosition;
        return !pos || pos === 'bottom' || pos === 'both';
    });

    const slots = useSlots();
    const hasActionArea = computed(
        () => isInternalMode.value || !!slots.actions || !!slots['actions-start'] || !!slots['actions-end'],
    );

    // ── Grid helpers ──────────────────────────────────────────────────────────────

    const colsClassMap: Record<number, string> = {
        1: 'grid-cols-1',
        2: 'grid-cols-1 md:grid-cols-2',
        3: 'grid-cols-1 md:grid-cols-3',
        4: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4',
        5: 'grid-cols-1 md:grid-cols-5',
        6: 'grid-cols-1 md:grid-cols-6',
    };

    const gridClass = computed(() => colsClassMap[props.config.cols] ?? 'grid-cols-2');

    // ── Card passthrough ─────────────────────────────────────────────────────────

    const transparentCard = { style: 'background: transparent; box-shadow: none; border: 0; padding: 0' };

    const cardPt = computed(() => {
        // Dialog mode or isCard false → invisible wrapper
        if (props.config.inDialog || !props.config.isCard) {
            return {
                root: transparentCard,
                body: { style: 'padding: 0' },
                content: { style: 'padding: 0' },
            };
        }
        // isCard true → show Card with bg/shadow
        return {};
    });

    // ── renderCtx — passed to SkFormFieldRenderer ─────────────────────────────

    const renderCtx = computed<RenderCtx>(() => ({
        config: props.config,
        getValue,
        setValue,
        getOptions,
        isVisible,
        isDisabled,
        isLoading,
        hasInlineLabel,
        hasInlineFieldLabel,
        isControlRight,
        isTranslatableField,
        displayLabel,
        translatableErrorsFor,
        activeErrors: activeErrors.value,
        colsClassMap,
        transparentCard,
        currentValues: currentValues.value,
    }));
</script>

<template>
    <Card :pt="cardPt">
        <template v-if="config.cardTitle" #title>
            {{ $t(config.cardTitle) }}
        </template>
        <template v-if="config.cardSubtitle" #subtitle>
            {{ $t(config.cardSubtitle) }}
        </template>
        <template #content>
            <!-- ── Loading skeleton (when dataUrl is set and data is loading) ──────── -->
            <div v-if="dataLoading" class="sk-fb__skeleton" :class="config.cssClass">
                <div class="grid gap-5" :class="gridClass">
                    <div
                        v-for="field in flatFields.filter((f) => !NON_INPUT_TYPES.has(f.type) && !f.hidden)"
                        :key="field.key"
                        class="flex flex-col gap-2"
                        :class="field.cssClass"
                    >
                        <div class="h-4 w-24 rounded bg-surface-200 dark:bg-surface-700 animate-pulse" />
                        <div class="h-10 rounded bg-surface-200 dark:bg-surface-700 animate-pulse" />
                    </div>
                </div>
            </div>

            <!--
        Root element is <form> in internal mode (config.submit set) so that
        type="submit" buttons work naturally. In v-model mode it's a plain <div>.
    -->
            <component
                :is="isInternalMode ? 'form' : 'div'"
                v-else
                class="sk-fb"
                :class="[{ 'sk-fb--dialog': isDialogMode }, config.cssClass]"
                @submit.prevent="handleSubmit"
            >
                <!-- ── Top actions ─────────────────────────────────────────────────────── -->
                <div v-if="showTopActions && hasActionArea" class="sk-fb__actions sk-fb__actions--top">
                    <slot name="actions-start" />
                    <template v-if="isInternalMode">
                        <Button
                            v-if="showCancelButton"
                            :label="cancelLabel"
                            :icon="cancelIcon"
                            severity="secondary"
                            outlined
                            type="button"
                            @click="handleCancel"
                        />
                    </template>
                    <slot name="actions" />
                    <template v-if="isInternalMode">
                        <Button
                            v-if="!config.actionLabels?.hideSubmit && !isReadOnly"
                            :label="
                                config.actionLabels?.submit
                                    ? $t(config.actionLabels.submit)
                                    : isEditMode
                                        ? $t('sk-button.update')
                                        : $t('sk-button.save')
                            "
                            :icon="config.actionLabels?.submitIcon ?? (isEditMode ? 'pi pi-check' : 'pi pi-plus')"
                            type="submit"
                            :loading="internalForm.processing"
                            :disabled="!internalForm.isDirty"
                        />
                    </template>
                    <slot name="actions-end" />
                </div>

                <!-- ── Fields grid ─────────────────────────────────────────────────────── -->
                <div class="sk-fb__grid" :class="gridClass">
                    <template v-for="field in resolvedFields" :key="field.key">
                        <!--
                            Section fields are handled by SkFormFieldRenderer (Card wrapper + grid).
                            Hidden fields (non-section): render as hidden input directly.
                            Visible fields: delegate all rendering to SkFormFieldRenderer.
                        -->
                        <input
                            v-if="field.hidden && field.type !== 'section'"
                            type="hidden"
                            :name="field.key"
                            :value="String(getValue(field.key) ?? '')"
                        >

                        <div
                            v-else-if="isVisible(field)"
                            :class="[field.type !== 'section' ? field.cssClass : undefined]"
                            :style="field.type === 'section' ? 'grid-column: 1 / -1' : undefined"
                        >
                            <SkFormFieldRenderer :field="field" :ctx="renderCtx">
                                <template v-for="(_, name) in $slots" #[name]="slotData">
                                    <slot :name="name" v-bind="slotData ?? {}" />
                                </template>
                            </SkFormFieldRenderer>
                        </div>
                    </template>
                </div>

                <!-- ── Bottom actions ─────────────────────────────────────────────────── -->
                <div v-if="showBottomActions && hasActionArea" class="sk-fb__actions sk-fb__actions--bottom">
                    <slot name="actions-start" />
                    <template v-if="isInternalMode">
                        <Button
                            v-if="showCancelButton"
                            :label="cancelLabel"
                            :icon="cancelIcon"
                            severity="secondary"
                            outlined
                            type="button"
                            @click="handleCancel"
                        />
                    </template>
                    <slot name="actions" />
                    <template v-if="isInternalMode">
                        <Button
                            v-if="!config.actionLabels?.hideSubmit && !isReadOnly"
                            :label="
                                config.actionLabels?.submit
                                    ? $t(config.actionLabels.submit)
                                    : isEditMode
                                        ? $t('sk-button.update')
                                        : $t('sk-button.save')
                            "
                            :icon="config.actionLabels?.submitIcon ?? (isEditMode ? 'pi pi-save' : 'pi pi-save')"
                            type="submit"
                            severity="primary"
                            :loading="internalForm.processing"
                            :disabled="!internalForm.isDirty"
                        />
                    </template>
                    <slot name="actions-end" />
                </div>
            </component>
        </template>
    </Card>
</template>
