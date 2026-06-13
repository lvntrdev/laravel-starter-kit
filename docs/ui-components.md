# UI Components

The starter kit includes a small set of reusable UI helpers built on top of PrimeVue. They cover consistent modal behavior, avatar uploads, file previews, tag rendering, centralized toast rendering, and loading states.

## Included Components

- `AppDialog`
- `AvatarUpload`
- `SkImageUpload`
- `ImageLightbox`
- `FilePreviewModal`
- `SkCard`
- PrimeVue `Tag` (SK-themed)
- PrimeVue `Button` (SK-themed, extended severity)
- PrimeVue `Message` / `InlineMessage` (SK-themed, extended severity)
- `ToastComponent` (SK custom toast renderer)
- `ConfirmDialogComponent`
- `MimePickerField`
- `ToggleFeatureCard`
- `SkPageLoader`
- `TurnstileWidget`
- skeleton helpers: `PageLoading`, `SkeletonBox`, `SkeletonCard`, `SkeletonTable`, `SkeletonText`
- `FileManager` component for media-oriented flows

## Global Overlays

`AdminLayout.vue` renders these once for the whole admin area:

- `@lvntr/components/ui/ConfirmDialogComponent.vue`
- `@lvntr/components/ui/ToastComponent.vue`
- `@lvntr/components/ui/AppDialog.vue`
- `@lvntr/components/ui/ImageLightbox.vue`

These components are meant to be triggered from composables and page logic instead of being recreated in every page.

## ImageLightbox and FilePreviewModal

File previews now use two different UI paths:

- `ImageLightbox` for fullscreen image viewing
- `FilePreviewModal` for PDF, video, audio, text, and other non-image previews

Open `ImageLightbox` through `useImageLightbox()`. Open `FilePreviewModal` through `useDialog()`.

## AppDialog and useDialog()

`AppDialog` works with `useDialog()` to render dynamic Vue components in a single shared dialog.

```ts
import { useDialog } from '@/composables/useDialog';
import UserForm from '@/pages/Admin/Users/components/UserForm.vue';

const dialog = useDialog();

dialog.open(UserForm, { inDialog: true }, 'Edit user', {
    refreshKey: 'users-table',
    width: '640px',
});
```

### Async mode

Use `openAsync` when the dialog component needs data fetched from the server before it can render:

```ts
await dialog.openAsync(UserForm, '/roles/1/data', 'Edit user', {
    refreshKey: 'users-table',
    mapResponse: (data) => ({ role: data }),
});
```

## ConfirmDialogComponent and useConfirm()

The confirm dialog is globally mounted and styled once. Pages only call `useConfirm()`.

```ts
import { router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';

const { confirmDelete } = useConfirm();

confirmDelete(() => {
    router.delete('/users/1');
});
```

## ToastComponent

`ToastComponent` is the shared PrimeVue toast container. In this project, it is typically fed by:

- flash messages from `AdminLayout.vue`
- `useApi()` error handling

## SkCard

`SkCard` is the shared wrapper around PrimeVue Card used by `SkForm` (and intended for `SkDatatable` and page-level cards). It guarantees a single, consistent caption header: title text on the left, an optional `#title-end` slot on the right (for action buttons, badges, status indicators), subtitle directly below, and a bottom divider that separates the whole caption block from the content.

```vue
<script setup lang="ts">
    import SkCard from '@lvntr/components/ui/SkCard.vue';
</script>

<template>
    <SkCard title="Users" subtitle="Active accounts">
        <template #title-end>
            <Button icon="pi pi-plus" :label="$t('users.create')" @click="open" />
        </template>

        <SkDatatable :config="config" />
    </SkCard>

    <!-- transparent shell — no background, shadow, padding, or divider visuals -->
    <SkCard transparent>
        <p>Anything goes here.</p>
    </SkCard>

    <!-- hide the caption divider when the section is purely decorative -->
    <SkCard title="Notes" :divider="false">
        <p>Quick note.</p>
    </SkCard>
</template>
```

Props:

- `title?: string` — shorthand for the `#title` slot. Pass a translated string (no internal `$t` call).
- `subtitle?: string` — shorthand for the `#subtitle` slot.
- `transparent?: boolean` (default `false`) — when `true`, the card renders without background, border, shadow, or padding. Useful inside dialogs or as an invisible grouping wrapper.
- `divider?: boolean` (default `true`) — draws a bottom border under the caption block (title + subtitle) so it reads as a distinct header above the content. Themed via `--p-surface-200` (light) / `--p-surface-700` (dark).
- `pt?: Record<string, any>` — extra PrimeVue Card passthrough. Merged with internal pt; consumer keys win on conflicts.

