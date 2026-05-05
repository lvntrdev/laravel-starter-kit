# Global Files Module

The Global Files module is the full-page admin screen used to manage system-wide files. It mounts the `FileManager` component with the `global` context and gives the panel a central workspace for media that is not tied to a specific user-owned record.

## What It Does

- manages system-wide files from a single screen
- exposes folder and file operations under the `global` context
- provides a full-height media workspace inside the admin panel
- makes the FileManager feature set available at page level

## Route

| Method | Path | Route name | Purpose |
| --- | --- | --- | --- |
| `GET` | `/files` | `files.index` | Opens the global file manager screen |

See [routes/web/files-route.php](../routes/web/files-route.php) for the definition.

## Screen Structure

The page is defined in `resources/js/pages/Admin/Files/Index.vue` and uses this mounting pattern:

```vue
<AdminLayout :title="$t('sk-file.title')" :subtitle="$t('sk-file.subtitle')">
    <div class="flex min-h-0 flex-1">
        <FileManager context="global" height="100%" class="flex-1" />
    </div>
</AdminLayout>
```

This keeps:

- the page header inside the admin layout
- the file manager stretched to the remaining vertical space
- scrolling localized to the FileManager area

## Capabilities

The screen exposes the `FileManager` behavior under the `global` context:

- create, rename, and delete folders
- upload files
- drag-and-drop moves
- bulk delete
- breadcrumb navigation
- inline preview
- sorting and selection

For component-level details, see [file-manager.md](./file-manager.md).

## Permissions

Access for the `global` context is resolved by the FileManager authorization layer:

- reads generally require `files.read`
- writes use `files.create`, `files.update`, and `files.delete`

So even if the screen is present in the UI, the authoritative permission checks still happen on the backend.

## When To Use It

- when you need a central place to manage application-wide media
- when files are not tied to a single user-owned record
- when operations or content teams need an in-panel file workspace

## Related Docs

- [file-manager.md](./file-manager.md)
- [roles-permissions.md](./roles-permissions.md)
- [settings.md](./settings.md)
