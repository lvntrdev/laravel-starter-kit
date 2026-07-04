<?php

/*
|--------------------------------------------------------------------------
| Schema Drift — inline test schema vs. real package migrations
|--------------------------------------------------------------------------
|
| tests/DatabaseTestCase::defineDatabaseMigrations() hand-builds the test
| tables inline (Schema builder) instead of running the package migrations,
| deliberately, to sidestep Testbench-core's duplicate `dedupe_receipts`
| fixture migrations that break `migrate:fresh` (see CLAUDE.md).
|
| The cost of that decision is silent drift: a package migration can gain or
| lose a column while the inline copy stays behind, and nothing notices until
| a test happens to touch the missing column. This test closes that gap
| WITHOUT running the package migrations as the test-DB bootstrap — it only
| ADDS detection:
|
|   For each table that the inline schema copies verbatim from the package
|   migrations, it re-derives the "real" column set by running those exact
|   migration files against a throwaway in-memory SQLite connection, then
|   asserts the inline table exposes the same set of column NAMES.
|
| Only column NAMES are compared, not types/indexes: the inline schema
| intentionally diverges on types under SQLite (e.g. uuid vs bigint ids) and
| that divergence is by design. A missing/extra COLUMN, however, is real drift
| and fails loud here.
|
| Intentionally excluded from the auto-comparison:
|   - `users` — the inline table is a hand-built minimal shim, NOT a copy of
|     the (much larger, UUID-keyed) stub users migration. It carries only the
|     columns the file-manager FKs/tests need. Kept separate on purpose; the
|     columns the kit relies on are asserted explicitly below.
|
| If this test goes red: a package migration changed its columns. Update the
| matching block in tests/DatabaseTestCase::defineDatabaseMigrations() to
| match (or, for `users`, the shim + the explicit assertion below).
|
*/

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-derive the real column set for a table by running the given package
 * migration files against a disposable in-memory SQLite connection.
 *
 * FK enforcement is disabled so create-time references to tables that are not
 * present on the scratch connection (users, media, self) do not matter — we
 * only care about the resulting column list.
 *
 * @param  list<string>  $files  absolute migration paths, in apply order
 * @return list<string> column names produced on the scratch connection
 */
function driftScratchColumns(array $files, string $table): array
{
    config(['database.connections.drift_scratch' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]]);
    DB::purge('drift_scratch');

    $previousDefault = config('database.default');
    config(['database.default' => 'drift_scratch']);
    DB::setDefaultConnection('drift_scratch');

    try {
        foreach ($files as $file) {
            // Plain require (not require_once): the migration files return an
            // anonymous-class instance, so re-including them is safe and yields
            // a fresh migration object each run.
            (require $file)->up();
        }

        return Schema::getColumnListing($table);
    } finally {
        config(['database.default' => $previousDefault]);
        DB::setDefaultConnection($previousDefault);
        DB::purge('drift_scratch');
    }
}

/**
 * Table => package migration files whose combined `up()` defines the schema
 * that DatabaseTestCase mirrors inline. Keep this in lockstep with
 * DatabaseTestCase::defineDatabaseMigrations().
 *
 * @return array<string, list<string>>
 */
function driftTableMap(): array
{
    $vendor = dirname(__DIR__, 3).'/database/migrations';

    // Each dataset entry is [tableName, [migrationFiles...]] so the test
    // receives both the table and its defining migrations as arguments.
    return [
        'settings' => ['settings', [
            $vendor.'/2026_03_14_080933_create_settings_table.php',
        ]],
        'global_file_buckets' => ['global_file_buckets', [
            $vendor.'/2026_04_13_100000_create_global_file_buckets_table.php',
        ]],
        'file_folders' => ['file_folders', [
            $vendor.'/2026_04_13_100100_create_file_folders_table.php',
        ]],
        'file_favorites' => ['file_favorites', [
            $vendor.'/2026_05_02_092853_create_file_favorites_table.php',
        ]],
        'file_manager_share_revocations' => ['file_manager_share_revocations', [
            $vendor.'/2026_05_06_100000_create_file_manager_share_revocations_table.php',
        ]],
        'media' => ['media', [
            $vendor.'/2026_03_08_205445_create_media_table.php',
            $vendor.'/2026_04_13_100200_add_folder_id_to_media_table.php',
            $vendor.'/2026_05_02_094121_add_soft_deletes_to_media_table.php',
        ]],
    ];
}

it('keeps the inline test schema column-compatible with the package migrations', function (string $table, array $files): void {
    foreach ($files as $file) {
        expect(is_file($file))->toBeTrue(
            "Package migration missing for `{$table}`: {$file}. "
            .'If a migration was renamed/removed, update tests/DatabaseTestCase and this map together.'
        );
    }

    $expected = driftScratchColumns($files, $table);
    sort($expected);

    $inline = Schema::getColumnListing($table);
    sort($inline);

    $missingInInline = array_values(array_diff($expected, $inline));
    $extraInInline = array_values(array_diff($inline, $expected));

    expect($inline)->toBe(
        $expected,
        "Inline test schema for `{$table}` drifted from the package migrations.\n"
        .'  Columns in migration but missing inline: '.(implode(', ', $missingInInline) ?: '(none)')."\n"
        .'  Columns inline but not in migration: '.(implode(', ', $extraInInline) ?: '(none)')."\n"
        .'Fix tests/DatabaseTestCase::defineDatabaseMigrations() so its inline '
        ."`{$table}` block matches the migration column set (types may differ on purpose)."
    );
})->with(driftTableMap());

it('keeps the users shim carrying the columns the kit depends on', function (): void {
    // `users` is intentionally a minimal shim, not a migration copy, so it is
    // excluded from the auto-comparison above. Still pin the columns the
    // file-manager FKs and password-expiry policy actually read, so silent
    // removal from the shim is caught.
    $required = ['id', 'email', 'password', 'password_changed_at'];

    $inline = Schema::getColumnListing('users');

    foreach ($required as $column) {
        expect(in_array($column, $inline, true))->toBeTrue(
            "The inline `users` shim lost the `{$column}` column that the kit relies on. "
            .'Restore it in tests/DatabaseTestCase::defineDatabaseMigrations().'
        );
    }
});
