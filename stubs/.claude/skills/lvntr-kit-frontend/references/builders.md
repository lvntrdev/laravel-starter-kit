> On-demand reference for `lvntr-kit-frontend` — exact builder/composable API. Read when you need a method name or signature.

Imports: `FB`/`DB`/`TB` from `@lvntr/components/{FormBuilder,DatatableBuilder,TabBuilder}/core`; components from `@lvntr/components/<Dir>/<Name>.vue`.
All builders are fluent (`this`) and end with `.build()` (the `addFields/addColumns/addTabs` children are built for you).
Label args are translation keys unless noted. `T` = row type.

---

## 1. FormBuilder — `FB`

Factories: `FB.form()`, `FB.inputText()`, `inputNumber()`, `inputOtp()`, `inputMask()`, `datePicker()`, `select()`, `multiselect()`, `radio()`, `selectButton()`, `checkbox()`, `checkboxGroup()`, `password()`, `textarea()`, `editor()`, `toggleButton()`, `toggleSwitch()`, `fileUpload()`, `colorSelector()`, `title(text?)`, `section(title?)`, `slot()`, `translatableText()`, `translatableTextarea()`, `translatableEditor()`.

### 1.1 `FB.form()` — form-level

Initial config: `layout 'vertical'`, `cols 2`, `isCard true`.

| Method | Meaning |
|---|---|
| `.layout('vertical'\|'horizontal')` | Label placement for the whole form |
| `.cols(n)` | Grid columns (1-12), default 2 |
| `.dividers(b=true)` | Horizontal layout only: hairlines between rows, left-aligned labels |
| `.class(css)` | CSS class on the form root |
| `.submit({ url, method: 'post'\|'put'\|'patch', preserveScroll? })` | Internal mode: SkForm owns the Inertia form, errors, loading |
| `.resource({ store, update, data, key, id? })` | Shorthand: `id` truthy = PUT `update` + GET `data`; falsy = POST `store`. `key` = key unwrapped from the data response |
| `.dataUrl(url)` | GET initial data (skeleton until it arrives) |
| `.dataKey(key)` | Key extracted from the `dataUrl` response (whole response if unset) |
| `.reloadOnDataUrlChange(b=true)` | Refetch when `dataUrl` changes after mount (default: mount-only) |
| `.initialData(obj\|null)` | Seed values by field key (order: initialData > `.default()` > null) |
| `.actionsPosition('top'\|'bottom'\|'both')` | Default `'bottom'` |
| `.actionLabels({ submit?, submitIcon?, cancel?, cancelIcon?, hideCancel?, hideSubmit?, hideActions? })` | Button labels/icons (translation keys) |
| `.hideCancel(b=true)` / `.hideSubmit(b=true)` / `.hideActions(b=true)` | Merge into `actionLabels`; `hideActions` drops the whole bar (host calls exposed `submit()`) |
| `.onCancel('back'\|'emit')` | Default `'emit'` |
| `.inDialog(b=true)` | Dialog mode: shows Cancel, cancel emits |
| `.showBack(b=true)` | "Back" button (only when not in dialog) |
| `.cardTitle(t)` / `.cardSubtitle(t)` | Wrap in a card with this heading |
| `.isCard(b=true)` | `false` = transparent (no card bg/border/shadow) |
| `.permission(key)` | User lacks it: all fields disabled, submit hidden (a floor — `.props({disabled:false})` cannot unlock it) |
| `.confirmLeave(b=true)` | Dirty-navigation warning (internal mode); default on, `false` opts out |
| `.addFields(...fields)` | Append fields/sections |

### 1.2 Shared field methods (every `FB.*` field)

Fields are **required by default**; label defaults to `validation.attributes.{key}`; `translateLabel` defaults `true`.

