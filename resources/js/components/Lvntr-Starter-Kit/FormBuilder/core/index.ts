// resources/js/formbuilder/index.ts

export type {
    FormBuilderConfig,
    FieldConfig,
    FormLayout,
    SelectOption,
    SelectFieldConfig,
    TitleFieldConfig,
    SectionFieldConfig,
    SlotFieldConfig,
    InputTextFieldConfig,
    InputNumberFieldConfig,
    InputOtpFieldConfig,
    InputMaskFieldConfig,
    DatePickerFieldConfig,
    PasswordFieldConfig,
    PasswordGeneratorConfig,
    TextareaFieldConfig,
    EditorFieldConfig,
    EditorImageUploadConfig,
    EditorToolbarPreset,
    ToggleButtonFieldConfig,
    CheckboxFieldConfig,
    ToggleSwitchFieldConfig,
    FileUploadFieldConfig,
    ColorSelectorFieldConfig,
    ExistingMedia,
    OptionFilter,
    FormResourceConfig,
    TranslatableTextFieldConfig,
    TranslatableTextareaFieldConfig,
    TranslatableEditorFieldConfig,
} from './types';

export {
    FormBuilder,
    InputTextBuilder,
    InputNumberBuilder,
    InputOtpBuilder,
    InputMaskBuilder,
    DatePickerBuilder,
    SelectFieldBuilder,
    CheckboxBuilder,
    PasswordBuilder,
    TextareaBuilder,
    EditorBuilder,
    ToggleButtonBuilder,
    ToggleSwitchBuilder,
    FileUploadBuilder,
    ColorSelectorBuilder,
    TitleBuilder,
    SectionBuilder,
    SlotBuilder,
    TranslatableTextBuilder,
    TranslatableTextareaBuilder,
    TranslatableEditorBuilder,
} from './builder';

import {
    FormBuilder,
    InputTextBuilder,
    InputNumberBuilder,
    InputOtpBuilder,
    InputMaskBuilder,
    DatePickerBuilder,
    SelectFieldBuilder,
    CheckboxBuilder,
    PasswordBuilder,
    TextareaBuilder,
    EditorBuilder,
    ToggleButtonBuilder,
    ToggleSwitchBuilder,
    FileUploadBuilder,
    ColorSelectorBuilder,
    TitleBuilder,
    SectionBuilder,
    SlotBuilder,
    TranslatableTextBuilder,
    TranslatableTextareaBuilder,
    TranslatableEditorBuilder,
} from './builder';

/**
 * FormBuilder — fluent API for configuring the <FormBuilder> component.
 *
 * @example
 * const config = FB.form()
 *   .layout('horizontal')
 *   .cols(2)
 *   .addFields(
 *     FB.inputText().key('name').label('Full Name').required(),
 *     FB.inputText().key('email').label('Email').inputType('email'),
 *     FB.select().key('province').label('Province').optionsUrl('/api/provinces'),
 *     FB.select().key('district').label('District')
 *       .optionsUrl(values => values.province ? `/api/districts?province_id=${values.province}` : null)
 *       .visible(values => !!values.province),
 *     FB.password().key('password').label('Password').toggleMask(),
 *     FB.toggleSwitch().key('is_active').label('Active'),
 *   )
 *   .build();
 *
 * @example — 12-column grid with field spanning
 * const config = FB.form()
 *   .cols(12)
 *   .addFields(
 *     FB.inputText().key('title').label('Title').colSpan(12),   // full row
 *     FB.inputText().key('first_name').label('First Name').colSpan(6),
 *     FB.inputText().key('last_name').label('Last Name').colSpan(6),
 *     FB.textarea().key('bio').label('Bio').colSpan(8),
 *     FB.select().key('role').label('Role').colSpan(4),
 *   )
 *   .build();
 */
export const FB = {
    form: () => new FormBuilder(),
    inputText: () => new InputTextBuilder(),
    inputNumber: () => new InputNumberBuilder(),
    inputOtp: () => new InputOtpBuilder(),
    inputMask: () => new InputMaskBuilder(),
    datePicker: () => new DatePickerBuilder(),
    select: () => new SelectFieldBuilder('select'),
    multiselect: () => new SelectFieldBuilder('multiselect'),
    radio: () => new SelectFieldBuilder('radio'),
    selectButton: () => new SelectFieldBuilder('select-button'),
    checkbox: () => new CheckboxBuilder(),
    checkboxGroup: () => new SelectFieldBuilder('checkbox-group'),
    password: () => new PasswordBuilder(),
    textarea: () => new TextareaBuilder(),
    editor: () => new EditorBuilder(),
    toggleButton: () => new ToggleButtonBuilder(),
    toggleSwitch: () => new ToggleSwitchBuilder(),
    fileUpload: () => new FileUploadBuilder(),
    colorSelector: () => new ColorSelectorBuilder(),
    title: (text?: string) => new TitleBuilder(text),
    section: (title?: string) => new SectionBuilder(title),
    slot: () => new SlotBuilder(),
    translatableText: () => new TranslatableTextBuilder(),
    translatableTextarea: () => new TranslatableTextareaBuilder(),
    translatableEditor: () => new TranslatableEditorBuilder(),
};
