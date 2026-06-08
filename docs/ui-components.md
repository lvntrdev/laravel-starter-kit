# UI Components

The starter kit includes a small set of reusable UI helpers built on top of PrimeVue. They cover consistent modal behavior, avatar uploads, file previews, tag rendering, centralized toast rendering, and loading states.

## Included Components

- `AppDialog`
- `AvatarUpload`
- `ImageLightbox`
- `FilePreviewModal`
- `SkCard`
- PrimeVue `Tag` (SK-themed)
- `ConfirmDialogComponent`
- `ToastComponent`
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

All variants are themed for light and dark mode. See the **Components → Tag** showcase page (`/components`) for every variant and color.

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

## Recommendation

Prefer reusing these components before introducing new local variants. That keeps the admin panel visually consistent and makes future kit updates easier.