| Method | Meaning |
|---|---|
| `.key(k)` | Required. Form data key |
| `.label(s\|false)` | Translation key; `false` hides the label |
| `.trans(b=true)` | `false` = label is already translated (`.label($t('x')).trans(false)`) |
| `.required(b=true)` / `.optional(b=true)` | Required flag (`optional()` = `required(false)`) |
| `.labelPlacement('top'\|'inline')` | Vertical layout label position |
| `.controlPosition('left'\|'right')` | Control vs label text |
| `.class(css)` | CSS class on the field wrapper |
| `.hint(s\|undefined)` | Helper text under the field |
| `.visible(fn(values))` | Show conditionally (`values` = current form values) |
| `.disabled(fn(values))` | Disable conditionally |
| `.default(v)` | Initial value |
| `.groupPrefix(s)` / `.groupSuffix(s)` | InputGroup addon (text or icon class) |
| `.labelIcon(icon)` / `.labelIconPosition('left'\|'right')` | Icon next to the label (default left) |
| `.icon(icon)` / `.iconPosition('left'\|'right')` | Icon inside the input (inputText, inputNumber, inputMask, password) |
| `.hidden(b=true)` | Hidden input: in the data, not visible |
| `.colSpan(n)` | Grid cells spanned (1..cols, clamped) |
| `.props({...})` | Passthrough props for the PrimeVue component (merged) |

`icon()` caveats: on `toggleSwitch` it sets the rich-row icon; on `password().feedback()` it has no effect. `title` has its own `icon`/`iconPosition`.

### 1.3 Per-type methods

Only type-specific methods are listed; all shared methods above also apply.

**`inputText()`** — `.placeholder(s|true)` (`true` = use the label), `.inputType('text'|'email'|'url'|'tel'|...)`.

**`inputNumber()`** — `.placeholder`, `.min(n)`, `.max(n)`, `.step(n)`, `.prefix(s)`, `.suffix(s)`, `.showButtons(b=true)`, `.fractionDigits(min, max)`, `.useGrouping(b=true)`.

**`inputOtp()`** — `.length(n)` (default 6), `.mask(b=true)`, `.integerOnly(b=true)`.

**`inputMask()`** — `.mask('(999) 999-9999')`, `.placeholder`, `.slotChar(c)` (default `_`), `.autoClear(b=true)`, `.unmask(b=true)` (strip literals from the value).

**`datePicker()`** — `.placeholder`, `.dateFormat(f)` (default `'dd/mm/yy'`), `.selectionMode('single'|'range'|'multiple')`, `.showTime(b)`, `.hourFormat('12'|'24')` (default `'24'`), `.showIcon(b)` (default true), `.iconDisplay('input'|'button')`, `.minDate(Date)`, `.maxDate(Date)`, `.showButtonBar(b)`, `.numberOfMonths(n)`, `.view('date'|'month'|'year')`, `.inline(b)`.

**`select()` / `multiselect()` / `radio()` / `selectButton()` / `checkboxGroup()`** (same builder, different `type`):
- `.options([{ label, value, description? }])` — static (`description` = secondary line in radio/checkbox-group)
- `.optionsUrl(url | (values) => url|null)` — fetched options; the fn form refetches when values change, return `null` to skip (cascading select)
- `.definitionOptions(key, { only?, except? })` — options from the Definitions system (`.enumOptions()` is a deprecated alias)
- `.optionLabel(prop)`, `.optionValue(prop)` — property names when options come from a URL
- `.placeholder`, `.showClear(b=true)`, `.filter(b=true)` (search box), `.radioLayout('horizontal'|'vertical')` (radio; default horizontal)

**`checkbox()`** / **`toggleButton()`** — `checkbox` has no extra methods. `toggleButton`: `.onLabel(s)`, `.offLabel(s)`, `.onIcon(i)`, `.offIcon(i)`.

**`toggleSwitch()`** — `.icon(i)` (leading icon, opts into the settings-style "rich row"), `.description(s)` (line under the label; also enables rich row).

**`password()`** — `.placeholder`, `.toggleMask(b=true)` (eye toggle, on unless set false), `.feedback(b=true)` (PrimeVue overlay meter, default off), `.strengthMeter(b=true)` (inline 4-segment meter + char count), `.generator(true | { length?, mixedCase?, letters?, numbers?, symbols? })` (button; defaults: length 16 clamped 8-128, all categories on).

**`textarea()`** — `.placeholder`, `.rows(n)`, `.autoResize(b=true)`.

