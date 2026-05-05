# FormBuilder

`SkForm` renders dynamic forms from a fluent configuration built with `FB`. It handles PrimeVue wiring, definition loading, dependent selects, file uploads, and permission-based read-only mode — so pages stay thin rendering layers.

## Imports

```ts
import { FB } from '@lvntr/components/FormBuilder/core';
import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
```

## Basic Usage

```vue
<script setup lang="ts">
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import users from '@/routes/users';

    const formConfig = FB.form()
        .cols(2)
        .cardTitle('sk-user.create')
        .submit({
            url: users.store.url(),
            method: 'post',
        })
        .addFields(
            FB.inputText().key('first_name'),
            FB.inputText().key('last_name'),
            FB.inputText().key('email').inputType('email'),
            FB.inputMask().key('phone').mask('(999) 999-9999').unmask(),
            FB.select().key('status').definitionOptions('userStatus').default('active'),
            FB.select().key('gender').definitionOptions('gender'),
            FB.password().key('password').toggleMask(),
        )
        .build();
</script>

<template>
    <SkForm :config="formConfig" />
</template>
```

## Two Operating Modes

### Internal submit mode

If `submit(...)` is configured, `SkForm` manages an internal Inertia `useForm()` instance.

### External model mode

If `submit(...)` is omitted, you can use `v-model` and handle submission yourself.

```vue
<SkForm v-model="formData" :config="formConfig" :errors="errors" />
```

## Form Builder API

- `layout('vertical' | 'horizontal')`
- `cols(number)`
- `class(string)`
- `dataUrl(url)`
- `dataKey(key)`
- `initialData(record)`
- `actionsPosition('top' | 'bottom' | 'both')`
- `submit({ url, method, preserveScroll? })`
- `actionLabels(...)`
- `hideCancel(boolean)`
- `hideSubmit(boolean)`
- `onCancel('back' | 'emit')`
- `inDialog(boolean)`
- `showBack(boolean)`
- `cardTitle(string)`
- `cardSubtitle(string)`
- `isCard(boolean)`
- `permission(key)` — permission key that puts the form into read-only mode when the user lacks it (all fields disabled + submit hidden)
- `addFields(...fields)`

## Common Field Methods

Most fields support:

- `key`
- `label`
- `required`
- `optional`
- `class`
- `hint`
- `visible(fn)`
- `disabled(fn)`
- `hidden(boolean)`
- `default(value)`
- `props({...})`

`hidden(true)` keeps the field in the submitted payload while rendering it as a hidden input instead of a visible control.

```ts
FB.inputText().key('user_id').default(currentUserId).hidden();
```

## Available Field Builders

- `FB.inputText()`
- `FB.inputNumber()`
- `FB.inputOtp()`
- `FB.inputMask()`
- `FB.select()`
- `FB.multiselect()`
- `FB.radio()`
- `FB.selectButton()`
- `FB.checkbox()`
- `FB.checkboxGroup()`
- `FB.password()`
- `FB.textarea()`
- `FB.editor()`
- `FB.translatableText()`
- `FB.translatableTextarea()`
- `FB.translatableEditor()`
- `FB.toggleButton()`
- `FB.toggleSwitch()`
- `FB.fileUpload()`
- `FB.colorSelector()`
- `FB.title()`
- `FB.slot()`

## InputMask Field API

`FB.inputMask()` is useful for values such as phone numbers, identity numbers, and formatted dates.

- `mask(string)`
- `placeholder(string | boolean)`
- `slotChar(string)`
- `autoClear(boolean)`
- `unmask(boolean)`

```ts
FB.inputMask().key('phone').mask('(999) 999-9999').placeholder('sk-common.placeholder.phone').slotChar('_').unmask();
```

When `unmask(true)` is enabled, the stored model value is returned without mask characters.

## Password Field API

`FB.password()` renders a password input with an optional strength meter, a crypto-safe generator, and a consistent eye toggle.

- `toggleMask(boolean)` — show a show/hide eye toggle (default `true`).
- `feedback(boolean)` — opt in to the PrimeVue `<Password>` strength meter. When omitted, the field falls back to the lighter `<InputText>` + custom eye toggle path so it renders identically inside `InputGroup` containers. Default `false`.
- `generator(options?)` — opt in to a crypto-safe generate button placed next to the input. Options are optional:

    ```ts
    FB.password().key('password').generator();
    // → 16 chars, mixed case + letters + digits + symbols

    FB.password().key('password').generator({
        length: 20,
        includeSymbols: true,
        includeNumbers: true,
        includeUppercase: true,
        includeLowercase: true,
    });
    ```

    Defaults are intentionally stricter than the framework-wide `Password::defaults()` rule so every generated value passes backend validation on the first submit. The generated password is written directly into the input, shown once via toast (`password_generated` / `password_generated_detail`), and can be copied from the field.