Slots:

- `header`, `title`, `subtitle`, `content`, `footer` — pass through to PrimeVue Card. The default slot also maps to `content`, so `<SkCard>…</SkCard>` is equivalent to `<SkCard><template #content>…</template></SkCard>`.
- `title-end` — rendered to the right of the title in the same flex row.

Notes:

- `SkCard` is `inheritAttrs: false` internally but forwards `class` onto the Card root via `useAttrs`, so `<SkCard class="my-cls">` works as expected (plain class fallthrough is otherwise blocked by PrimeVue Card's own `inheritAttrs: false`).
- The divider only renders when the caption is present and `divider` is `true` (default). A card without a title and subtitle gets no divider line.

## Tag (PrimeVue)

The kit standardizes on PrimeVue's `<Tag>`, repainted to the SK palette. It is auto-imported (no import needed), and its `severity` accepts the 6 PrimeVue severities plus the supported SK palette names — so you get the full palette with no component patch (the theme targets the `data-p` attribute PrimeVue emits). `SkDatatable` tag columns render through this same `<Tag>`.

```vue
<template>
    <Tag value="Active" severity="success" />
    <Tag value="Pending" severity="amber" rounded class="p-tag-soft" />
    <Tag value="Verified" icon="pi pi-check" severity="emerald" class="p-tag-outlined" />
    <Tag value="Indigo" severity="indigo" />
</template>
```

Severity values:

- the 6 built-ins: `success`, `info`, `warn`, `danger`, `secondary`, `contrast`
- Tailwind families: `red`, `orange`, `amber`, `yellow`, `lime`, `green`, `emerald`, `teal`, `cyan`, `sky`, `blue`, `indigo`, `violet`, `purple`, `fuchsia`, `pink`, `rose`, `slate`, `gray`, `zinc`, `neutral`, `stone`
- SK custom families: `mauve`, `olive`, `mist`, `taupe`

Variants — opt-in via `class`, since PrimeVue Tag has no variant prop:

- `p-tag-soft` — lighter, tinted fill
- `p-tag-outlined` — border only
- `p-tag-dot` — leading status dot
- `p-tag-sm` / `p-tag-lg` — sizes
- `rounded` (native prop) — pill shape

All variants are themed for light and dark mode. See the **Components → Tag** showcase page (`/sk-components`) for every variant and color.

## Button (PrimeVue)

PrimeVue `Button` is auto-imported and its `severity` prop accepts the 8 built-in PrimeVue values plus any Tailwind color family or SK custom color — the same extended palette the Tag uses. The theme targets the `data-p-severity` attribute that PrimeVue emits on the button root.

Built-in severities (8): `primary` (no prop, default), `secondary`, `success`, `info`, `warn`, `help`, `danger`, `contrast`.

Extended color values (same Tailwind + SK families as Tag): `red`, `orange`, `amber`, `yellow`, `lime`, `green`, `emerald`, `teal`, `cyan`, `sky`, `blue`, `indigo`, `violet`, `purple`, `fuchsia`, `pink`, `rose`, `slate`, `gray`, `zinc`, `neutral`, `stone`, `mauve`, `olive`, `mist`, `taupe`.

```vue
<template>
    <Button label="Save" />
    <Button label="Delete" severity="danger" outlined />
    <Button label="Approve" severity="emerald" />
    <Button label="Tag" severity="indigo" rounded />
    <Button icon="pi pi-cog" severity="secondary" text />
</template>
```

Variants are the standard PrimeVue button props (`outlined`, `text`, `raised`, `rounded`, `size`, `loading`, `disabled`). No extra class is needed for color variants — severity alone drives the full color.

Destructive actions must use `severity="danger"` (outlined or filled depending on prominence). See the **Components → Button** showcase page (`/sk-components`) for the full per-color matrix.

## Message and InlineMessage (PrimeVue)

PrimeVue `Message` and `InlineMessage` are auto-imported and repainted to the SK palette. Their `severity` accepts the same extended color set as Button and Tag. The theme targets the `data-p` attribute on `Message` and the `p-inlinemessage-<severity>` class on `InlineMessage`.

Built-in severities (6): `success`, `info`, `warn`, `danger`, `secondary`, `contrast`.

Extended color values: all Tailwind families and SK custom families (same list as Button/Tag above).

`Message` supports four layout variants:

- **accent** (default) — tinted left-border banner; use `:closable="false"` for persistent notices
- **fill** — solid-color filled banner, opt in with `class="p-message-fill"`
- **outlined** — border-only, opt in with `variant="outlined"`
- **simple** — minimal, no border or background, opt in with `variant="simple"`

```vue
<template>
    <!-- accent (default) -->
    <Message severity="info" :closable="false">Your changes were saved.</Message>

    <!-- fill variant -->
    <Message severity="success" icon="pi pi-check-circle" class="p-message-fill">
        <div class="font-semibold">Published</div>
        <div class="text-[12.5px] opacity-80">All users can see this post.</div>
    </Message>

    <!-- outlined variant -->
    <Message severity="warn" variant="outlined" :closable="false">Review required.</Message>

    <!-- InlineMessage — inline, no dismiss -->
    <InlineMessage severity="danger">Field is required.</InlineMessage>

    <!-- Extended color -->
    <Message severity="indigo">Custom severity.</Message>
</template>
```

See the **Components → Message** showcase page (`/sk-components`) for every variant and color.

## Toast (SK custom renderer)

`ToastComponent` wraps PrimeVue's `<Toast>` with a fully custom `#container` template. Because the template is custom, PrimeVue's native severity markup does not apply — styling is driven entirely by `.sk-toast-<severity>` CSS classes in `theme/main/components/toast.css`. All `toast.add()` calls go through `useToast()` from PrimeVue.

Built-in severities: `success`, `info`, `warn`, `error`, `secondary`, `contrast`.

Extended color values: all Tailwind families and SK custom families (same list as Button/Tag above). Pass any family name as `severity` and the toast picks up the matching `sk-toast-<name>` color token.

Variants (opt in via `styleClass`):

- Default (accent) — tinted background, colored accent bar, progress bar
- `sk-toast-outlined` — border-only shell
- `sk-toast-solid` — fully filled, inverted text

Extra options beyond the standard `ToastMessageOptions`:

- `icon` — PrimeVue icon class (e.g. `'pi pi-check-circle'`); falls back to a per-severity default if omitted
- `styleClass` — variant class (`'sk-toast-solid'` / `'sk-toast-outlined'`)
- `actions` — pill-button array: `{ label: string; command?: () => void; primary?: boolean; dismiss?: boolean }`

```ts
import { useToast } from 'primevue/usetoast';

const toast = useToast();

// Simple info toast
toast.add({
    severity: 'info',
    summary: 'Saved',
    detail: 'Your changes have been applied.',
    group: 'bc',
    life: 4000,
});

// Custom icon + solid variant
toast.add({
    severity: 'success',
    summary: 'Published',
    icon: 'pi pi-globe',
    styleClass: 'sk-toast-solid',
    group: 'bc',
    life: 3000,
});

// Action buttons (sticky — no life)
toast.add({
    severity: 'warn',
    summary: 'Delete item?',
    detail: 'This cannot be undone.',
    icon: 'pi pi-exclamation-triangle',
    group: 'bc',
    actions: [
        { label: 'Delete', primary: true, command: () => doDelete() },
        { label: 'Cancel' },
    ],
});
```

`group: 'bc'` is required — `ToastComponent` is registered on the `bc` group. See the **Components → Toast** showcase page (`/sk-components`) for live examples.

## AvatarUpload

Avatar upload is a ready-made image picker/uploader component.

```vue
<AvatarUpload :avatar-url="user.avatar_url" upload-url="/user/avatar" delete-url="/user/avatar" />
```

Built-in behavior:

- instant preview with `FileReader`
- upload via `fetch`
- delete confirmation through `useConfirm()`
- Inertia reload after successful upload or delete

## PageLoading

`PageLoading.vue` shows a skeleton overlay during Inertia navigation.

```vue
<PageLoading>
    <template #skeleton>
        <SkeletonTable :rows="6" :columns="4" />
    </template>

    <YourPageContent />
</PageLoading>
```

Other skeleton helpers available under `@lvntr/components/Skeleton/`:

- `SkeletonBox` — generic placeholder box
- `SkeletonCard` — card-shaped loading block
- `SkeletonTable` — table-shaped loading block with configurable rows and columns
- `SkeletonText` — text-line placeholders

## SkImageUpload

`SkImageUpload` is a generic image upload slot for settings-style brand assets (logo, favicon). It mirrors `AvatarUpload`'s optimistic-preview pattern — instant `FileReader` preview, `fetch` upload, and an Inertia partial reload on success — but is designed for rectangular/non-avatar contexts.

```vue
<SkImageUpload
    :preview-url="settings.logo_url"
    upload-url="/admin/settings/logo"
    field-name="logo"
    response-key="logo_url"
    accept="image/png,image/svg+xml"
    label="Light logo"
    hint="Recommended: 300×80 px, PNG or SVG"
    upload-label="Upload"
    remove-label="Remove"
    remove-confirm="Remove the logo?"
    variant="logo-light"
    layout="stacked"
    :reload-only="['settings']"
/>
```

Props:

- `previewUrl?: string | null` — canonical server URL; `null` shows a placeholder icon
- `uploadUrl: string` — endpoint for both POST (upload) and DELETE (remove)
- `fieldName: string` — FormData key for the file (e.g. `logo`, `favicon`)
- `responseKey: string` — key under `json.data` holding the new URL after upload
- `accept: string` — passed to `<input accept>`
- `label: string`, `hint: string` — already-translated display strings
- `uploadLabel: string`, `removeLabel: string`, `removeConfirm: string` — already-translated button/confirm strings
- `variant?: 'logo-light' | 'logo-dark' | 'favicon'` (default `'logo-light'`) — preview box style; `logo-dark` uses a dark backdrop; `favicon` renders a square box
- `layout?: 'stacked' | 'row'` (default `'row'`) — `stacked` stacks preview + buttons vertically (used in logo grids); `row` puts everything inline
- `reloadOnly?: string[]` (default `['settings']`) — Inertia partial-reload prop keys

## MimePickerField

`MimePickerField` is a checkbox-group MIME-type picker used in the File Manager settings. It renders categories (Images, Documents, Archive) of MIME options as labeled checkboxes and emits a `string[]` of selected MIME strings.

```vue
<MimePickerField v-model="settings.allowed_mimes" />
```

Props:

- `modelValue?: string[] | null` — currently selected MIME types
- `categories?: MimeCategory[]` — override the default category list (Images / Documents / Archive). Each entry: `{ titleKey: string; options: { label: string; value: string; icon: string }[] }`

Default MIME groups: JPEG, PNG, GIF, WebP (images); PDF, DOC/DOCX, XLS/XLSX, plain text, CSV (documents); ZIP (archive).

## ToggleFeatureCard

`ToggleFeatureCard` is a styled card-row with an integrated toggle switch, used for feature-flag settings UIs (e.g. enabling/disabling File Manager modules).

```vue
<ToggleFeatureCard
    v-model="settings.share_enabled"
    label="Share links"
    description="Allow users to generate public share links for files."
    icon="pi-share-alt"
/>
```

Props:

- `modelValue?: boolean` (default `false`) — bound toggle state
- `label: string` — feature name, rendered in bold
- `description?: string` — secondary text below the label
- `icon?: string` — PrimeVue icon name (without `pi ` prefix, e.g. `'pi-share-alt'`); renders a tinted icon badge on the left when provided

The card border and background shift to a primary-tinted color when the toggle is on.

## SkPageLoader

`SkPageLoader` is a full-screen animated loading overlay that appears during Inertia page switches. It replaces the default NProgress top bar with an animated radial grid background and a staggered letter-wave word. The animation and theming are defined in `theme/main/components/page-loader.css`.

It is already mounted by `AdminLayout.vue`; you do not need to add it to individual pages.

```vue
<!-- AdminLayout.vue already mounts this: -->
<SkPageLoader :delay="250" />
```

Props:

- `delay?: number` (default `250`) — milliseconds before the overlay appears; prevents flashing on instant navigations

`SkPageLoader` uses `usePageLoading()` internally and reads the localized `sk-layout.loading` string for the animated word.

## TurnstileWidget

`TurnstileWidget` embeds a Cloudflare Turnstile challenge widget. It is used by the kit's auth pages (login, register, password reset) and can be placed in any form that needs bot protection.

For full configuration and backend setup, see the [Authentication documentation](auth.md).

```vue
<TurnstileWidget v-model="form.turnstile_token" />
```

Props:

- `modelValue: string` — the Turnstile token emitted after a successful challenge. Bind it to your form's token field and include it in the submit payload.

Exposes (via `defineExpose`):

- `reset()` — reset the widget (useful after a failed form submit)
- `loadFailed: Ref<boolean>` — `true` if the Cloudflare script failed to load

The widget only renders when `page.props.turnstile.enabled` is `true`. If Turnstile is disabled in the app settings, the component renders nothing. The Cloudflare script is loaded lazily on mount and is shared across widget instances on the same page.

## Recommendation

Prefer reusing these components before introducing new local variants. That keeps the admin panel visually consistent and makes future kit updates easier.