**`editor()`** — `.placeholder`, `.minHeight(css)` (default `'10rem'`), `.toolbar('minimal'|'standard'|'full')` (default `'standard'`), `.imageUpload({ context, contextId?, folderId?, folderName?, acceptedMimes? })` (FileManager context; enables image button + paste/drop), `.links(b=true)` (link + button-link tools, default off), `.treatEmptyAsBlank(b=true)` (empty submits `''` not `<p></p>`, default on).

**`fileUpload()`** — `.multiple(b=true)`, `.accept('image/*')`, `.maxFileSize(bytes)` (per file; no client check if unset), `.fileLimit(n)` (counts kept existing + new), `.existingMedia([{ id, name, url, size, mime_type }])`, `.existingMediaKey(key)` (auto-fill from initial/remote data), `.deferExistingRemoval(b=true)` (removal applied on save via `syncMediaCollection()` instead of an immediate `DELETE /media/{id}`).

**`colorSelector()`** — `.colors(string[])` (default: 22 Tailwind families + `white`,`black`), `.tones(number[])` (default 50..950), `.format('hex'|'name'|'name-tone')` (default `'name'`), `.defaultTone(n)` (default 500).

**`title(text?)`** — heading row (auto key). `.tag('h3')` (default `h3`), `.icon(i)`, `.iconPosition('left'|'right')`.

**`section(title?)`** — grouped fields (single level, no nested sections; not required). `.title(t)`, `.subtitle(s)`, `.cols(n)` (default = form cols), `.isCard(b=true)`, `.aside(b=true)` (title left, grid right, no card), `.asideWidth(css)` (default `'14rem'`), `.addFields(...fields)`.

**`slot()`** — custom content cell. `.slotName(name)` (default = key). Render with `<template #[name]="{ values }">`.

**`translatableText()` / `translatableTextarea()` / `translatableEditor()`** — one input per content language (Settings → Content Languages). Shared: `.onlyLocales([...])`, `.exceptLocales([...])`, `.localeLabelStyle('badge'|'name'|'flag')` (default `'badge'`). Text: `.placeholder(s)`, `.inputType('text'|'email'|'url')`, `.maxLength(n)`. Textarea: `.placeholder(s)`, `.rows(n)`, `.autoResize(b=true)`. Editor: `.minHeight(css)`, `.toolbar('minimal'|'full')` (no `standard`).

### 1.4 `SkForm`

`import SkForm from '@lvntr/components/FormBuilder/SkForm.vue'`

| Item | Detail |
|---|---|
| Props | `config: FormBuilderConfig` (required), `errors?: Record<string,string>` (v-model mode only) |
| `v-model` | `Record<string, unknown>` — **external mode** (when `config.submit`/`.resource()` is NOT set): SkForm reads/writes only the bound object; `initialData`, `.default()`, `dataUrl` do not populate it and `reset()` is a no-op |
| Emits | `success` (after an internal-mode Inertia submit), `cancel` (Cancel clicked and `onCancel` is `'emit'`) |
| Exposed | `reset()`, `submit()`, `reload()` (refetch `dataUrl`), `setValue(key, value)`, `processing`, `isDirty`, `dataLoading`, `remoteData`, `currentValues` |
| Slots | `title-end`; `actions-start`, `actions`, `actions-end` (action bar); `<slotName>` for each `FB.slot()` field (`{ values }`); `field-<key>` overrides one field's control (`{ field, value, onUpdate }`); `section-<key>-title-end` (`{ values }`) |

```ts
const formConfig = computed(() =>
  FB.form().cols(2).inDialog(props.inDialog)
    .resource({ store: p.store.url(), update: p.update.url(), data: p.show.url(), key: 'product', id: props.id })
    .addFields(FB.inputText().key('name'), FB.select().key('status').definitionOptions('productStatus'))
    .build());
```

---

## 2. DatatableBuilder — `DB`

Factories: `DB.table<T>()`, `DB.column<T>()`, `DB.filter()`, `DB.action<T>()`, `DB.menuAction<T>()`.

### 2.1 `DB.table<T>()`

Initial config: `sortable true`, `pagination true`, `searchable true`, `isCard true`, `columnToggle true`, `perPage 10`, ID column visible (`key 'id'`), three-dot menu button `{ icon 'pi pi-ellipsis-v', severity 'secondary', size 'small' }`. `build()` throws without `.route()`.