```ts
// Simple password field with eye toggle
FB.password().key('password');

// Password with generator button
FB.password().key('password').generator();

// Strength meter variant (falls back to PrimeVue <Password>)
FB.password().key('password').feedback();

// Generator with a custom length and symbol set
FB.password().key('password').generator({ length: 24 });
```

## Editor Field API

`FB.editor()` renders a Tiptap v3 WYSIWYG editor as a FormBuilder field. Content is stored as sanitized HTML — `App\Support\HtmlSanitizer` strips tags, attributes, and URL schemes outside the allowlist on both write and read paths.

- `preset('minimal' | 'standard' | 'full')` — toolbar layout. `minimal` covers bold / italic / link; `standard` adds headings, lists, alignment and color; `full` enables tables, task lists, image embedding and horizontal rule. Default `'standard'`.
- `placeholder(string)` — translation key rendered when the editor is empty.
- `minHeight(string)` — CSS `min-height` for the editor body (default `'180px'`).
- `imageUpload({ folderName?, maxSizeKb?, acceptedMimes? })` — configure inline image uploads. `folderName` groups every image uploaded through this editor under a single root-level folder in the current FileManager context (e.g. every welcome-message image goes under "Welcome Message"). Accepts the same regex as the server-side `folder_name` validator: letters, digits, space, dash, underscore only.

```ts
FB.editor()
    .key('welcome_message')
    .preset('standard')
    .placeholder('sk-setting.general.welcome_message_placeholder')
    .imageUpload({ folderName: 'Welcome Message' });
```

### Rendering sanitized content

When you render editor output elsewhere in the admin UI, wrap it in an `sk-prose` container so the typography extensions resolve consistently:

```vue
<div class="sk-prose" v-html="welcomeMessage" />
```

Server-side, route every read through `HtmlSanitizer::sanitize()` before sharing to the frontend (defense-in-depth — the write path also sanitizes, but a drifted DB row or an old pre-sanitize entry must not reach the browser).

### URL scheme allowlist

`HtmlSanitizer` allows relative URLs plus `http://`, `https://`, `mailto:`, `tel:`. Everything else (`blob:`, `data:`, `file:`, `ftp:`, `javascript:`, `vbscript:`) is rejected. Keep this in mind when populating editor content programmatically — any smuggled scheme is stripped before save.

## Translatable Field API

Use the translatable builders when a text field should store one value per active language in a JSON column:

- `FB.translatableText()` — one `InputText` per locale.
- `FB.translatableTextarea()` — one `Textarea` per locale.
- `FB.translatableEditor()` — one rich editor per locale.

Common methods:

- `onlyLocales(['tr', 'en'])` — render only these locale codes.
- `exceptLocales(['en'])` — hide these locale codes.
- `translatableLayout('inline' | 'tabs')` — inline stacked fields or tabbed locale panels.
- `localeLabelStyle('badge' | 'name' | 'flag')` — locale label rendering.

```ts
FB.form().addFields(
    FB.translatableText().key('title').label('Title').required(),
    FB.translatableTextarea().key('description').label('Description').rows(4),
    FB.translatableEditor().key('content').label('Content').minHeight('220px'),
);
```

Backend pairing:

- Store each attribute in a JSON column.
- Add Spatie `HasTranslations` to the model and list the attributes in `$translatable`.
- Use `App\Support\HasTranslatableRules` in FormRequests.
- Use `App\Support\TranslatableQueryHelpers` for datatable search/sort and resource output.

See [Translatable Fields](./translatable-fields.md) for the complete backend and frontend guide.

## ColorSelector Field API

`FB.colorSelector()` renders a Tailwind color palette picker with an optional tone selector.

- `colors(string[])` — available color names. Defaults to all Tailwind palettes.
- `tones(number[])` — tone steps displayed. Defaults to `[50, 100, …, 950]`.
- `format('hex' | 'name' | 'name-tone')` — output format. Defaults to `'name'`.
- `defaultTone(number)` — initial tone used when format requires one. Defaults to `500`.

Output format controls what gets stored in the model:

| `format`       | Stored value    |
| -------------- | --------------- |
| `'name'`       | `"blue"`        |
| `'name-tone'`  | `"blue-500"`    |
| `'hex'`        | `"#3b82f6"`     |

The tone selector appears below the dropdown for `'name-tone'` and `'hex'` formats. In `'name'` mode tone is ignored and the selector is hidden.

