<script setup lang="ts">
    import type {
        ColorSelectorFieldConfig,
        EditorFieldConfig,
        ExistingMedia,
        FieldConfig,
        FileUploadFieldConfig,
        InputNumberFieldConfig,
        InputOtpFieldConfig,
        InputMaskFieldConfig,
        DatePickerFieldConfig,
        InputTextFieldConfig,
        PasswordFieldConfig,
        PasswordGeneratorConfig,
        SelectFieldConfig,
        SelectOption,
        TextareaFieldConfig,
        ToggleButtonFieldConfig,
        TranslatableTextFieldConfig,
        TranslatableTextareaFieldConfig,
        TranslatableEditorFieldConfig,
    } from './core';
    import ColorSelector from './SkColorSelector.vue';
    import EditorInput from './inputs/EditorInput.vue';
    import TranslatableInput from './inputs/TranslatableInput.vue';
    import SkIcon from '../ui/SkIcon.vue';
    import { generatePassword } from './utils/passwordGenerator';
    import FilePreviewModal, {
        suggestedPreviewWidth,
        type FilePreviewFile,
    } from '../ui/FilePreviewModal.vue';
    import { InputGroup } from 'primevue';
    import { useToast } from 'primevue/usetoast';
    import { useApi } from '@/composables/useApi';
    import { useConfirm } from '@/composables/useConfirm';
    import { useDialog } from '@/composables/useDialog';
    import { useImageLightbox } from '@/composables/useImageLightbox';
    import { trans } from 'laravel-vue-i18n';

    interface Props {
        field: FieldConfig;
        value: unknown;
        disabled?: boolean;
        invalid?: boolean;
        options?: SelectOption[];
        loading?: boolean;
        translatableErrors?: Record<string, string>;
        /** Translatable alanlarda label'ı TranslatableInput bassın (dil seçici label yanında). */
        translatableLabel?: boolean;
    }

    const props = withDefaults(defineProps<Props>(), {
        disabled: false,
        invalid: false,
        options: () => [],
        loading: false,
        translatableErrors: undefined,
        translatableLabel: false,
    });

    const emit = defineEmits<{
        update: [value: unknown];
    }>();

    // ── Type-narrowed accessors ───────────────────────────────────────────────────

    const asInputText = computed(() => props.field as InputTextFieldConfig);
    const asInputNumber = computed(() => props.field as InputNumberFieldConfig);
    const asInputOtp = computed(() => props.field as InputOtpFieldConfig);
    const asInputMask = computed(() => props.field as InputMaskFieldConfig);
    const asDatePicker = computed(() => props.field as DatePickerFieldConfig);
    const asSelect = computed(() => props.field as SelectFieldConfig);
    const asPassword = computed(() => props.field as PasswordFieldConfig);
    const asTextarea = computed(() => props.field as TextareaFieldConfig);
    const asEditor = computed(() => props.field as EditorFieldConfig);
    const asTranslatable = computed(
        () =>
            props.field as
                | TranslatableTextFieldConfig
                | TranslatableTextareaFieldConfig
                | TranslatableEditorFieldConfig,
    );
    const translatableValue = computed(() => props.value as Record<string, string> | null | undefined);
    const asToggleButton = computed(() => props.field as ToggleButtonFieldConfig);
    const asFileUpload = computed(() => props.field as FileUploadFieldConfig);
    const asColorSelector = computed(() => props.field as ColorSelectorFieldConfig);

    /** Extra props passed to the underlying PrimeVue component via .props({...}). */
    const extraProps = computed(() => props.field.componentProps ?? {});

    /** Translate option labels (and optional descriptions) via trans() so consumers can pass translation keys. */
    const translatedOptions = computed(() =>
        props.options.map((opt) => ({
            ...opt,
            label: trans(opt.label),
            description: opt.description ? trans(opt.description) : undefined,
        })),
    );
    const controlPosition = computed(() => props.field.controlPosition ?? 'left');

    /**
     * Render password fields as plain InputText + our own eye/generate
     * addons by default. Only fall back to PrimeVue's `<Password>` when the
     * consumer explicitly opts into its strength-meter feedback, since that
     * component owns its own absolute-positioned icons and fights InputGroup.
     */
    const useCustomPasswordInput = computed(() => props.field.type === 'password' && !asPassword.value.feedback);

    // ── Icon support ─────────────────────────────────────────────────────────────

    /**
     * Generic input icon resolver.
     * BaseFieldConfig.icon is preferred; InputTextFieldConfig.icon is the
     * legacy fallback (deprecated, kept for backwards-compatibility).
     */
    const inputIcon = computed<string | undefined>(() => {
        const base = (props.field as { icon?: string }).icon;
        const legacy = (props.field as InputTextFieldConfig).icon;
        return base ?? legacy;
    });

    const inputIconPosition = computed<'left' | 'right'>(
        () =>
            (props.field as { iconPosition?: 'left' | 'right' }).iconPosition ??
            (props.field as InputTextFieldConfig).iconPosition ??
            'left',
    );

    const SUPPORTS_INPUT_ICON = new Set(['input-text', 'input-number', 'input-mask', 'password']);

    /** True when the password field should render our custom eye toggle. */
    const showPasswordToggle = computed(() => useCustomPasswordInput.value && (asPassword.value.toggleMask ?? true));

    /** True when the password field should render the generate button. */
    const showPasswordGenerator = computed(() => useCustomPasswordInput.value && !!asPassword.value.generator);

    /**
     * True when the field should render inside an IconField wrapper.
     * Exclusions:
     * - groupPrefix/groupSuffix present → InputGroup wins, IconField deactivated.
     * - password with PrimeVue feedback path → component owns its own icons.
     * - password with eye-toggle/generator addons → those force an InputGroup
     *   wrapper, and PrimeVue's InputGroup expects its input as a direct child;
     *   nesting IconField inside it breaks the border/radius grouping. The icon
     *   falls back to a leading InputGroupAddon instead (see template).
     */
    const usesIconField = computed(() => {
        if (!SUPPORTS_INPUT_ICON.has(props.field.type)) return false;
        if (!inputIcon.value) return false;
        if (props.field.groupPrefix || props.field.groupSuffix) return false;
        if (props.field.type === 'password') {
            if (!useCustomPasswordInput.value) return false;
            if (showPasswordToggle.value || showPasswordGenerator.value) return false;
        }
        return true;
    });

    /**
     * Leading icon for a grouped custom-password field, rendered as an
     * InputGroupAddon because IconField cannot live inside InputGroup.
     */
    const showPasswordIconAddon = computed(
        () =>
            props.field.type === 'password' &&
            useCustomPasswordInput.value &&
            !usesIconField.value &&
            !!inputIcon.value &&
            !props.field.groupPrefix,
    );

    /** Local visibility state — only used when we render the custom eye toggle. */
    const passwordVisible = ref(false);

    function handleTranslatableUpdate(value: Record<string, string>): void {
        emit('update', value);
    }

    /** Resolve the generator config: `true` → {}, object → itself. */
    const passwordGeneratorConfig = computed<PasswordGeneratorConfig>(() => {
        const raw = asPassword.value.generator;
        return typeof raw === 'object' && raw !== null ? raw : {};
    });

    /** InputGroup wrapper detection. */
    const hasGroup = computed(
        () =>
            !!(props.field.groupPrefix || props.field.groupSuffix) ||
            showPasswordToggle.value ||
            showPasswordGenerator.value,
    );
    const isIcon = (text: string) => text.startsWith('pi ');

    const stringVal = computed({
        get: () => (props.value as string) ?? '',
        set: (v) => emit('update', v),
    });

    const numberVal = computed({
        get: () => (props.value as number | null) ?? null,
        set: (v) => emit('update', v),
    });

    const boolVal = computed({
        get: () => (props.value as boolean) ?? false,
        set: (v) => emit('update', v),
    });

    const SELECT_TYPES = new Set(['select', 'multiselect', 'radio', 'select-button']);

    /**
     * Normalize the model value to match the option value type.
     * PrimeVue uses strict === comparison, so "1" !== 1 causes selection mismatch.
     */
    const anyVal = computed({
        get: () => {
            const raw = props.value ?? null;
            if (raw === null || !SELECT_TYPES.has(props.field.type) || !props.options.length) {
                return raw;
            }

            const valueKey = (props.field as SelectFieldConfig).optionValue ?? 'value';
            const sampleOption = props.options[0] as unknown as Record<string, unknown>;
            const sampleType = typeof sampleOption[valueKey];

            const cast = (v: unknown): unknown => {
                if (v === null || v === undefined) return v;
                if (sampleType === 'string') {
                    if (typeof v === 'boolean') return v ? '1' : '0';
                    return String(v);
                }
                if (sampleType === 'number') return Number(v);
                return v;
            };

            return Array.isArray(raw) ? raw.map(cast) : cast(raw);
        },
        set: (v) => emit('update', v),
    });

    const dateVal = computed({
        get: () => (props.value as Date | Date[] | null) ?? null,
        set: (v) => emit('update', v),
    });

    // ── Password generator ───────────────────────────────────────────────────────

    const toast = useToast();

    function handleGeneratePassword(): void {
        const value = generatePassword(passwordGeneratorConfig.value);
        emit('update', value);
        toast.add({
            severity: 'success',
            summary: trans('sk-common.password_generated'),
            detail: trans('sk-common.password_generated_detail'),
            group: 'bc',
            life: 3000,
        });
    }

    // ── File Upload ───────────────────────────────────────────────────────────────

    const { confirmDelete } = useConfirm();
    const api = useApi();
    const existingFiles = ref<ExistingMedia[]>([]);

    watchEffect(() => {
        if (props.field.type === 'file-upload') {
            existingFiles.value = [...(asFileUpload.value.existingMedia ?? [])];
        }
    });

    function formatFileSize(bytes: number): string {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / 1048576).toFixed(1)} MB`;
    }

    function isImageMime(mime: string): boolean {
        return mime.startsWith('image/');
    }

    function fileIcon(mime: string): string {
        if (mime === 'application/pdf') return 'pi pi-file-pdf';
        if (mime.includes('spreadsheet') || mime.includes('excel') || mime.includes('.sheet'))
            return 'pi pi-file-excel';
        if (mime.includes('wordprocessing') || mime.includes('msword') || mime.includes('.document'))
            return 'pi pi-file-word';
        return 'pi pi-file';
    }

    const dialog = useDialog();
    const lightbox = useImageLightbox();

    function openFilePreview(file: FilePreviewFile): void {
        if (file.mimeType?.startsWith('image/')) {
            lightbox.open(file.url, file.name);
            return;
        }
        const width = suggestedPreviewWidth(file.mimeType);
        dialog.open(FilePreviewModal, { file }, file.name, width ? { width } : {});
    }

    function handleFileSelect(event: Event): void {
        const input = event.target as HTMLInputElement;
        if (!input.files?.length) return;

        const config = asFileUpload.value;
        const files = Array.from(input.files);

        if (config.multiple) {
            const currentFiles = (props.value as File[]) ?? [];
            const keepIds = existingFiles.value.map((m) => m.id);
            emit('update', [...keepIds, ...currentFiles, ...files]);
        } else {
            emit('update', files[0]);
        }

        input.value = '';
    }

    function removeNewFile(index: number): void {
        confirmDelete(() => {
            const config = asFileUpload.value;
            if (config.multiple) {
                const currentValue = (props.value as (File | number)[]) ?? [];
                const newFiles = currentValue.filter((item): item is File => item instanceof File);
                const keepIds = currentValue.filter((item): item is number => typeof item === 'number');
                newFiles.splice(index, 1);
                emit('update', [...keepIds, ...newFiles]);
            } else {
                emit('update', null);
            }
        }, 'Are you sure you want to remove this file?');
    }

    function removeExistingFile(media: ExistingMedia): void {
        confirmDelete(async () => {
            try {
                await api.delete(`/media/${media.id}`);
                existingFiles.value = existingFiles.value.filter((m) => m.id !== media.id);

                if (asFileUpload.value.multiple) {
                    const currentValue = (props.value as (File | number)[]) ?? [];
                    emit(
                        'update',
                        currentValue.filter((item) => item !== media.id),
                    );
                }
            } catch {
                // silently fail
            }
        }, 'Are you sure you want to delete this file? This action cannot be undone.');
    }

    const newFiles = computed<File[]>(() => {
        if (props.field.type !== 'file-upload') return [];
        const config = asFileUpload.value;

        if (config.multiple) {
            const val = (props.value as (File | number)[]) ?? [];
            return val.filter((item): item is File => item instanceof File);
        }

        return props.value instanceof File ? [props.value] : [];
    });

    const newFilePreviews = computed(() =>
        newFiles.value.map((file) => ({
            file,
            url: URL.createObjectURL(file),
            isImage: file.type.startsWith('image/'),
        })),
    );
</script>

<template>
    <component :is="hasGroup ? InputGroup : 'div'" :class="{ contents: !hasGroup, 'w-full': hasGroup }">
        <InputGroupAddon v-if="field.groupPrefix">
            <i v-if="isIcon(field.groupPrefix)" :class="field.groupPrefix" />
            <template v-else>
                {{ field.groupPrefix }}
            </template>
        </InputGroupAddon>

        <!-- Leading icon for grouped password fields (IconField can't nest in InputGroup) -->
        <InputGroupAddon v-if="showPasswordIconAddon">
            <SkIcon :icon="inputIcon!" />
        </InputGroupAddon>

        <!-- InputText -->
        <IconField
            v-if="field.type === 'input-text' && usesIconField"
            :icon-position="inputIconPosition"
            class="w-full"
        >
            <InputIcon>
                <SkIcon :icon="inputIcon!" />
            </InputIcon>
            <InputText
                :id="field.key"
                v-model="stringVal"
                :type="asInputText.inputType ?? 'text'"
                :placeholder="asInputText.placeholder ? $t(asInputText.placeholder) : undefined"
                :disabled="disabled"
                :invalid="invalid"
                class="w-full"
                v-bind="extraProps"
            />
        </IconField>
        <InputText
            v-else-if="field.type === 'input-text'"
            :id="field.key"
            v-model="stringVal"
            :type="asInputText.inputType ?? 'text'"
            :placeholder="asInputText.placeholder ? $t(asInputText.placeholder) : undefined"
            :disabled="disabled"
            :invalid="invalid"
            class="w-full"
            v-bind="extraProps"
        />

        <!-- InputNumber -->
        <IconField
            v-else-if="field.type === 'input-number' && usesIconField"
            :icon-position="inputIconPosition"
            class="w-full"
        >
            <InputIcon>
                <SkIcon :icon="inputIcon!" />
            </InputIcon>
            <InputNumber
                :id="field.key"
                v-model="numberVal"
                :placeholder="asInputNumber.placeholder ? $t(asInputNumber.placeholder) : undefined"
                :min="asInputNumber.min"
                :max="asInputNumber.max"
                :step="asInputNumber.step"
                :prefix="asInputNumber.prefix"
                :suffix="asInputNumber.suffix"
                :show-buttons="asInputNumber.showButtons"
                :min-fraction-digits="asInputNumber.minFractionDigits"
                :max-fraction-digits="asInputNumber.maxFractionDigits"
                :use-grouping="asInputNumber.useGrouping ?? true"
                :disabled="disabled"
                :invalid="invalid"
                class="w-full"
                v-bind="extraProps"
                @input="numberVal = ($event.value ?? null) as number | null"
            />
        </IconField>
        <InputNumber
            v-else-if="field.type === 'input-number'"
            :id="field.key"
            v-model="numberVal"
            :placeholder="asInputNumber.placeholder ? $t(asInputNumber.placeholder) : undefined"
            :min="asInputNumber.min"
            :max="asInputNumber.max"
            :step="asInputNumber.step"
            :prefix="asInputNumber.prefix"
            :suffix="asInputNumber.suffix"
            :show-buttons="asInputNumber.showButtons"
            :min-fraction-digits="asInputNumber.minFractionDigits"
            :max-fraction-digits="asInputNumber.maxFractionDigits"
            :use-grouping="asInputNumber.useGrouping ?? true"
            :disabled="disabled"
            :invalid="invalid"
            class="w-full"
            v-bind="extraProps"
            @input="numberVal = ($event.value ?? null) as number | null"
        />

        <!-- InputOtp -->
        <InputOtp
            v-else-if="field.type === 'input-otp'"
            :id="field.key"
            v-model="stringVal"
            :length="asInputOtp.length ?? 6"
            :mask="asInputOtp.mask"
            :integer-only="asInputOtp.integerOnly"
            :disabled="disabled"
            :invalid="invalid"
            v-bind="extraProps"
        />

        <!-- InputMask -->
        <IconField
            v-else-if="field.type === 'input-mask' && usesIconField"
            :icon-position="inputIconPosition"
            class="w-full"
        >
            <InputIcon>
                <SkIcon :icon="inputIcon!" />
            </InputIcon>
            <InputMask
                :id="field.key"
                v-model="stringVal"
                :mask="asInputMask.mask"
                :placeholder="asInputMask.placeholder ? $t(asInputMask.placeholder) : undefined"
                :slot-char="asInputMask.slotChar ?? '_'"
                :auto-clear="asInputMask.autoClear ?? false"
                :unmask="asInputMask.unmask ?? false"
                :disabled="disabled"
                :invalid="invalid"
                class="w-full"
                v-bind="extraProps"
            />
        </IconField>
        <InputMask
            v-else-if="field.type === 'input-mask'"
            :id="field.key"
            v-model="stringVal"
            :mask="asInputMask.mask"
            :placeholder="asInputMask.placeholder ? $t(asInputMask.placeholder) : undefined"
            :slot-char="asInputMask.slotChar ?? '_'"
            :auto-clear="asInputMask.autoClear ?? false"
            :unmask="asInputMask.unmask ?? false"
            :disabled="disabled"
            :invalid="invalid"
            class="w-full"
            v-bind="extraProps"
        />

        <!-- DatePicker -->
        <DatePicker
            v-else-if="field.type === 'date-picker'"
            :id="field.key"
            v-model="dateVal"
            :date-format="asDatePicker.dateFormat ?? 'dd/mm/yy'"
            :selection-mode="asDatePicker.selectionMode ?? 'single'"
            :show-time="asDatePicker.showTime ?? false"
            :hour-format="asDatePicker.hourFormat ?? '24'"
            :show-icon="asDatePicker.showIcon ?? true"
            :icon-display="asDatePicker.iconDisplay ?? 'input'"
            :min-date="asDatePicker.minDate"
            :max-date="asDatePicker.maxDate"
            :show-button-bar="asDatePicker.showButtonBar ?? false"
            :number-of-months="asDatePicker.numberOfMonths ?? 1"
            :view="asDatePicker.view ?? 'date'"
            :inline="asDatePicker.inline ?? false"
            :placeholder="asDatePicker.placeholder ? $t(asDatePicker.placeholder) : undefined"
            :disabled="disabled"
            :invalid="invalid"
            class="w-full"
            v-bind="extraProps"
        />

        <!-- Select -->
        <Select
            v-else-if="field.type === 'select'"
            :id="field.key"
            v-model="anyVal"
            :options="translatedOptions"
            :option-label="asSelect.optionLabel ?? 'label'"
            :option-value="asSelect.optionValue ?? 'value'"
            :placeholder="
                loading
                    ? $t('sk-common.loading')
                    : asSelect.placeholder
                        ? $t(asSelect.placeholder)
                        : $t('sk-common.select')
            "
            :show-clear="asSelect.showClear"
            :filter="asSelect.filter"
            :disabled="disabled || loading"
            :invalid="invalid"
            :loading="loading"
            class="w-full"
            v-bind="extraProps"
        />

        <!-- MultiSelect -->
        <MultiSelect
            v-else-if="field.type === 'multiselect'"
            :id="field.key"
            v-model="anyVal"
            display="chip"
            :options="translatedOptions"
            :option-label="asSelect.optionLabel ?? 'label'"
            :option-value="asSelect.optionValue ?? 'value'"
            :placeholder="
                loading
                    ? $t('sk-common.loading')
                    : asSelect.placeholder
                        ? $t(asSelect.placeholder)
                        : $t('sk-common.select')
            "
            :filter="asSelect.filter"
            :disabled="disabled || loading"
            :invalid="invalid"
            :loading="loading"
            class="w-full"
            v-bind="extraProps"
        />

        <!-- Radio -->
        <div
            v-else-if="field.type === 'radio'"
            class="sk-fb__group"
            :class="asSelect.radioLayout === 'vertical' ? 'sk-fb__group--vertical' : 'sk-fb__group--horizontal'"
        >
            <div v-if="loading" class="sk-fb__group-loading">
                <i class="pi pi-spin pi-spinner text-sm" />
                Loading...
            </div>
            <div v-for="option in translatedOptions" v-else :key="String(option.value)" class="sk-fb__group-item">
                <template v-if="controlPosition === 'right'">
                    <label :for="`${field.key}_${option.value}`" class="sk-fb__group-label">
                        <span class="sk-fb__group-label-text">{{ option.label }}</span>
                        <span v-if="option.description" class="sk-fb__group-label-desc">{{ option.description }}</span>
                    </label>
                </template>
                <RadioButton
                    :input-id="`${field.key}_${option.value}`"
                    :name="field.key"
                    :value="option.value"
                    :model-value="anyVal"
                    :disabled="disabled"
                    v-bind="extraProps"
                    @update:model-value="(v) => emit('update', v)"
                />
                <template v-if="controlPosition !== 'right'">
                    <label :for="`${field.key}_${option.value}`" class="sk-fb__group-label">
                        <span class="sk-fb__group-label-text">{{ option.label }}</span>
                        <span v-if="option.description" class="sk-fb__group-label-desc">{{ option.description }}</span>
                    </label>
                </template>
            </div>
        </div>

        <!-- Checkbox — label is rendered by FormBuilder, not here -->
        <Checkbox
            v-else-if="field.type === 'checkbox'"
            v-model="boolVal"
            :input-id="field.key"
            :binary="true"
            :disabled="disabled"
            :invalid="invalid"
            v-bind="extraProps"
        />

        <!-- CheckboxGroup -->
        <div
            v-else-if="field.type === 'checkbox-group'"
            class="sk-fb__group"
            :class="asSelect.radioLayout === 'vertical' ? 'sk-fb__group--vertical' : 'sk-fb__group--horizontal'"
        >
            <div v-if="loading" class="sk-fb__group-loading">
                <i class="pi pi-spin pi-spinner text-sm" />
                Loading...
            </div>
            <div v-for="option in translatedOptions" v-else :key="String(option.value)" class="sk-fb__group-item">
                <template v-if="controlPosition === 'right'">
                    <label :for="`${field.key}_${option.value}`" class="sk-fb__group-label">
                        <span class="sk-fb__group-label-text">{{ option.label }}</span>
                        <span v-if="option.description" class="sk-fb__group-label-desc">{{ option.description }}</span>
                    </label>
                </template>
                <Checkbox
                    :input-id="`${field.key}_${option.value}`"
                    :value="option.value"
                    :model-value="(value as unknown[]) ?? []"
                    :disabled="disabled"
                    v-bind="extraProps"
                    @update:model-value="(v) => emit('update', v)"
                />
                <template v-if="controlPosition !== 'right'">
                    <label :for="`${field.key}_${option.value}`" class="sk-fb__group-label">
                        <span class="sk-fb__group-label-text">{{ option.label }}</span>
                        <span v-if="option.description" class="sk-fb__group-label-desc">{{ option.description }}</span>
                    </label>
                </template>
            </div>
        </div>

        <!-- Password (default path) — rendered as plain InputText so the
             InputGroup can cleanly host our own eye-toggle + generate buttons
             without fighting PrimeVue Password's absolute-positioned icons.
             IconField is only used when there is no InputGroup wrapper
             (groupPrefix/groupSuffix absent), which usesIconField guarantees. -->
        <IconField
            v-else-if="field.type === 'password' && useCustomPasswordInput && usesIconField"
            :icon-position="inputIconPosition"
            class="w-full"
        >
            <InputIcon>
                <SkIcon :icon="inputIcon!" />
            </InputIcon>
            <InputText
                :id="field.key"
                v-model="stringVal"
                :type="passwordVisible ? 'text' : 'password'"
                :placeholder="asPassword.placeholder ? $t(asPassword.placeholder) : undefined"
                :disabled="disabled"
                :invalid="invalid"
                autocomplete="new-password"
                class="w-full"
                v-bind="extraProps"
            />
        </IconField>
        <InputText
            v-else-if="field.type === 'password' && useCustomPasswordInput"
            :id="field.key"
            v-model="stringVal"
            :type="passwordVisible ? 'text' : 'password'"
            :placeholder="asPassword.placeholder ? $t(asPassword.placeholder) : undefined"
            :disabled="disabled"
            :invalid="invalid"
            autocomplete="new-password"
            class="w-full"
            v-bind="extraProps"
        />

        <!-- Password (PrimeVue with strength feedback meter) -->
        <Password
            v-else-if="field.type === 'password'"
            :id="field.key"
            v-model="stringVal"
            :placeholder="asPassword.placeholder ? $t(asPassword.placeholder) : undefined"
            :feedback="asPassword.feedback ?? false"
            :toggle-mask="asPassword.toggleMask ?? true"
            :disabled="disabled"
            :invalid="invalid"
            input-class="w-full"
            class="w-full"
            v-bind="extraProps"
        />

        <!-- SelectButton -->
        <SelectButton
            v-else-if="field.type === 'select-button'"
            :id="field.key"
            v-model="anyVal"
            :options="translatedOptions"
            :option-label="asSelect.optionLabel ?? 'label'"
            :option-value="asSelect.optionValue ?? 'value'"
            :disabled="disabled || loading"
            :invalid="invalid"
            v-bind="extraProps"
        />

        <!-- Textarea -->
        <Textarea
            v-else-if="field.type === 'textarea'"
            :id="field.key"
            v-model="stringVal"
            :placeholder="asTextarea.placeholder ? $t(asTextarea.placeholder) : undefined"
            :rows="asTextarea.rows ?? 4"
            :auto-resize="asTextarea.autoResize ?? false"
            :disabled="disabled"
            :invalid="invalid"
            class="w-full"
            v-bind="extraProps"
        />

        <!-- Editor (Tiptap rich text) -->
        <EditorInput
            v-else-if="field.type === 'editor'"
            :id="field.key"
            v-model="stringVal"
            :placeholder="asEditor.placeholder ? $t(asEditor.placeholder) : undefined"
            :toolbar="asEditor.toolbar ?? 'standard'"
            :min-height="asEditor.minHeight ?? '10rem'"
            :image-upload="asEditor.imageUpload"
            :links="asEditor.links ?? false"
            :treat-empty-as-blank="asEditor.treatEmptyAsBlank ?? true"
            :disabled="disabled"
            :invalid="invalid"
            class="w-full"
            v-bind="extraProps"
        />

        <!-- ToggleButton -->
        <ToggleButton
            v-else-if="field.type === 'toggle-button'"
            :id="field.key"
            v-model="boolVal"
            :on-label="asToggleButton.onLabel ?? 'Yes'"
            :off-label="asToggleButton.offLabel ?? 'No'"
            :on-icon="asToggleButton.onIcon"
            :off-icon="asToggleButton.offIcon"
            :disabled="disabled"
            :invalid="invalid"
            v-bind="extraProps"
        />

        <!-- ToggleSwitch -->
        <ToggleSwitch
            v-else-if="field.type === 'toggle-switch'"
            :id="field.key"
            v-model="boolVal"
            :disabled="disabled"
            :invalid="invalid"
            v-bind="extraProps"
        />

        <!-- FileUpload -->
        <div v-else-if="field.type === 'file-upload'" class="w-full">
            <!-- Upload zone -->
            <label
                :for="field.key"
                class="sk-fb__upload-zone"
                :class="[invalid ? 'sk-fb__upload-zone--invalid' : '', disabled ? 'sk-fb__upload-zone--disabled' : '']"
            >
                <div class="sk-fb__upload-inner">
                    <span class="sk-fb__upload-icon">
                        <i class="pi pi-cloud-upload" />
                    </span>
                    <span class="sk-fb__upload-text">
                        {{ asFileUpload.multiple ? 'Drop files here or click to upload' : 'Click to select a file' }}
                    </span>
                    <span v-if="asFileUpload.maxFileSize" class="sk-fb__upload-hint">
                        Maks. {{ formatFileSize(asFileUpload.maxFileSize) }}
                    </span>
                </div>
                <input
                    :id="field.key"
                    type="file"
                    class="hidden"
                    :accept="asFileUpload.accept"
                    :multiple="asFileUpload.multiple"
                    :disabled="disabled"
                    @change="handleFileSelect"
                >
            </label>

            <!-- Existing media list -->
            <div v-if="existingFiles.length" class="sk-fb__file-list">
                <div v-for="media in existingFiles" :key="`existing-${media.id}`" class="sk-fb__file-item">
                    <button
                        type="button"
                        class="sk-fb__file-preview-link"
                        @click="
                            openFilePreview({
                                url: media.url,
                                name: media.name,
                                mimeType: media.mime_type,
                                size: media.size,
                            })
                        "
                    >
                        <img
                            v-if="isImageMime(media.mime_type)"
                            :src="media.url"
                            :alt="media.name"
                            class="sk-fb__file-thumb"
                        >
                        <i v-else :class="[fileIcon(media.mime_type), 'sk-fb__file-icon']" />
                    </button>
                    <div class="sk-fb__file-info">
                        <button
                            type="button"
                            class="sk-fb__file-name sk-fb__file-name--link"
                            @click="
                                openFilePreview({
                                    url: media.url,
                                    name: media.name,
                                    mimeType: media.mime_type,
                                    size: media.size,
                                })
                            "
                        >
                            {{ media.name }}
                        </button>
                        <p class="sk-fb__file-size">
                            {{ formatFileSize(media.size) }}
                        </p>
                    </div>
                    <button
                        v-if="!disabled"
                        type="button"
                        class="sk-fb__file-remove"
                        @click="removeExistingFile(media)"
                    >
                        <i class="pi pi-times text-sm" />
                    </button>
                </div>
            </div>

            <!-- New file list -->
            <div v-if="newFiles.length" class="sk-fb__file-list">
                <div
                    v-for="(item, index) in newFilePreviews"
                    :key="`new-${index}`"
                    class="sk-fb__file-item sk-fb__file-item--new"
                >
                    <button
                        type="button"
                        class="sk-fb__file-preview-link"
                        @click="
                            openFilePreview({
                                url: item.url,
                                name: item.file.name,
                                mimeType: item.file.type,
                                size: item.file.size,
                            })
                        "
                    >
                        <img v-if="item.isImage" :src="item.url" :alt="item.file.name" class="sk-fb__file-thumb">
                        <i v-else :class="[fileIcon(item.file.type), 'sk-fb__file-icon']" />
                    </button>
                    <div class="sk-fb__file-info">
                        <button
                            type="button"
                            class="sk-fb__file-name sk-fb__file-name--link"
                            @click="
                                openFilePreview({
                                    url: item.url,
                                    name: item.file.name,
                                    mimeType: item.file.type,
                                    size: item.file.size,
                                })
                            "
                        >
                            {{ item.file.name }}
                        </button>
                        <p class="sk-fb__file-size">
                            {{ formatFileSize(item.file.size) }}
                        </p>
                    </div>
                    <button v-if="!disabled" type="button" class="sk-fb__file-remove" @click="removeNewFile(index)">
                        <i class="pi pi-times text-sm" />
                    </button>
                </div>
            </div>
        </div>

        <!-- ColorSelector -->
        <ColorSelector
            v-else-if="field.type === 'color-selector'"
            v-model="stringVal"
            :colors="asColorSelector.colors"
            :tones="asColorSelector.tones"
            :format="asColorSelector.format"
            :default-tone="asColorSelector.defaultTone"
            :disabled="disabled"
            :invalid="invalid"
            v-bind="extraProps"
        />

        <!-- TranslatableInput (translatable-text / translatable-textarea / translatable-editor) -->
        <TranslatableInput
            v-else-if="
                field.type === 'translatable-text' ||
                    field.type === 'translatable-textarea' ||
                    field.type === 'translatable-editor'
            "
            :field="asTranslatable"
            :model-value="translatableValue"
            :errors="translatableErrors"
            :disabled="disabled"
            :show-label="translatableLabel"
            @update="handleTranslatableUpdate"
        />

        <InputGroupAddon v-if="field.groupSuffix">
            <i v-if="isIcon(field.groupSuffix)" :class="field.groupSuffix" />
            <template v-else>
                {{ field.groupSuffix }}
            </template>
        </InputGroupAddon>

        <InputGroupAddon v-if="showPasswordToggle" class="sk-fb__password-toggle">
            <Button
                v-tooltip.top="$t(passwordVisible ? 'sk-common.hide_password' : 'sk-common.show_password')"
                type="button"
                :icon="passwordVisible ? 'pi pi-eye-slash' : 'pi pi-eye'"
                severity="secondary"
                variant="text"
                :disabled="disabled"
                :aria-label="$t(passwordVisible ? 'sk-common.hide_password' : 'sk-common.show_password')"
                @click="passwordVisible = !passwordVisible"
            />
        </InputGroupAddon>

        <InputGroupAddon v-if="showPasswordGenerator" class="sk-fb__password-generator">
            <Button
                v-tooltip.top="$t('sk-common.generate_password')"
                type="button"
                icon="pi pi-refresh"
                severity="primary"
                variant="text"
                :disabled="disabled"
                :aria-label="$t('sk-common.generate_password')"
                @click="handleGeneratePassword"
            />
        </InputGroupAddon>
    </component>
</template>