| Method | Meaning |
|---|---|
| `.route(url \| {url} \| () => {url})` | Required. Datatable endpoint (`products.dtApi.url()` or the Wayfinder route object) |
| `.sortable(b)` / `.pagination(b)` / `.searchable(b)` | Feature toggles (default all true) |
| `.perPage(n)` | Page size (default 10; backend `default_per_page` config applies server side) |
| `.isCard(b=true)` / `.cardTitle(t)` / `.cardSubtitle(t)` | Card wrapper |
| `.title(t)` / `.subtitle(t)` | Toolbar heading. Leave unset on pages whose `AdminLayout` already prints the title |
| `.message(text, severity='info')` or `.message({ text, severity?, icon?, closable? })` | Notice band under the toolbar. Severity: `info`, `success`, `warn`, `danger`, `secondary`, `contrast`. `closable` default false |
| `.columnToggle(b)` | Column visibility/order menu button (default true) |
| `.idColumn({ visible?, key? } \| false)` | Built-in ID column; `false` hides it |
| `.addColumns(...DB.column())` / `.addFilters(...DB.filter())` | Add columns / filters |
| `.addActions(...DB.action())` | Row buttons |
| `.addMenuActions(...DB.menuAction())` | Row three-dot menu items |
| `.menuButton({ icon?, severity?, size?, variant?, rounded?, raised?, text?, outlined? })` | Merge-customize the three-dot button |
| `.create({ label?, icon?, url?, onClick?, ...button style })` | Toolbar create button: `url` renders a link, `onClick` a dialog trigger |

### 2.2 `DB.column<T>()`

Initial: `sortable true`. `key` required.

| Method | Meaning |
|---|---|
| `.key(k)` | Required; dot paths ok (`role.name`) |
| `.label(l)` | Header (translation key) |
| `.sortable(b)` | Default true |
| `.render((row, escape) => html)` | Custom HTML. **Always** wrap interpolations in `escape()` |
| `.tag(type='definition', tagKey?)` | Render as PrimeVue Tag. `'definition'` = label/severity from the Definitions system; `'value'` = raw cell value (optionally via `tagLabels`) |
| `.tagKey(k)` | Definition key (definition mode) or row property used to resolve color (value mode) |
| `.tagLabels({ raw: 'Label' })` | Value-mode label map |
| `.tagSeverityKey(prop)` | Row property holding the severity (overridden by `colors`) |
| `.colors({ value: 'emerald' })` | Tailwind color per value (`TagColor`: red..rose, slate, gray, zinc, neutral, stone) |
| `.icons({ value: 'pi pi-check' })` | Icon per value |
| `.tagIconPos('left'\|'right')` | Default left |
| `.tagSoft(b=true)` / `.tagRounded(b=true)` / `.tagOutlined(b=true)` | Tag styles |
| `.sticky()` | Pin while scrolling horizontally |
| `.hidden()` / `.visible(b)` | Initial column-menu visibility (default visible) |
| `.locked(b=true)` | Always visible, cannot be hidden from the menu |

### 2.3 `DB.filter()`

Initial: `type 'select'`, `placement 'panel'`. `key` required.

| Method | Meaning |
|---|---|
| `.key(k)` | Required; sent as `filter[key]` (`daterange`: `filter[key_from]` + `filter[key_to]`) |
| `.label(l)` / `.placeholder(p)` | Texts |
| `.type('select'\|'select-button'\|'date'\|'daterange')` | Control type |
| `.options([{ label, value, count?, color? }])` | Static options (`count` chip, `color` dot in inline pills) |
| `.definitionOptions(key)` | Options from Definitions |
| `.optionsUrl(url)` | GET → `{ data: FilterOption[] }` |
| `.inline()` / `.placement('inline'\|'panel')` | `inline` = toolbar; `panel` = funnel popover (default). Funnel only shows if >=1 panel filter exists |

### 2.4 `DB.action<T>()` — row buttons

`build()` throws without `.icon()` and `.handle()`.