```ts
// Default — stores color name
FB.colorSelector().key('brand_color');

// Color name + tone — stores "blue-500"
FB.colorSelector().key('brand_color').format('name-tone').defaultTone(500);

// Hex value — stores "#2563eb"
FB.colorSelector().key('brand_color').format('hex').defaultTone(600);

// Restrict palette
FB.colorSelector().key('accent').colors(['red', 'blue', 'green']).tones([400, 500, 600]);
```

When the initial model value is a hex string, the component performs a reverse lookup to restore the matching color + tone selection.

## Data Sources for Select-Like Fields

Select fields can get options from:

- `options([...])` for static arrays
- `definitionOptions('userStatus')` for authenticated `/definitions` records
- `optionsUrl(...)` for remote dynamic options

`enumOptions(...)` is still available as a deprecated alias for backwards compatibility, but new code should prefer `definitionOptions(...)`.

## Reactive Field Dependencies

The `visible(fn)` and `disabled(fn)` methods receive all current form values as an argument. SkForm re-evaluates them on every change, so fields can react to each other.

### Disabling a field based on another field's value

```ts
FB.form().addFields(
    FB.select()
        .key('notification_channel')
        .options([
            { label: 'Email', value: 'email' },
            { label: 'SMS', value: 'sms' },
            { label: 'None', value: 'none' },
        ]),
    FB.inputText()
        .key('notification_address')
        .disabled((values) => values.notification_channel === 'none'),
);
```

When `notification_channel` is set to `none`, the `notification_address` field becomes disabled.

### Showing/hiding a field based on another field's value

```ts
FB.toggleSwitch().key('use_custom_domain'),
FB.inputText()
    .key('custom_domain')
    .visible((values) => values.use_custom_domain === true),
```

The `custom_domain` field only appears when the toggle is enabled.

## Dynamic Options from API (Dependent Selects)

`optionsUrl` accepts either a static string or a **function** that receives current form values and returns a URL (or `null` to skip fetching). SkForm watches the returned URL — when it changes, it automatically fetches new options.

### Loading options from a static URL

```ts
FB.select().key('role').optionsUrl('/api/roles/options');
```

### Dependent select — fetching options based on another field

```ts
FB.form().addFields(
    FB.select()
        .key('country')
        .options([
            { label: 'Turkey', value: 'TR' },
            { label: 'Germany', value: 'DE' },
        ]),
    FB.select()
        .key('city')
        .optionsUrl((values) => (values.country ? `/api/cities?country=${values.country}` : null)),
);
```

How it works:

1. User selects a `country`
2. `optionsUrl` function runs with the new values, returns `/api/cities?country=TR`
3. SkForm detects the URL changed, fetches new options automatically
4. `city` dropdown is populated with the response
5. Returning `null` means "don't fetch" — the select stays empty until a country is chosen

### Combining disabled + dependent optionsUrl

```ts
FB.select()
    .key('department')
    .optionsUrl('/api/departments/options'),
FB.select()
    .key('team')
    .disabled((values) => !values.department)
    .optionsUrl((values) =>
        values.department
            ? `/api/teams/options?department=${values.department}`
            : null
    ),
```

The `team` select is disabled until a department is chosen. Once selected, teams are fetched from the API filtered by department.

## Form-Level Permission Guard

Use `.permission()` to restrict a form to users who hold a specific ability:

```ts
FB.form()
    .resource({ store: ..., update: ..., data: ..., key: 'user', id: userId })
    .permission('users.update')
    .addFields(/* ... */)
    .build();
```

The permission is resolved from the `auth.permissions` Inertia shared prop via the `useCan()` composable. When the current user lacks the permission:

- All fields are automatically `disabled` (in addition to any existing `field.disabled(values => ...)` callbacks)
- The submit button is hidden in both the top and bottom action areas
- `handleSubmit` aborts any submission as a defense-in-depth check
- Cancel/back buttons and custom slot actions still render normally

## Best Practice

Keep field definitions close to the page or tab that owns the form. Use domain Actions and Form Requests on the backend so the form stays a rendering layer, not a business layer.

## Works Well For

- settings tabs
- create and edit resource forms
- profile forms
- admin tools with repeated field patterns

## Built-in Behavior

`SkForm` already handles:

- loading initial data from `dataUrl`
- preloading definition options
- updating dynamic select options when dependent fields change
- rendering hidden fields as native `<input type="hidden">` elements
- file upload submission through `forceFormData`
- dialog-friendly cancel behavior
- unified error rendering for internal or external mode
- turning the form read-only when `permission` is set and the user lacks the ability
