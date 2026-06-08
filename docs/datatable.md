# Datatable

The starter kit ships with a reusable datatable stack made of two parts:

- frontend `SkDatatable` component
- backend `DatatableQueryBuilder`

## Imports

```ts
import { DB } from '@lvntr/components/DatatableBuilder/core';
import SkDatatable from '@lvntr/components/DatatableBuilder/SkDatatable.vue';
```

## Frontend Builder

Use the fluent `DB` API to configure the table:

```vue
<script setup lang="ts">
    import { DB } from '@lvntr/components/DatatableBuilder/core';
    import SkDatatable from '@lvntr/components/DatatableBuilder/SkDatatable.vue';
    import users from '@/routes/users';

    interface UserRow {
        id: string;
        full_name: string;
        email: string;
        role: string;
        status: string;
        created_at: string;
    }

    const tableConfig = DB.table<UserRow>()
        .route(users.dtApi.url())
        .addColumns(
            DB.column<UserRow>().label('sk-common.full_name').key('full_name'),
            DB.column<UserRow>().key('email'),
            DB.column<UserRow>().label('sk-common.role').key('role'),
            DB.column<UserRow>().key('status').tag('definition').tagKey('userStatus').tagOutlined(),
        )
        .addFilters(DB.filter().key('status').definitionOptions('userStatus'))
        .addActions(
            DB.action<UserRow>()
                .icon('pi pi-pencil')
                .severity('warn')
                .label('sk-button.edit')
                .handle((row) => console.log(row.id)),
        )
        .build();
</script>

<template>
    <SkDatatable :config="tableConfig" refresh-key="users-table" />
</template>
```

Main capabilities:

- server-side pagination, search, sorting, and filters
- inline or panel filters
- row actions and menu actions
- definition-backed tags
- sticky columns

## Table Builder API

- `route(url)` — accepts a string, a Wayfinder result object, or a callback returning `{ url }`
- `sortable(enabled)`
- `pagination(enabled)`
- `searchable(enabled)`
- `isCard(enabled)`
- `cardTitle(title)`
- `cardSubtitle(subtitle)`
- `perPage(count)`
- `idColumn(config | false)`
- `addColumns(...columns)`
- `addFilters(...filters)`
- `addActions(...actions)`
- `addMenuActions(...menuActions)`
- `menuButton(config)`
- `create(config)`

## Column Builder

- `key(string)`
- `label(string)`
- `sortable(boolean)`
- `render((row, escape) => string)`
- `tag('definition', tagKey?)`
- `tagKey(key)`
- `colors(map)`
- `icons(map)`
- `tagIconPos('left' | 'right')`
- `tagSoft(enabled = true)`
- `tagRounded(enabled = true)`
- `tagOutlined(enabled = true)`
- `sticky()`

Tag rendering is definition-driven. Use `tag('definition')` when the column value maps to a definition key such as `userStatus`. `SkDatatable` resolves the label, severity, and icon from the definitions payload, and you can still override the visual layer with `colors({...})`, `icons({...})`, `tagSoft()`, `tagRounded()`, `tagOutlined()`, and `tagIconPos()`.

```ts
DB.column<UserRow>()
    .key('status')
    .tag('definition', 'userStatus')
    .colors({
        active: 'emerald',
        inactive: 'rose',
    })
    .icons({
        active: 'pi pi-check-circle',
        inactive: 'pi pi-times-circle',
    })
    .tagIconPos('right')
    .tagOutlined()
    .tagRounded();
```

Notes:

- `tagKey()` points to the definition group key, for example `userStatus`
- `colors()` and `icons()` are matched against the current row value
- when you do not override them, `SkDatatable` uses the severity and icon returned by `useDefinition()`

## Filter Builder

- `key(string)`
- `label(string)`
- `type('select' | 'select-button' | 'date' | 'daterange')`
- `options([...])`
- `definitionOptions(key)`
- `optionsUrl(url)`
- `placeholder(string)`
- `inline()`
- `placement('inline' | 'panel')`

Free-text searching is handled by the table-level search box through `searchable(true)`, not by a dedicated text filter type.

## Row Actions

### Inline actions

Use `DB.action()` for buttons rendered directly in the row.

- `icon`
- `severity`
- `size`
- `variant`
- `rounded`
- `raised`
- `text`
- `outlined`
- `label`
- `tooltip`
- `visible(fn)`
- `handle(fn)`

### Menu actions

Use `DB.menuAction()` for actions inside the three-dot dropdown menu.

- `label`
- `icon`
- `separator`
- `visible(fn)`
- `handle(fn)`

## Bulk Actions

Bulk actions let the user select multiple rows — across pages — and run a single backend operation on all of them. Selection can cover an explicit set of IDs or every row matching the current filter state.

### Frontend

Pass a `bulk-actions` prop to `SkDatatable` with an array of action descriptors. Each descriptor needs at minimum a `label`, an `action` key, and an `icon`:

```vue
<template>
    <SkDatatable
        :config="tableConfig"
        :bulk-actions="[
            { label: 'sk-button.delete', action: 'delete', icon: 'pi pi-trash', severity: 'danger' },
        ]"
        bulk-action-url="/admin/users/bulk"
        refresh-key="users-table"
    />
</template>
```

When the user triggers an action, `SkDatatable` posts the following payload:

