# Composables

Kit composables are shipped inside the package and run directly from the vendor library by default — they no longer need to be copied into the consumer app. Imports throughout the application use `@/composables/<name>` (or the bare `@/composables` barrel) exactly as before; a Vite `customResolver` plus a matching tsconfig path entry resolve those paths **local-first, then vendor**: if a file exists under the consumer's `resources/js/composables/` it wins, otherwise the vendor copy is used automatically.

**`useAdminMenu`** is the only composable that still ships as an editable stub (`resources/js/composables/useAdminMenu.ts`). It depends on the consumer's generated `@/routes/*` files and is the project's own menu definition, so it must remain editable. The `@/composables/index.ts` barrel also stays as a stub.

### Upgrading composables via Composer

Because the 15 kit composables live in the package, they are updated when you run `composer update lvntr/laravel-starter-kit`. No manual file copying is required.

### Publishing composables for customization

To edit a composable, publish it to the consumer app first:

```bash
php artisan sk:publish --tag=composables
```

This copies the current vendor versions into `resources/js/composables/`. Once a local copy exists the local-first resolver picks it up automatically — no alias changes or build config edits are needed.

### Migration note for existing installs

Projects created before this change already have all composables under `resources/js/composables/`. The local-first resolver means those local copies continue to be used, so **nothing breaks**. The trade-off is that those projects will not receive upstream composable fixes via `composer update` until the unmodified files are removed. To opt into vendor-managed upgrades, delete the composables you have not customized from `resources/js/composables/` — keeping `useAdminMenu.ts`, `index.ts`, and any file you have intentionally edited. The kit never auto-deletes local files.

## Commonly Used Composables

- `useApi` for JSON requests using the API response envelope
- `useCan` for permission and role checks from Inertia shared props
- `useDefinition` for cached definition loading
- `useDialog` for dialog state and remote-loading flows
- `useImageLightbox` for fullscreen image preview from FileManager and file-upload fields
- `useConfirm` for confirmation actions
- `useFlash` for flash message handling
- `useDarkMode` for dark mode persistence
- `usePageLoading` for Inertia loading state
- `useRefreshBus` for forcing table or widget refreshes
- `useSidebar` for responsive sidebar state
- `useUrlTab` for tab state synced to the URL
- `useAdminMenu` and `useMenuBuilder` for admin navigation composition

## Core Request and Dialog Helpers

### useApi()

Small `fetch()` wrapper for the project's `to_api()` / `ApiResponse` JSON envelope.

- Adds `Accept: application/json` and `X-Requested-With: XMLHttpRequest`
- Adds `X-XSRF-TOKEN` when available
- Unwraps the `data` payload
- Throws `ApiError` on failed responses
- Can show PrimeVue toast errors unless `toast: false` is passed

```ts
const api = useApi();

const user = await api.get<User>('/api/v1/users/1');
await api.post('/api/v1/users', { name: 'John Doe' });
await api.put('/api/v1/users/1', { name: 'Jane Doe' });
await api.patch('/api/v1/users/1', { status: 'active' });
await api.delete('/api/v1/users/1');
```

### useConfirm()

PrimeVue `ConfirmationService` wrapper with two helpers:

- `confirmDelete(onAccept, message?, icon?)`
- `confirmAction({ message, onAccept, header?, icon?, acceptLabel?, rejectLabel?, acceptClass? })`

```ts
const { confirmDelete, confirmAction } = useConfirm();

confirmDelete(() => {
    console.log('Delete accepted');
});

confirmAction({
    message: 'Publish this record now?',
    acceptLabel: 'Publish',
    onAccept: () => console.log('Confirmed'),
});
```

### useDialog()

Global dialog manager used together with `@lvntr/components/ui/AppDialog.vue`.

- `open(component, props?, header?, options?)`
- `openAsync(component, url, header?, options?, baseProps?)`
- `close()`
- `setLoading(val)`

If `refreshKey` is provided in options, `onSuccess` and `onCancel` callbacks are injected automatically.

### useImageLightbox()

Shared fullscreen image preview state rendered through the global `ImageLightbox` overlay in `AdminLayout.vue`.

- `open(url, name?)`
- `close()`
- `state.visible`, `state.url`, `state.name`

Use this for images. For non-image files, keep using `useDialog()` with `FilePreviewModal`.

## Authorization and Navigation

### useCan()

Reads permission and role data from Inertia shared props.

- `can(permission)`
- `canAny(permissions)`
- `hasRole(role)`

### useAdminMenu()

Defines the project's admin sidebar items and delegates visibility and active-state behavior to `useMenuBuilder()`.

### useMenuBuilder()

Shared menu helper for sidebar-style navigation.

- Filters top-level items and child items by permission and role
- Removes empty section headers after filtering
- Detects active links for both plain URLs and URLs with query parameters
- Keeps parent groups open when one of their children is active

```ts
const allItems: MenuItem[] = [{ title: 'sk-menu.dashboard', href: '/dashboard' }];

return useMenuBuilder(allItems);
```

### useUrlTab()

Keeps a tab selection in sync with a query string key such as `?tab=security`.

### useRefreshBus()

Simple global refresh bus for components such as DataTable. Registered callbacks are automatically cleaned up on component unmount.

- `on(key, callback)` — register a refresh callback
- `refresh(...keys)` — trigger one or more named refresh callbacks
- `refreshAll()` — trigger all registered callbacks

```ts
const bus = useRefreshBus();

bus.on('users-table', () => fetchData());
bus.refresh('users-table');
```

## UI State Helpers

### useSidebar()

Handles desktop collapse state and mobile drawer state for the admin sidebar.

### useDarkMode()

Persists dark mode in local storage and toggles the `.dark` class on `<html>`.

### usePageLoading()

Tracks Inertia navigation state using `inertia:start` and `inertia:finish` browser events.

### useFlash()

Returns reactive flash data from Inertia shared props.

In this project, flash messages are displayed in `AdminLayout.vue`, not inside the composable itself.

## Definition Helpers

### useDefinition()

Loads definition records from the authenticated `/definitions` endpoint and stores them in a shared reactive cache.

- `load(keys)` — loads only the requested keys; deduplicates through shared cache
- `loadAll()` — loads all available definition keys
- `list(key, filter?)` — returns raw definition items, optionally filtered
- `options(key, filter?)` — returns items as `{ label, value }` pairs for selects
- `find(key, value)` — looks up a single item by value
- `clearCache()` — resets the reactive cache
- `loaded` — reactive boolean that becomes `true` once any load completes

Typical keys in this project include `userStatus`, `gender`, `identityType`, and `yesNo`.

```ts
const { load, options, find } = useDefinition();

await load(['userStatus', 'gender']);

const statusOptions = options('userStatus');
const activeStatus = find('userStatus', 'active');
```

`list()` and `options()` accept an optional `filter` object with `only` or `except` arrays when you need a subset of a definition list:

```ts
// Only show active and pending statuses
const filteredOptions = options('userStatus', { only: ['active', 'pending'] });

// Exclude a specific status
const filteredOptions = options('userStatus', { except: ['archived'] });
```

## Recommendation

When a UI behavior appears in more than one page, move it into a composable before repeating it inline.