| Method | Meaning |
|---|---|
| `.icon(i)` | Required (`'pi pi-pencil'`) |
| `.handle((row) => void)` | Required click handler |
| `.label(l)` / `.tooltip(t)` | Text / hover tooltip |
| `.severity('primary'\|'secondary'\|'success'\|'info'\|'warn'\|'danger'\|'contrast')` | Button color |
| `.size('small'\|'large')` / `.variant('outlined'\|'text')` | Size / variant |
| `.rounded(b=true)` / `.raised(b=true)` / `.text(b=true)` / `.outlined(b=true)` | PrimeVue button flags |
| `.visible((row) => bool)` / `.disabled((row) => bool)` | Per-row gating |

### 2.5 `DB.menuAction<T>()` — three-dot menu items

`build()` throws without `.label()` and `.handle()`.
`.label(l)` (required), `.handle((row) => void)` (required), `.icon(i)`, `.separator(b=true)`, `.visible((row) => bool)`, `.disabled((row) => bool)`.

### 2.6 `SkDatatable`

`import SkDatatable from '@lvntr/components/DatatableBuilder/SkDatatable.vue'`

| Item | Detail |
|---|---|
| Props | `config: DataTableConfig<T>` (required), `refreshKey?: string` (registers on the refresh bus), `selection?: UseDatatableSelectionReturn` (from `useDatatableSelection()`; adds checkbox column + bulk bar) |
| Emits | `load(rows: unknown[], total: number)` after every successful fetch |
| Exposed | `refresh()`, `pageData`, `total` |
| Slots | `toolbar`, `toolbar-start`, `toolbar-end`, `message` (replaces `.message()` for runtime content), `bulk-actions` (buttons in the floating bulk bar), `cell-<columnKey>` with `{ row, value }` (replaces that cell) |
| Backend contract | Response must be `DataTableResponse<T>`: `{ data, total, per_page, current_page, last_page, from, to, columns? }` — produce it with `DatatableQueryBuilder`. Optional server `columns` merge over local config by key |

```ts
DB.table<Product>().route(products.dtApi.url()).addColumns(
  DB.column<Product>().key('price').render((r, esc) => `<b>${esc(String(r.price))}</b>`),
  DB.column<Product>().key('status').tag('definition').tagKey('productStatus'),
).addActions(DB.action<Product>().icon('pi pi-pencil').handle((p) => openEdit(p.id))).build();
```

---

## 3. TabBuilder — `TB`

Factories: `TB.tabs()`, `TB.item()`.

### 3.1 `TB.tabs()`

Initial: `layout 'horizontal'`, `queryParam 'tab'`. `build()` needs >=1 tab; duplicate keys throw in dev (console.error in prod); returns an immutable snapshot.

| Method | Meaning |
|---|---|
| `.layout('horizontal'\|'vertical')` / `.vertical()` / `.horizontal()` | Layout |
| `.queryParam(name)` | URL param (default `tab`); empty name throws in dev |
| `.class(css)` | CSS class on the root |
| `.isCard(b=true)` / `.cardTitle(t)` / `.cardSubtitle(t)` | Panel card (per-tab override on the item) |
| `.lazy(b=true)` | Mount only the active panel (`false` clears) |
| `.keepAlive(b=true)` | Mount every panel and keep it alive (`false` clears) |
| `.history('push'\|'replace')` | History entry per switch (default `replace`) |
| `.urlMode('server'\|'client')` | `server` = Inertia visit (default); `client` = rewrite URL, no request |
| `.syncUrl(b=true)` | `false` = no URL sync (use inside dialogs) |
| `.addTabs(...TB.item())` | Add tabs |

Without `.lazy()`/`.keepAlive()`: vertical mounts only the active panel, horizontal mounts all.

### 3.2 `TB.item()`

`key` required (non-blank); `label` defaults to the key.