```json
{
    "action": "delete",
    "ids": ["uuid-1", "uuid-2"],
    "select_all_filtered": false,
    "filter_snapshot": {}
}
```

When `select_all_filtered` is `true`, `ids` is empty and `filter_snapshot` carries the current filter state so the backend can reconstruct the filtered set.

Selection is preserved across page changes. `onSuccess` and `onError` Inertia router callbacks fire after the backend responds.

### Request Validation

`ids.*` is validated as `string|min:1|max:64`, which covers integer auto-increment keys, UUIDs (36 chars), and ULIDs (26 chars) without a type-specific rule.

### Backend

Implement the `BulkAction` interface:

```php
interface BulkAction
{
    public function handle(Collection $models, array $meta): BulkActionResult;
}
```

`BulkActionDispatcher` resolves the right action from the `action` key and passes either the explicit model set (when `ids` is present) or the full filtered set (when `select_all_filtered` is `true`).

`BulkActionResult` carries:

```php
new BulkActionResult(
    processed: 12,
    skipped: 1,
    failed: 0,
    message: 'Deleted 12 users.',
);
```

The controller returns an Inertia flash response — not a JSON response:

```php
return back()->with('success', $result->message);
// or
return back()->with('error', $result->message);
```

### Stub Examples

**BulkDeleteUserAction** — skips users with a higher admin rank than the acting user:

```php
final class BulkDeleteUserAction implements BulkAction
{
    public function __construct(private readonly User $actor) {}

    public function handle(Collection $models, array $meta): BulkActionResult
    {
        $processed = 0;
        $skipped   = 0;

        foreach ($models as $user) {
            if ($user->rank >= $this->actor->rank) {
                $skipped++;
                continue;
            }
            $user->delete();
            $processed++;
        }

        return new BulkActionResult($processed, $skipped, 0);
    }
}
```

**BulkDeleteRoleAction** — protects system roles from deletion:

```php
final class BulkDeleteRoleAction implements BulkAction
{
    public function handle(Collection $models, array $meta): BulkActionResult
    {
        $systemRoles = config('permission-resources.system_roles', []);
        $processed   = 0;
        $skipped     = 0;

        foreach ($models as $role) {
            if (in_array($role->name, $systemRoles, true)) {
                $skipped++;
                continue;
            }
            $role->delete();
            $processed++;
        }

        return new BulkActionResult($processed, $skipped, 0);
    }
}
```

## Custom Cell Slots

`SkDatatable` exposes per-column slots using the `cell-{column.key}` naming pattern. Each slot receives:

- `row`: the full row object
- `value`: the resolved value for the current column key

Use PrimeVue's `<Tag>` (auto-imported, no import needed) when you want slot content to match the built-in badge styling. `severity` accepts the 6 PrimeVue severities and supported SK palette names (e.g. `indigo`, `emerald`); soft/outlined are opt-in via the `p-tag-soft` / `p-tag-outlined` classes:

```vue
<template>
    <SkDatatable :config="tableConfig">
        <template #cell-status="{ row, value }">
            <Tag :value="String(value)" :severity="row.is_active ? 'success' : 'danger'" rounded class="p-tag-soft" />
        </template>
    </SkDatatable>
</template>
```

When a matching `cell-*` slot exists, it overrides the built-in rendering for that column, including definition tags.

## Backend Builder

Use `DatatableQueryBuilder` inside controllers or dedicated query classes:

```php
return DatatableQueryBuilder::for(User::query())
    ->searchable(['name', 'email'])
    ->sortable(['id', 'name', 'email', 'created_at'])
    ->filterable(['status'])
    ->defaultSort('-created_at')
    ->response();
```

### Search semantics

`searchable()` splits the incoming `filter[search]` value by whitespace into
words. Each word is matched against every listed column with `LIKE '%word%'`
(OR across columns), and **all words must match** (AND across words). So
`filter[search]=john doe` against `['name', 'email']` returns rows where each
of `john` and `doe` appears in at least one of name/email. `%` and `_` in the
search value are escaped and treated literally.

The default per-page count (when the caller does not call `perPage()` and no
`?per_page=` query param is present) is read from
`config('starter-kit.datatable.default_per_page')` and falls back to `10`.

`?per_page=` is capped at `config('starter-kit.datatable.max_per_page')` (or
the `STARTER_KIT_DATATABLE_MAX_PER_PAGE` env var) and falls back to `100`
when the key is absent. Higher requested values are silently clamped to the
ceiling — protects the server from accidental or hostile large-payload
requests without breaking legitimate callers.

## Recommended Pattern

For larger modules, keep datatable logic in `app/Domain/*/Queries/*DatatableQuery.php` and inject that query class into the controller.

## Expected Response Shape

`SkDatatable` expects a payload like:

```json
{
    "data": [],
    "total": 0,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1,
    "from": null,
    "to": null
}
```

## Built-in Behavior

`SkDatatable` already includes:

- server-side search, sort, pagination, and filters
- automatic definition loading for tag columns and definition-backed filters
- definition-based tag labels, severities, and icons rendered through PrimeVue's `<Tag>`
- query string sync for shareable table URLs
- `sessionStorage` persistence between reloads
- optional refresh bus integration through `refresh-key`
- automatic per-page controls
- per-column custom render overrides through `cell-{column.key}` slots
- a `load` event that emits fetched rows

## Good Use Cases

- admin user lists
- role lists
- activity logs
- any resource that needs filters, actions, and server-side pagination
