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
 * Run the given package migration files against a disposable in-memory SQLite
 * connection, then hand control to $inspect while that connection is still the
 * default one, and return whatever $inspect returns.
 *
 * FK enforcement is disabled so create-time references to tables that are not
 * present on the scratch connection (users, media, self) do not matter — we
 * only care about the resulting schema.
 *
 * The scratch connection is always torn down (finally), so a failing
 * expectation inside $inspect cannot leak the default connection into the rest
 * of the suite.
 *
 * @param  list<string>  $files  absolute migration paths, in apply order
 * @param  Closure():mixed  $inspect  runs against the scratch connection
 */
function driftScratchRun(array $files, Closure $inspect): mixed
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

        return $inspect();
    } finally {
        config(['database.default' => $previousDefault]);
        DB::setDefaultConnection($previousDefault);
        DB::purge('drift_scratch');
    }
}

/**
 * Re-derive the real column set for a table by running the given package
 * migration files against a disposable in-memory SQLite connection.
 *
 * @param  list<string>  $files  absolute migration paths, in apply order
 * @return list<string> column names produced on the scratch connection
 */
function driftScratchColumns(array $files, string $table): array
{
    return driftScratchRun($files, fn (): array => Schema::getColumnListing($table));
}

/**
 * Full definition (nullable / default / type) of a single column on the
 * connection that is currently default.
 *
 * @return array<string, mixed>|null
 */
function driftColumnDefinition(string $table, string $column): ?array
{
    foreach (Schema::getColumns($table) as $definition) {
        if ($definition['name'] === $column) {
            return $definition;
        }
    }

    return null;
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
    $required = ['id', 'email', 'password', 'password_changed_at', 'timezone'];

    $inline = Schema::getColumnListing('users');

    foreach ($required as $column) {
        expect(in_array($column, $inline, true))->toBeTrue(
            "The inline `users` shim lost the `{$column}` column that the kit relies on. "
            .'Restore it in tests/DatabaseTestCase::defineDatabaseMigrations().'
        );
    }
});

it('keeps the inline users shim timezone column nullable with no default', function (): void {
    // The shim is what every later timezone test actually writes to, so pin
    // its nullability here as well — a shim that drifted to NOT NULL
    // DEFAULT 'UTC' would make the fallback tests pass for the wrong reason.
    $definition = driftColumnDefinition('users', 'timezone');

    expect($definition)->not->toBeNull(
        'The inline `users` shim lost the `timezone` column. '
        .'Restore it in tests/DatabaseTestCase::defineDatabaseMigrations().'
    );
    expect($definition['nullable'])->toBeTrue('Inline `users.timezone` must stay nullable.');
    expect($definition['default'])->toBeNull('Inline `users.timezone` must have no default (null means "follow the site setting").');
});

it('adds users.timezone as a nullable, defaultless column', function (): void {
    // Asserted against the REAL migration files (not the inline shim), because
    // the nullability is a product decision, not an implementation detail:
    // `null` means "follow the site setting" and must stay distinguishable
    // from a user who deliberately picked 'UTC'. A NOT NULL column defaulting
    // to 'UTC' would silently opt every existing user out of the site setting.
    $migrations = dirname(__DIR__, 3).'/stubs/database/migrations';

    $create = $migrations.'/0001_01_01_000000_create_users_table.php';
    $addTimezone = $migrations.'/2026_08_15_100000_add_timezone_to_users_table.php';

    foreach ([$create, $addTimezone] as $file) {
        expect(is_file($file))->toBeTrue("Expected migration is missing: {$file}");
    }

    $definition = driftScratchRun([$create, $addTimezone], fn (): ?array => driftColumnDefinition('users', 'timezone'));

    expect($definition)->not->toBeNull('The `users` table has no `timezone` column after the migration ran.');
    expect($definition['nullable'])->toBeTrue(
        '`users.timezone` must be nullable — null is the "follow the site setting" state.'
    );
    expect($definition['default'])->toBeNull(
        "`users.timezone` must not carry a default — a 'UTC' default is indistinguishable from a deliberate user choice."
    );
});

it('keeps the users.timezone migration re-runnable and reversible', function (): void {
    // The `hasColumn` guards are what make a re-run on a partially upgraded
    // app a no-op. Without them SQLite/MySQL raise "duplicate column".
    $migrations = dirname(__DIR__, 3).'/stubs/database/migrations';

    $create = $migrations.'/0001_01_01_000000_create_users_table.php';
    $addTimezone = $migrations.'/2026_08_15_100000_add_timezone_to_users_table.php';

    $states = driftScratchRun([$create], function () use ($addTimezone): array {
        // Fresh instances each time, exactly as the migrator would build them.
        (require $addTimezone)->up();
        (require $addTimezone)->up();   // idempotent: must not throw
        $afterUp = Schema::hasColumn('users', 'timezone');

        (require $addTimezone)->down();
        (require $addTimezone)->down(); // idempotent: must not throw
        $afterDown = Schema::hasColumn('users', 'timezone');

        return [$afterUp, $afterDown];
    });

    expect($states[0])->toBeTrue('`users.timezone` should exist after up().');
    expect($states[1])->toBeFalse('`users.timezone` should be gone after down().');
});