| Method | Meaning |
|---|---|
| `.key(k)` / `.label(l)` | Slot name = key; label = translation key |
| `.icon(i)` | Icon class |
| `.description(t)` | Secondary line (vertical only) |
| `.iconColor('blue'\|'amber'\|'emerald'\|'purple'\|'teal'\|'red'\|'rose'\|'indigo'\|'slate'\|'pink'\|'orange'\|'cyan'\|'green'\|'yellow')` | Icon tile (vertical only; default `slate`) |
| `.badge(value, severity?)` | Trailing badge; severity `success\|warn\|info\|danger\|secondary` (default `secondary`) |
| `.checked(b=true)` | Green check; wins over `badge` |
| `.permission(...perms)` | Any-of permission; lacking = tab hidden |
| `.role(...roles)` | Any-of role; lacking = tab hidden |
| `.visible(b \| () => b)` / `.disabled(b \| () => b)` | Static or reactive gating. Disabled tabs stay listed but are never URL-selectable |
| `.isCard(b=true)` / `.cardTitle(t)` / `.cardSubtitle(t)` | Per-tab card overrides |

### 3.3 `SkTabs`

`import SkTabs from '@lvntr/components/TabBuilder/SkTabs.vue'`

| Item | Detail |
|---|---|
| Props | `config: TabBuilderConfig` (required), `modelValue?: string` (active tab key) |
| `v-model` | Active key; a URL deep link wins over a differing incoming value on mount |
| Emits | `update:modelValue(key)`, `change({ key, previousKey: string\|null, tab })` on every switch after mount |
| Exposed | `activeTab: string`, `isActive(key)` |
| Slots | `<tab.key>` with `{ tab, isActive }` (one per tab, name must match exactly); `sidebar-header`, `sidebar-footer` (vertical); `empty` (rendered alone when no tab is selectable) |

---

## 4. Composables

Barrel: `@/composables` (`stubs/resources/js/composables/index.ts`). Direct: `@/composables/<name>` (local copy first, vendor fallback). **NOT in the barrel** are marked `no-barrel`; import those by path.

