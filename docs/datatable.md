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

## Custom Cell Slots

`SkDatatable` exposes per-column slots using the `cell-{column.key}` naming pattern. Each slot receives:

- `row`: the full row object
- `value`: the resolved value for the current column key

Import `SkTag` when you want slot content to match the built-in badge styling:

```vue
<script setup lang="ts">
    import SkTag from '@lvntr/components/ui/SkTag.vue';
</script>

<template>
    <SkDatatable :config="tableConfig">
        <template #cell-status="{ row, value }">
            <SkTag :value="String(value)" :severity="row.is_active ? 'success' : 'danger'" soft rounded />
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
- definition-based tag labels, severities, and icons rendered through `SkTag`
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
