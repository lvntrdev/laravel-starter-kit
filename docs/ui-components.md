# UI Components

The starter kit includes a small set of reusable UI helpers built on top of PrimeVue. They cover consistent modal behavior, avatar uploads, file previews, tag rendering, centralized toast rendering, and loading states.

## Included Components

- `AppDialog`
- `AvatarUpload`
- `ImageLightbox`
- `FilePreviewModal`
- `SkTag`
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

## SkTag

`SkTag` is the shared badge/tag helper used by `SkDatatable` and is a good drop-in option in custom cells when PrimeVue `Tag` is too limited.

```vue
<script setup lang="ts">
    import SkTag from '@lvntr/components/ui/SkTag.vue';
</script>

<template>
    <SkTag value="Active" severity="success" />
    <SkTag value="Pending" color="amber" soft rounded />
    <SkTag value="Verified" icon="pi pi-check" color="emerald" outlined />
    <SkTag value="External" icon="pi pi-arrow-right" icon-pos="right" color="sky" />
</template>
```

Supported props:

- `value`
- `icon`
- `iconPos` — `'left'` (default) or `'right'`
- `severity` — PrimeVue-style severity or Tailwind palette key
- `color` — Tailwind palette key, overrides the severity mapping
- `soft`
- `rounded`
- `outlined`

Behavior notes:

- `severity` accepts PrimeVue-style severities (`success`, `warn`, `danger`, `info`, `secondary`, `contrast`) and Tailwind palette keys such as `emerald` or `violet`
- `color` overrides the severity mapping when you need an exact palette choice
- `soft`, `rounded`, and `outlined` can be combined freely
- the shared theme loads solid, soft, and outlined variants and keeps them aligned in light and dark mode

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