| Composable | Returns | Note |
|---|---|---|
| `useApi({ toast?: boolean })` | `{ get<T>(url), post<T>(url, body?), put, patch, delete }` | Unwraps the `ApiEnvelope.data`; throws `ApiError` (`status`, `body`); `toast:false` silences error toasts. Types `ApiEnvelope`, `ApiError` in barrel |
| `useCan()` | `{ can(perm), canAny(perms[]), hasRole(role) }` | From Inertia `auth.permissions` / `auth.role_names` |
| `useConfirm()` | `{ confirmDelete(onAccept, message?, icon?), confirmAction({ message, onAccept, header?, icon?, onReject?, acceptLabel?, rejectLabel?, acceptClass? }) }` | `ConfirmDialog group="app"` is mounted in `AdminLayout` |
| `useDialog()` | `{ open, openAsync, close, setLoading(b), setFooter(footer\|null), patchFooter(partial), state }` | See 4.1 |
| `useRefreshBus()` | `{ on(key, cb), refresh(...keys), refreshAll() }` | `on` auto-unregisters on unmount; `SkDatatable refreshKey` registers via it |
| `useDefinition()` | `{ load(keys[]), loadAll(), list(key, filter?), options(key, filter?), find(key, value), clearCache(), invalidate(keyOrKeys?), loaded }` | Shared reactive cache; `filter` = `{ only?, except? }`; `options` -> `{label,value}[]`; `find` -> `EnumItem\|undefined` (`{ value, label, severity, icon? }`). Types `EnumItem`, `DefinitionKey`, `DefinitionFilter` |
| `useDatatableSelection({ bulkUrl, idKey='id', onSuccess? })` | `{ selectedIds, selectionMode, submitting, selectedCount, hasSelection, isAllFilteredMode, toggleRow, isRowSelected, togglePageSelection(rows, sel), isPageFullySelected(rows), isPagePartiallySelected(rows), selectAllFiltered(), clearSelection(), executeBulkAction(action, filterSnapshot?, overrideUrl?) }` | POSTs `{ action, ids, select_all_filtered, filter_snapshot }` through Inertia; clears selection + calls `onSuccess` on success. Types `BulkSelectionMode`, `BulkActionResult`, `BulkActionPayload` |
| `useFlash()` | `{ flash: ComputedRef<FlashMessages>, hasFlash: ComputedRef<boolean> }` | Read-only view of Inertia flash (`success/error/warning/info`); `AdminLayout` already displays it |
| `usePageLoading(delay=150)` | `{ isLoading, isNavigating }` (readonly refs) | `isNavigating` is immediate; `isLoading` only after `delay` ms |
| `useUrlTab(tabs, queryParam='tab', { history?: 'push'\|'replace' })` | `{ tabs, activeTab (writable), activeIndex (writable), isActive(key) }` | `tabs` = array / ref / getter of `{ key, label, icon? }`. Only for custom tabs; `SkTabs` has its own state. Type `TabDefinition` |
| `useSidebar()` | `{ isCollapsed, isMobileOpen, isMobile, toggle(), openMobile(), closeMobile() }` | `isCollapsed` persisted in localStorage |
| `useMenuBuilder(items: MenuItem[])` | `{ items, isItemActive(item), isGroupOpen(item), currentUrl }` | Filters by `permission`/`role`, drops empty section headers |
| `useAdminMenu()` | `{ ...useMenuBuilder result }` | **Stub-only** (`stubs/resources/js/composables/useAdminMenu.ts`, editable): the app's sidebar definition; in the barrel |
| `useDarkMode()` | `{ isDark, toggleDark() }` | Toggles `.dark` on `<html>`; user choice (localStorage) beats the admin global default |
| `useTheme()` | `{ theme, runtimeThemes, applyTheme(value) }` | Applies `data-sk-theme` (`main` = no attribute, `aura`); driven by Inertia `appearance.theme` |
| `useAccentColor()` | `{ accent, setAccent(color), applyAccent(color, { followGlobal? }), sidebarStyle, setSidebarStyle(style), applySidebarStyle(style) }` | `accent` `'default'` = follow admin global default. Barrel also exports `ACCENT_COLORS`, `ACCENT_SWATCH`, `SIDEBAR_STYLES`, types `AccentColor`, `SidebarStyle` |
| `useAppearanceDefaults()` | `{ appearance, defaultAccent, defaultDarkMode, defaultSidebarStyle, logoLightUrl, logoDarkUrl, faviconUrl, applyFavicon() }` | Admin-wide appearance from Inertia shared props; call `applyFavicon()` in `onMounted` |
| `useImageLightbox()` | `{ open(url, name='', items=[], index=0), close(), next(), prev(), state }` | **no-barrel.** Global overlay mounted in `AdminLayout`; pass `items`+`index` for gallery |
| `useFileShare()` | `{ createShare(mediaId, ttlHours): ShareLinkResult\|null, revokeShare(mediaId, tokenHash): boolean }` | **no-barrel.** Signed share links (1-720 h); toasts its own errors. `ShareLinkResult = { url, expires_at, token_hash }` |
| `withBasePath(path)` (`useBasePath`) | `string` | **no-barrel.** Prefixes a root-relative path with the app deploy sub-path; for raw `fetch`/XHR only (`useApi` already applies it) |
| `getXsrfToken()` (`useCsrf`) | `string` | **no-barrel.** Decoded `XSRF-TOKEN` cookie or `''`; for raw requests |

### 4.1 `useDialog()` details

- `open(component, props = {}, header = '', options = {})`
- `openAsync(component, url, header = '', options = {}, baseProps = {})` — shows loading, GETs `url`, passes the result as `data` prop (or `options.mapResponse(data)` -> props); closes on fetch failure
- `options`: `width` (default `'640px'`), `subtitle`, `icon`, `refreshKey` (injects `onSuccess` = close + refresh, `onCancel` = close into props), `footer`, `darkMask`; `openAsync` adds `mapResponse`
- `footer: DialogFooter`: `{ icon?, text?, cancelLabel?, confirmLabel?, confirmIcon='pi pi-check', severity='primary', onConfirm?(), hideCancel?, hideConfirm?, disabled?, loading?, startSlot?, startSlotProps?, endSlot?, endSlotProps? }`. When a footer is set, the inner component must not render its own buttons

---

## 5. Components

Barrel `@lvntr/components` also re-exports all of these. Mounted globally in `AdminLayout` (never mount again): `AppDialog`, `ConfirmDialogComponent`, `ToastComponent`, `ImageLightbox`.

