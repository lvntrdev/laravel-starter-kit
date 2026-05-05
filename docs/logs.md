# Log Viewer

A maintainer-only admin section for reading, searching, and deleting Laravel log files in `storage/logs/`. It is shipped as a self-contained domain module and does not require any new dependency.

## Authorization

The whole module is gated on the `system_admin` role only — no entry exists in `config/permission-resources.php`. Non-system-admin users get a 403 on the route and never see the menu item, so the feature is invisible to them.

The route file `routes/web/log-route.php` is listed in the `$routesWithoutPermissionMiddleware` array in `routes/web.php`, which is why the dynamic `check.permission` middleware is skipped for this group.

## Routes

The route file is loaded inside the authenticated web group. Named routes:

- `logs.index` — `GET /logs` (Inertia)
- `logs.dtApi` — `GET /logs/dt` (JSON datatable feed)
- `logs.show` — `GET /logs/{filename}` (Inertia)
- `logs.entries` — `GET /logs/{filename}/entries` (JSON, paginated)
- `logs.destroy` — `DELETE /logs` (JSON, bulk delete)

Filename route parameters use `where('filename', '[A-Za-z0-9._-]+\.log')` so path traversal and non-`.log` requests never reach the controller.

## UI

Two Inertia pages live under `resources/js/pages/Admin/Logs/`:

### `Index.vue`

`SkDatatable` over `logs.dtApi`. Columns:

- `name` — filename (sortable, search by substring)
- `channel_type` — `daily` (matches `laravel-YYYY-MM-DD.log`), `single` (`laravel.log`), or `other`
- `size_bytes` — formatted as KB / MB / GB
- `modified_at` — relative timestamp with absolute tooltip
- `is_active` — chip when the file is the live daily file or written within the last 5 seconds

Row action: **Delete** (disabled when `is_active`). Bulk select supports the **Delete selected** toolbar action; both flow through the `logs.destroy` endpoint and are gated by `useConfirm`.

### `Show.vue`

Filter panel + paginated viewer for a single file. Filters:

- `levels[]` — multi-select from the eight Laravel/PSR-3 levels (`emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`)
- `from`, `to` — ISO date range
- `keyword` — case-insensitive substring search across message + stack trace

Filter changes call `logs.entries` via `useApi`, replace the list, and reset the cursor. **Load more** uses `next_cursor` from the previous response. EOF flag short-circuits the button.

Each entry collapses to a level chip + timestamp + first part of the message. Expanding shows the full message, JSON-pretty-printed `context` (if any), and the stack trace.

## Domain layer

```
app/Domain/Logs/
├── DTOs/
│   ├── LogFileDTO.php          # name, path, size_bytes, modified_at, channel_type, is_active
│   ├── LogEntryDTO.php         # timestamp, level, env, message, context, stack, is_raw
│   ├── LogEntryFilterDTO.php   # levels, from, to, keyword, cursor, per_page
│   └── DeleteLogFilesDTO.php   # filenames[]
├── Queries/
│   ├── LogFileQuery.php        # lists storage/logs/, filters/sorts/paginates in-memory
│   └── LogEntryQuery.php       # streams a single file with cursor pagination
├── Actions/
│   └── DeleteLogFilesAction.php # bulk delete with active-file guardrails
├── Events/
│   └── LogFilesDeleted.php     # carries deleted filenames[] + causer id
├── Listeners/
│   └── LogActivityForLogFilesDeleted.php  # writes one spatie/activitylog entry per batch
└── Services/
    └── LaravelLogParser.php    # stateless line parser; multiline stack-trace aware
```

`LogFilesDeleted → LogActivityForLogFilesDeleted` is wired in `App\Providers\DomainServiceProvider::boot()`.

### Streaming entry reader

`LogEntryQuery::paginate()` opens the file with `fopen('rb')` and walks lines with `fgets()` capped at 64KB per line. The cursor is the byte offset where the next entry header begins, so resuming a page is a single `fseek`. Memory stays bounded regardless of file size.

Unmatched lines are handled by position:

- after a header — appended to the current entry's `stack` field, keeping multi-line exceptions intact
- before any header (or in a file with no Laravel-format headers at all) — buffered and emitted as a single raw `LogEntryDTO` (`is_raw = true`, `level = 'raw'`, sentinel epoch-0 timestamp). The UI hides the timestamp for raw entries and displays them with a gray chip, so the content stays visible instead of being silently dropped. Raw entries are filtered out the moment any structured filter (level / from / to / keyword) is applied.

### Active-file guardrail

A file is considered "active" — and refused for deletion — when either is true:

- it is today's daily file (`laravel-{today}.log`), or
- its `mtime` is within the last 5 seconds (some other channel is currently appending).

Active files are reported back as `failed[]` entries with `reason: 'active_file_protected'` so partial bulk deletes still succeed for the rest.

### Path-traversal guardrail

The safe filename regex `^[A-Za-z0-9._-]+\.log$` is enforced in three places: the route parameter constraint, the `DeleteLogFilesRequest` validation, and the `DeleteLogFilesAction` itself (defence in depth). Anything else returns `log.invalid_filename`.

## Activity logging

When a delete batch succeeds (any `deleted[]` entries), `LogFilesDeleted` is dispatched. The listener writes one `spatie/activitylog` entry with `log_name = 'system'`, no subject, the deleted filenames in `properties.filenames`, and the current user as causer. Entries surface in the existing **Admin → Activity Logs** page automatically.

## i18n

All strings live in `lang/en/sk-log.php` and `lang/tr/sk-log.php`. The menu key (`sk-menu.logs`) is in the existing menu translation files. Failure reason codes returned by the action (`invalid_filename`, `not_found`, `active_file_protected`, `delete_failed`) map one-to-one onto `sk-log.reason_*` keys in the UI.

## Wayfinder

Routes are typed: `import logs from '@/routes/logs'` exposes `logs.index.url()`, `logs.show.url({ filename })`, and so on. Frontend never hardcodes URLs.

## Out of scope (v1)

Not implemented; open follow-up issues if you need them:

- live tail / WebSocket streaming
- cross-file search across all logs
- time-based bulk purge ("delete files older than N days") — the daily channel handles this for you
- `.zip` export / download
- non-Laravel log formats (Apache, JSON channels)
- per-user role gating beyond `system_admin`
