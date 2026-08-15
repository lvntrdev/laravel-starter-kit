# Timezones

The starter kit stores timestamps in UTC and converts them only at display and date-filter boundaries.

## UTC Storage Guarantee

Keep Laravel's storage timezone pinned to UTC:

```env
APP_TIMEZONE=UTC
APP_DISPLAY_TIMEZONE=Europe/Istanbul
```

`APP_TIMEZONE` controls `config('app.timezone')` and must remain `UTC`. Using a regional timezone here makes stored rows ambiguous and can apply the same offset twice when a value is formatted later. `APP_DISPLAY_TIMEZONE` is independent: it provides the site's display fallback without changing storage.

Run the storage check at any time:

```bash
php artisan sk:doctor --only=timezone-storage
```

The check fails when `config('app.timezone') !== 'UTC'`. Correct the configuration before writing more rows; rows already stored under a non-UTC configuration may need application-specific review because their intended instants are ambiguous.

## Database Connection Timezone

Keeping the application timezone at UTC is necessary, but it is not sufficient for MySQL or MariaDB. A `TIMESTAMP` column is converted from the connection session timezone to UTC on write, then converted back to the session timezone on read. If the session inherits `SYSTEM` and the database host clock is UTC+03:00, an application-supplied UTC wall-clock value is therefore stored three hours behind. The reverse conversion makes the value look correct to the application, while replicas with another session timezone, `mysqldump` output, and BI/reporting tools see the shifted instant on disk.

`DATETIME` columns do not receive this session conversion and are unaffected.

The contract for the kit's MySQL and MariaDB connections is a literal entry in each existing connection array in `config/database.php`:

```php
'timezone' => '+00:00',
```

`sk:install` adds the entry when it is missing. It is deliberately not backed by an environment variable: there is exactly one correct storage value, and making it configurable would make the corruption configurable. Existing `timezone` values are never overwritten, missing connection arrays are skipped, and the SQLite, PostgreSQL, and SQL Server connections are not changed.

### One-time conversion for existing data

First take the branch that ends the investigation early. If the MySQL host clock has been UTC for the entire lifetime of the data, no bytes were shifted and no conversion is needed. Check the session setting and the clock it currently resolves to in one query:

```sql
SELECT @@session.time_zone AS session_time_zone,
       NOW() AS session_now,
       UTC_TIMESTAMP() AS utc_now,
       TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS utc_offset_seconds;
```

An offset of `0` is enough only when the host itself is configured for UTC. If `@@session.time_zone` is `SYSTEM`, verify the operating-system/database-host timezone too: a regional zone can currently be at UTC while having observed a different daylight-saving offset for older rows.

If the inherited host clock was not UTC, do not run a blanket update. **Take a restorable backup and rehearse the complete procedure on a copy of the database first.** The kit cannot know the historical host offset. Determine it from the host configuration and deployment history, then verify the direction and amount against at least one known-good record whose real creation time can be confirmed independently. If the host observed daylight-saving changes or its timezone changed during the data's lifetime, separate the affected rows into periods and verify each period's offset.

Classify `TIMESTAMP` columns by how they were written, not by their names:

- **Application-written `TIMESTAMP` values** such as `created_at`, `updated_at`, `last_login_at`, `email_verified_at`, and `revoked_at` were stored with the inherited offset but appeared correct through the old session. After the connection is pinned to UTC, old values appear shifted and must be moved by the same signed offset that the old session inherited.
- **Database-written `DEFAULT CURRENT_TIMESTAMP` values** were stored at the correct instant by MySQL and only appeared offset-forward through the old session. The UTC connection setting fixes their display by itself. In this kit, **exclude `file_favorites.created_at` and `failed_jobs.failed_at` from every conversion update**. Updating them would corrupt values that are already correct.

Perform the real cutover in a maintenance window with application writes stopped: back up again, add `'timezone' => '+00:00'`, clear/purge long-lived connections, verify that a fresh session reports `+00:00`, and only then update the pre-existing application-written columns. The following is an adaptation example, not a ready-to-paste command:

```sql
-- EXAMPLE ONLY: the independently verified old offset was +03:00.
-- Adapt the tables, columns, signed interval, and legacy-row predicate.
START TRANSACTION;

UPDATE your_table
SET created_at = DATE_ADD(created_at, INTERVAL 180 MINUTE),
    updated_at = DATE_ADD(updated_at, INTERVAL 180 MINUTE)
WHERE your_verified_legacy_row_predicate;

-- Inspect known records before choosing COMMIT; use ROLLBACK if they do not match.
```

Use a negative interval for a negative inherited offset, and do not assume every table or historical period has the same write path. Prefer stopping writes and converting all pre-existing rows before traffic resumes. If the config change has already gone live, the dataset is mixed: rows written before the change are offset while rows written after it are correct. The conversion must reconcile only the old population, using a cutover marker or another independently verified predicate; a broad update would damage the new rows.

`sk:upgrade` does **not** perform this data conversion and never will. It can only rewrite `config/database.php`, after the safety assessment and consent prompt described in the update guide. The data-specific offset, column provenance, and row boundary cannot be inferred safely by the kit.

## Display Timezone Resolution

Every backend display boundary uses the same chain, exposed by `resolve_display_timezone(?object $user = null): string`:

1. `user.timezone`
2. `config('app.display_timezone')` — the **Settings → General** timezone
3. `config('app.timezone')`
4. `'UTC'`

Invalid IANA timezone identifiers are skipped instead of causing an exception. The profile info tab and the admin user create/edit forms provide a searchable timezone selector.

The `users.timezone` column is nullable and has no default. `null` means **follow the site setting**; it is not equivalent to explicitly storing `'UTC'`. A user with `null` follows a later General-settings timezone change, while a user who selected UTC stays on UTC.

Inertia shares the resolved timezone as the top-level `timezone` prop. `auth.user.timezone` remains the user's raw preference, including `null`.

## Backend Date Helpers

The two helpers serve different contracts:

| Helper | Output | Use it for |
|---|---|---|
| `format_date($value, $type = 'datetime', ?string $timezone = null)` | display text such as `14-03-2026 08:36` | Blade, mail, exports, and other final presentation output |
| `to_api_date($value)` | ISO-8601 with an offset in the resolved timezone, or `null` | API Resources and other machine-readable boundaries |

`format_date()` keeps its existing display format and remains backward compatible. It now follows the shared resolution chain and accepts an explicit timezone override, but its result is not a parseable instant contract. API Resources must use `to_api_date()` so clients can safely reformat the value.

## Frontend Formatting

Import `formatDateTime`, `formatDate`, or `formatTime` from `@lvntr/components/utils/datetime`. They use `Intl.DateTimeFormat` with an explicit timezone resolved in this order:

1. the function's explicit timezone argument
2. the Inertia shared `timezone` prop
3. the browser timezone
4. `'UTC'`

The utilities return `''` for null or unparseable input. For upgrade compatibility, an existing `dd-mm-yyyy HH:mm` display string passes through unchanged; update consumer Resources to `to_api_date()` so new code receives ISO-8601 instead.

## Datatable Date Filters

Use `DatatableQueryBuilder::dateRangeFilters($column)` for date columns. The incoming `Y-m-d` value is a calendar date in the user's resolved display timezone, not a UTC date. The factory converts it to a half-open UTC range against the bare column:

```text
column >= local-day start converted to UTC
column <  next local-day start converted to UTC
```

The exclusive next-day boundary includes the whole final day, including sub-second values. Computing it from the parsed local day also handles 23- and 25-hour DST days correctly. Because the query compares the unwrapped column rather than using `whereDate`, an index on that column remains usable.
