# Admin Component Style Guide

Conventions for building pages under `resources/js/pages/Admin/*`. Follow these so new modules look and behave like the existing User / Role / ActivityLog / Settings / Files pages.

## Page layout

```
resources/js/pages/Admin/<Module>/
├── Index.vue              # Listing (SkDatatable)
├── Create.vue             # Optional — only if create is a dedicated route
├── Edit.vue               # Optional — only if edit is a dedicated route
└── components/
    └── <Module>Form.vue   # SkForm wrapper reused by Create/Edit
```

Most modules fit inside `Index.vue` with dialog-based create/edit (see `Users/Index.vue`). Prefer dedicated `Create.vue` / `Edit.vue` only when the form cannot fit in a dialog (multi-step, media, long forms).

## Form flows — pick one, don't mix

| Case | Use | Endpoint shape | Why |
| --- | --- | --- | --- |
| Admin CRUD from a dialog or an Inertia page | **`SkForm` + FormBuilder** | Inertia controller action returning `back()` / `redirect()` | `SkForm` wraps Inertia's `useForm` internally; validation errors auto-populate from Laravel FormRequest responses; success/cancel events drive dialog lifecycle |
| Programmatic one-shot API call (row action, bulk op, non-form mutation) | **`useApi()`** | Controller returning `to_api()` / `ApiResponse` | Returns unwrapped `data`, throws `ApiError` with envelope, shows toast on failure |
| Reading a small JSON payload (select options, stats widget) | **`useApi().get()`** | `ApiResponse::success(...)` | Same as above; skip if the data is already delivered by Inertia props |

Never call `useApi()` to submit an `SkForm`. Never hand-roll `fetch()` — it bypasses the toast/error pipeline.

## Datatable

- Frontend: `SkDatatable` + `DB.table<T>()` builder (see [datatable.md](./datatable.md))
- Backend: `DatatableQueryBuilder::for(Model::class)` in a `*DatatableQuery` class under `app/Domain/<Module>/Queries/`
- Pass a unique `refresh-key` so dialogs can call `useRefreshBus().refresh(key)` after mutations

## Rich-text content

For any user-authored HTML (welcome messages, notice banners, marketing copy, rich descriptions) use `FB.editor()` paired with `App\Support\HtmlSanitizer` on the backend. Sanitise on write through a FormRequest `prepareForValidation` hook and again on read in the controller before sharing to Inertia; render with `v-html` inside an `sk-prose` container. Never expose raw editor HTML straight from the DB to the browser — see the [FormBuilder Editor field API](./formbuilder.md#editor-field-api).

## Dialogs & confirmations

- `AppDialog` from `@lvntr/components/ui/AppDialog.vue` for modal forms
- `useConfirm()` for destructive actions (delete, disable). Wire `accept` to the mutation.
- `useDialog()` for imperative dialog state if you don't want a template-level `v-model`

## Feedback

- Mutation success toast: emit from the component OR use Laravel's flash session (handled by `useFlash()` which auto-fires on Inertia response)
- Mutation failure toast: already handled by `useApi()` / `SkForm` — do not add a second toast
- Validation errors: `SkForm` surfaces them inline; don't duplicate in toasts

## Permission guards

- In template: `v-can="'users.update'"` / `v-role="'admin'"` directives
- In script: `const { can, hasRole } = useCan()`
- Server-side checks stay authoritative — client checks are UX only

## Definitions (enums / dropdowns)

- Load via `useDefinition()` in the page `onMounted`, not inside a loop
- When a mutation invalidates a definition set (e.g. role list changed), call
  `useDefinition().invalidate('roles')` or wire it to the refresh bus:

  ```ts
  const { invalidate } = useDefinition();
  const bus = useRefreshBus();
  bus.on('roles-updated', () => invalidate('roles'));
  ```

## Routes from the frontend

Always use Wayfinder-generated functions — never hardcode `/admin/...` strings.

```ts
import users from '@/routes/users';
DB.table<UserRow>().route(users.dtApi.url());
```

## Quick checklist for a new admin module

1. Domain scaffold: `php artisan make:sk-domain <Name>`
2. Backend: `*DatatableQuery` + FormRequest + Actions + Events
3. Frontend `Index.vue`: `SkDatatable` with `refresh-key`, actions wired to dialogs
4. `<Module>Form.vue`: `SkForm` with FormBuilder config; emit `success` / `cancel`
5. Permissions: add resource entries to `config/permission-resources.php`, run `sk:seed-permissions --fresh`
6. Translations: `lang/{en,tr}/<module>.php`
7. Tests: Feature test for list + create + update + delete