| Component (path under `@lvntr/components/`) | What it is / key props |
|---|---|
| `FormBuilder/SkForm.vue` | Builder-driven form — section 1.4 |
| `FormBuilder/SkFormInput.vue` | Renders one field by `field.type`. Props `field`, `value`, `disabled?`, `invalid?`, `options?`, `loading?`; emits `update(value)`. Internal to SkForm |
| `FormBuilder/SkColorSelector.vue` | Tailwind color/tone picker behind `FB.colorSelector()`. Props `modelValue?`, `colors?`, `tones?`, `format?`, `defaultTone?`, `disabled?`, `invalid?`; emits `update:modelValue` |
| `FormBuilder/inputs/EditorInput.vue` | Rich-text editor behind `FB.editor()` (also `EditorImagePicker`, `EditorColorPalette`, `TranslatableInput`) |
| `DatatableBuilder/SkDatatable.vue` | Server-driven table — section 2.6 |
| `TabBuilder/SkTabs.vue` | URL-synced tabs — section 3.3 |
| `ui/AppDialog.vue` | Global dialog shell driven by `useDialog()`; no props |
| `ui/ConfirmDialogComponent.vue` | The `ConfirmDialog group="app"` used by `useConfirm()` |
| `ui/ToastComponent.vue` | Kit toast (group `bc`); extra `toast.add` fields: `icon`, `styleClass` (`'sk-toast-solid'\|'sk-toast-outlined'`), `actions: [{ label, command?, primary?, dismiss? }]` |
| `ui/SkCard.vue` | Themed card. Props `title?`, `subtitle?`, `transparent?`, `divider?` (true), `flush?`, `hostPageHeader?` (true). Slots `header`, `title`, `subtitle`, `title-end`, `actions`, `content`/default, `footer` |
| `ui/SkIcon.vue` | Icon from a class string, SVG markup, URL or data URI. Props `icon`, `ariaLabel?` |
| `ui/AvatarUpload.vue` | Avatar upload/delete card. Props `avatarUrl?`, `uploadUrl`, `deleteUrl`, `title?`, `subtitle?` (`''` hides), `initials?`, `isCard?` (true) |
| `ui/SkImageUpload.vue` | Single-slot image box (logo/favicon). Props `previewUrl?`, `uploadUrl`, `fieldName`, `responseKey`, `accept`, `label`, `hint`, `uploadLabel`, `removeLabel`, `removeConfirm`, `variant?` (`logo-light\|logo-dark\|favicon`), `layout?` (`row\|stacked`), `reloadOnly?` |
| `ui/ImageLightbox.vue` | Fullscreen image overlay driven by `useImageLightbox()` |
| `ui/FilePreviewModal.vue` | Non-image file preview body for `useDialog().open`. Props `file: { url, name, mimeType?, size? }`, `onDownload?`, `showExternalOpen?` (true); exports `suggestedPreviewWidth` |
| `ui/MimePickerField.vue` | Checkbox grid of MIME types. `v-model` `string[]`; prop `categories?` |
| `ui/ToggleFeatureCard.vue` | Settings-style switch card. `v-model` boolean; props `label`, `description?`, `icon?` |
| `ui/SkPageLoader.vue` | Fullscreen Inertia navigation loader. Prop `delay?` (250 ms) |
| `ui/TurnstileWidget.vue` | Cloudflare Turnstile (reads Inertia `turnstile` props). `v-model` token `string` |
| `Skeleton/PageLoading.vue` | Skeleton overlay around content while navigating. Prop `delay?` (150); slot `skeleton` replaces the default |
| `Skeleton/SkeletonBox.vue` | Pulse block. Props `width?` (`'100%'`), `height?` (`'1rem'`), `rounded?` |
| `Skeleton/SkeletonCard.vue` | Stat-card skeleton; default slot replaces the content |
| `Skeleton/SkeletonTable.vue` | Table skeleton. Props `rows?` (5), `columns?` (4) |
| `Skeleton/SkeletonText.vue` | Text-line skeleton. Props `lines?` (3), `lastWidth?` (`'60%'`) |
| `FileManager/FileManager.vue` | Full file manager UI. Props `context` (`'user'\|'global'\|string`), `contextId?`, `readonly?`, `enableTrash?` (falls back to the `fileManagerSettings` shared prop), `acceptedMimes?`, `maxSizeKb?`, `height?` (`'auto'`); emits `share(file)`. Composable `useFileManager` exported from `FileManager/` |
