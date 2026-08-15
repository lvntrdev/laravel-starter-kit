<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lvntr\StarterKit\Support\SensitiveLogAttributes;
use RuntimeException;
use stdClass;
use Throwable;

/**
 * Strips credentials out of activity-log rows that were written BEFORE the
 * deny list in `Lvntr\StarterKit\Traits\HasActivityLogging` existed.
 *
 * ## Why this command exists at all
 *
 * The shipped `User` stub keeps `password` in `$fillable` (mass assignment
 * needs it) and the trait used to log the whole fillable set, so every password
 * change wrote the OLD and the NEW bcrypt hash into a row the admin
 * activity-log screen renders. The trait now excludes those attributes, which
 * only stops NEW rows — the historical rows still carry the hashes. This
 * command cleans them WITHOUT deleting the rows: the audit trail (who changed
 * what, when) is the whole point of the table and is preserved intact; only the
 * credential-bearing keys are removed.
 *
 * ## Both JSON columns, not just `properties`
 *
 * spatie/laravel-activitylog 5.x writes model diffs to a SEPARATE
 * `attribute_changes` column (`ActivityLogger::withChanges()`), while
 * `properties` holds whatever `withProperties()` callers passed — and rows
 * written by older 4.x installs carry the diff inside `properties` instead. A
 * pass over `properties` alone would therefore clean nothing on a current
 * install. BOTH columns are rewritten, and each is skipped when the install
 * does not have it.
 *
 * ## Removal is recursive
 *
 * `attribute_changes` has the fixed `{"attributes": {...}, "old": {...}}`
 * shape, but `properties` is a free-form bag: kit audit listeners and consumer
 * code push arbitrary nested structures through `withProperties()`. A key named
 * `password` is a credential wherever it sits, so the walk descends through
 * every nested object and list. Nothing but a matching key is touched — sibling
 * keys, ordering and the surrounding structure survive unchanged, including the
 * now-empty `attributes` / `old` objects (an emptied diff still proves that a
 * change happened, which is audit information in its own right).
 *
 * ## Irreversible by design
 *
 * There is no undo: the point is that the hash stops existing. Take a database
 * backup before running it. Restoring an older backup brings the hashes back,
 * so re-run this command afterwards — it is idempotent and safe to run any
 * number of times.
 *
 * The deny list itself lives in {@see SensitiveLogAttributes}; the write-path
 * guard that keeps NEW rows clean is the `HasActivityLogging` trait.
 */
final class RedactActivityLogSecretsCommand extends Command
{
    /**
     * Rows read (and at most rewritten) per round trip.
     *
     * Bounded on purpose. A single table-wide `UPDATE` on `activity_log` — the
     * biggest, hottest table in a mature install — would hold row locks over
     * the entire table for the duration of the statement and stall every
     * request that logs an activity. 500 keeps each transaction short enough to
     * interleave with live traffic.
     */
    public const DEFAULT_CHUNK_SIZE = 500;

    private const MAX_CHUNK_SIZE = 5000;

    /**
     * Rows a {@see self::probe()} reads before it stops looking.
     *
     * The only thing standing between `sk:doctor` and a multi-minute stall on a
     * mature activity log. See probe() for why a hard row cap is the only bound
     * that holds on every driver.
     */
    public const PROBE_ROW_LIMIT = 500;

    /**
     * Table name used when the configured activity model cannot be resolved.
     */
    private const FALLBACK_TABLE = 'activity_log';

    /**
     * Primary key name used when the configured activity model cannot be
     * resolved (or does not declare a usable single-column key).
     */
    private const FALLBACK_KEY = 'id';

    /**
     * The JSON columns that may carry credentials, newest shape first.
     *
     * @var list<string>
     */
    private const REDACTABLE_COLUMNS = ['attribute_changes', 'properties'];

    /**
     * Drivers on which the `LIKE` pre-filter is known to work.
     *
     * MySQL/MariaDB coerce a JSON column to its string form for `LIKE`, and on
     * SQLite the column is plain text. PostgreSQL has no `json LIKE` operator
     * and would fatal, so every other driver falls back to scanning all rows —
     * slower, but it can never silently skip a row.
     *
     * @var list<string>
     */
    private const PREFILTER_DRIVERS = ['mysql', 'mariadb', 'sqlite'];

    protected $signature = 'sk:redact-activity-secrets
        {--chunk= : Rows processed per round trip (default 500, max 5000)}
        {--all : Scan every row instead of pre-filtering on the sensitive key names}
        {--dry-run : Report what would be redacted without writing anything}';

    protected $description = 'Remove password hashes and other credentials from existing activity_log rows (irreversible; back up first).';

    public function handle(): int
    {
        $chunkSize = $this->option('chunk') !== null
            ? (int) $this->option('chunk')
            : self::DEFAULT_CHUNK_SIZE;

        $dryRun = (bool) $this->option('dry-run');

        $stats = self::redact(
            chunkSize: $chunkSize,
            scanEveryRow: (bool) $this->option('all'),
            dryRun: $dryRun,
        );

        if ($stats['table'] === null) {
            $this->info('No activity log table found — nothing to redact.');

            return Command::SUCCESS;
        }

        if ($stats['columns'] === []) {
            $this->info("Table [{$stats['table']}] has no JSON payload column — nothing to redact.");

            return Command::SUCCESS;
        }

        $verb = $dryRun ? 'would be redacted' : 'redacted';

        $this->info(sprintf(
            '%s: %d row(s) %s out of %d scanned (columns: %s).',
            $stats['table'],
            $stats['redacted'],
            $verb,
            $stats['scanned'],
            implode(', ', $stats['columns']),
        ));

        if ($stats['invalid'] > 0) {
            $this->warn(sprintf(
                '%d JSON payload(s) could not be decoded and were left untouched — inspect them by hand.',
                $stats['invalid'],
            ));
        }

        if ($dryRun && $stats['redacted'] > 0) {
            $this->warn('Dry run: nothing was written. Re-run without --dry-run to apply.');
        }

        return Command::SUCCESS;
    }

    /**
     * The redaction routine itself — callable without a console.
     *
     * Deliberately static and output-free so the vendor-resident data migration
     * (`database/migrations/2026_08_15_120000_redact_secrets_from_activity_log.php`,
     * auto-loaded by `StarterKitServiceProvider::registerMigrations()`) can
     * invoke it directly instead of going through `Artisan::call()`: during
     * `migrate` the command is only registered when the service provider saw
     * `runningInConsole()`, and a migration run from a web/deploy hook would
     * otherwise blow up on an unknown command.
     *
     * This is the EXHAUSTIVE pass — it walks the table to the end. `sk:doctor`
     * deliberately does not call it; see {@see self::probe()} for the bounded,
     * collation-independent variant and why the difference matters.
     *
     * Idempotent: a row is written only when the strip actually removed
     * something, so a second run reports 0 redactions.
     *
     * @return array{table: string|null, columns: list<string>, scanned: int, redacted: int, invalid: int}
     */
    public static function redact(
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        bool $scanEveryRow = false,
        bool $dryRun = false,
    ): array {
        $stats = ['table' => null, 'columns' => [], 'scanned' => 0, 'redacted' => 0, 'invalid' => 0];

        [$connectionName, $table, $keyName] = self::resolveTarget();

        // The kit ships to apps that never published the Spatie migration, and
        // this routine also runs from a migration whose position relative to
        // the activity-log migration is not guaranteed. A missing table is a
        // valid install shape, not an error.
        if (! Schema::connection($connectionName)->hasTable($table)) {
            return $stats;
        }

        $stats['table'] = $table;

        $columns = array_values(array_filter(
            self::REDACTABLE_COLUMNS,
            static fn (string $column): bool => Schema::connection($connectionName)->hasColumn($table, $column),
        ));

        if ($columns === []) {
            return $stats;
        }

        $stats['columns'] = $columns;

        $connection = DB::connection($connectionName);
        $chunkSize = max(1, min(self::MAX_CHUNK_SIZE, $chunkSize));

        // The key column, not a hard-coded `id`: `resolveTarget()` already
        // honours a custom activity model's table and connection, and a model
        // whose primary key is named anything else would otherwise fail mid-run
        // on an unknown column — halfway through a migration, with part of the
        // history redacted and part not.
        $query = $connection->table($table)->select(array_merge([$keyName], $columns));

        if (! $scanEveryRow && in_array($connection->getDriverName(), self::PREFILTER_DRIVERS, true)) {
            // Grouped on purpose: `chunkById` appends `and id > ?` at the top
            // level, and an ungrouped OR chain would swallow that condition and
            // loop forever over the same page.
            $query->where(static function ($group) use ($columns): void {
                foreach (self::likePatterns() as $pattern) {
                    foreach ($columns as $column) {
                        $group->orWhere($column, 'like', $pattern);
                    }
                }
            });
        }

        // chunkById, never chunk(): the rewrite removes the very keys the
        // pre-filter selects on, so an OFFSET-based pager would renumber the
        // result set under its own feet and skip every second page. Keyset
        // paging by ascending key only ever moves forward.
        $query->chunkById($chunkSize, static function (Collection $rows) use (&$stats, $connection, $table, $columns, $keyName, $dryRun): void {
            $updates = [];

            foreach ($rows as $row) {
                $stats['scanned']++;
                $payload = [];

                foreach ($columns as $column) {
                    $raw = $row->{$column} ?? null;

                    if (! is_string($raw) || trim($raw) === '') {
                        continue;
                    }

                    $decoded = json_decode($raw, false);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $stats['invalid']++;

                        continue;
                    }

                    $changed = false;
                    $clean = self::stripSensitive($decoded, $changed);

                    if (! $changed) {
                        continue;
                    }

                    $encoded = json_encode($clean);

                    if ($encoded === false) {
                        // Cannot happen for a value that just decoded cleanly —
                        // and if it somehow does, failing loudly beats writing a
                        // corrupt payload or silently leaving the hash in place.
                        throw new RuntimeException(
                            "Failed to re-encode redacted [{$column}] for {$table} {$keyName} "
                            .var_export($row->{$keyName}, true).': '.json_last_error_msg()
                        );
                    }

                    $payload[$column] = $encoded;
                }

                if ($payload === []) {
                    continue;
                }

                $stats['redacted']++;

                if (! $dryRun) {
                    // A list of pairs rather than a key-value map: PHP silently
                    // casts a numeric-string array key to int, which would
                    // rewrite the identity of a string primary key (uuid,
                    // ulid) before it ever reaches the WHERE clause.
                    $updates[] = [$row->{$keyName}, $payload];
                }
            }

            if ($updates === []) {
                return;
            }

            // One short transaction per chunk: bounded lock duration, and a
            // crash mid-run leaves whole chunks applied rather than half a row.
            $connection->transaction(static function () use ($connection, $table, $keyName, $updates): void {
                foreach ($updates as [$key, $payload]) {
                    $connection->table($table)->where($keyName, $key)->update($payload);
                }
            });
        }, $keyName);

        return $stats;
    }

    /**
     * Bounded read-only probe: is there still a credential in the log?
     *
     * `redact(dryRun: true)` answers the same question EXACTLY, and that is why
     * it is the wrong tool for `sk:doctor` (`ActivityLogSecretsCheck`), which
     * has a five-second budget. Two failure modes forced this second entry
     * point:
     *
     *  - the pre-filtered scan is not case-correct. Its `LIKE` patterns are
     *    lowercase, and on MySQL/MariaDB a JSON column compares under a
     *    case-sensitive collation, so a row whose only sensitive key is
     *    `Password` never matches. A doctor check that reports OK because it
     *    skipped the dirty row is worse than no check — it asserts a safety
     *    that does not hold.
     *  - the unfiltered scan (every non-pre-filter driver, PostgreSQL included)
     *    decodes the WHOLE table through `chunkById`. On a mature log that is
     *    minutes of round trips, which is not a probe.
     *
     * Fixing the first by dropping the pre-filter would hand every driver the
     * second problem, so neither is solved in SQL here. Instead:
     *
     *  - the candidate set is bounded by a hard row cap, not by a `WHERE` the
     *    database might have to scan the whole table to satisfy. A filtered
     *    `LIMIT` is only cheap when matches EXIST — on a clean table it
     *    degenerates into the full scan it was meant to avoid, on every driver.
     *    A plain ordered `LIMIT` over the primary key is one indexed range
     *    scan of a fixed size, identically bounded on MySQL, MariaDB, SQLite
     *    and PostgreSQL alike.
     *  - the sensitivity decision happens in PHP, through the same
     *    {@see self::stripSensitive()} walk the redactor uses, so it inherits
     *    `SensitiveLogAttributes::matches()`'s case-INsensitivity. No collation
     *    takes part in the answer, and `Password` is caught exactly like
     *    `password`.
     *
     * Ordering ascending by the primary key aims the window where the evidence
     * is: the credential-bearing rows are by definition OLD ones, written
     * before the deny list existed, so an install that never ran the cleanup
     * has dirty rows at the very front. (On a uuid/ulid key the same window is
     * an arbitrary but stable slice — a sample rather than the oldest rows,
     * which is still a valid answer to "was the cleanup skipped entirely?".)
     *
     * The price is honesty about the limit: outside `exhaustive`, `dirty` is a
     * FLOOR over `scanned` rows, never a table-wide total, and a clean result
     * means "clean within the window", not "clean". Callers must report it that
     * way. The exhaustive count remains `redact(scanEveryRow: true, dryRun: true)`'s
     * job — WITH `scanEveryRow`, because the prefilter this method rejects is
     * the same one a plain `dryRun` would still be using.
     *
     * Reads only — nothing here writes, at any limit.
     *
     * @return array{table: string|null, columns: list<string>, scanned: int, dirty: int, invalid: int, exhaustive: bool}
     */
    public static function probe(int $limit = self::PROBE_ROW_LIMIT): array
    {
        $stats = [
            'table' => null,
            'columns' => [],
            'scanned' => 0,
            'dirty' => 0,
            'invalid' => 0,
            // Vacuously true until a row proves otherwise: a missing table and
            // a column-less table have both been seen in full.
            'exhaustive' => true,
        ];

        [$connectionName, $table, $keyName] = self::resolveTarget();

        if (! Schema::connection($connectionName)->hasTable($table)) {
            return $stats;
        }

        $stats['table'] = $table;

        $columns = array_values(array_filter(
            self::REDACTABLE_COLUMNS,
            static fn (string $column): bool => Schema::connection($connectionName)->hasColumn($table, $column),
        ));

        if ($columns === []) {
            return $stats;
        }

        $stats['columns'] = $columns;
        $limit = max(1, min(self::MAX_CHUNK_SIZE, $limit));

        $rows = DB::connection($connectionName)
            ->table($table)
            ->select(array_merge([$keyName], $columns))
            ->orderBy($keyName)
            // One row past the window on purpose: whether it came back is how
            // the probe knows it did NOT see the whole table, and it costs one
            // row instead of a `COUNT(*)` that would itself scan the table.
            ->limit($limit + 1)
            ->get();

        $stats['exhaustive'] = $rows->count() <= $limit;

        foreach ($rows->take($limit) as $row) {
            $stats['scanned']++;

            foreach ($columns as $column) {
                $raw = $row->{$column} ?? null;

                if (! is_string($raw) || trim($raw) === '') {
                    continue;
                }

                $decoded = json_decode($raw, false);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $stats['invalid']++;

                    continue;
                }

                // The walk mutates its own local decode and nothing else; only
                // the flag is kept. A row counts once however many of its
                // columns are dirty — `dirty` counts ROWS, like `redacted`.
                $changed = false;
                self::stripSensitive($decoded, $changed);

                if ($changed) {
                    $stats['dirty']++;

                    break;
                }
            }
        }

        return $stats;
    }

    /**
     * Recursively drop every sensitive key from a decoded JSON payload.
     *
     * Decoded as objects (not associative arrays) so an emptied `{}` re-encodes
     * as `{}` and not as `[]` — round-tripping through arrays would flip the
     * JSON type of every emptied sub-object and change what the activity-log UI
     * receives.
     */
    private static function stripSensitive(mixed $node, bool &$changed): mixed
    {
        if ($node instanceof stdClass) {
            foreach (get_object_vars($node) as $key => $value) {
                if (SensitiveLogAttributes::matches((string) $key)) {
                    unset($node->{$key});
                    $changed = true;

                    continue;
                }

                $node->{$key} = self::stripSensitive($value, $changed);
            }

            return $node;
        }

        if (is_array($node)) {
            foreach ($node as $index => $value) {
                $node[$index] = self::stripSensitive($value, $changed);
            }

            return $node;
        }

        return $node;
    }

    /**
     * `LIKE` patterns that select rows worth decoding.
     *
     * A pre-filter only, never the decision of what gets removed — that is
     * `SensitiveLogAttributes::matches()`. The patterns match the QUOTED key
     * name (`"password"`), which survives MySQL's JSON normalisation, and the
     * suffix patterns match a superset (`_` is a single-character LIKE
     * wildcard, left unescaped on purpose): reading a few extra rows costs one
     * decode that changes nothing, while missing a row would leave a credential
     * behind. Use `--all` when even that is not conservative enough — e.g. a
     * case-variant key name on a case-sensitive MySQL JSON collation.
     *
     * @return list<string>
     */
    private static function likePatterns(): array
    {
        $patterns = [];

        foreach (SensitiveLogAttributes::ATTRIBUTES as $attribute) {
            $patterns[] = '%"'.$attribute.'"%';
        }

        foreach (SensitiveLogAttributes::SUFFIXES as $suffix) {
            $patterns[] = '%'.$suffix.'"%';
        }

        return $patterns;
    }

    /**
     * Resolve the connection + table + primary key the activity log lives on.
     *
     * Read off the configured `activitylog.activity_model` rather than
     * hard-coding `activity_log`: a consumer that pointed the package at its
     * own model (different table, or a dedicated audit connection) must have
     * THAT table redacted, not an empty default one. The key NAME comes from
     * the same instance for the same reason — the routine pages and updates by
     * it, so assuming `id` on a model that renamed its key would abort the run
     * on an unknown column, and inside a migration that means a half-redacted
     * history.
     *
     * @return array{0: string|null, 1: string, 2: string}
     */
    private static function resolveTarget(): array
    {
        /** @var mixed $model */
        $model = config('activitylog.activity_model', 'Spatie\\Activitylog\\Models\\Activity');

        if (is_string($model) && $model !== '' && class_exists($model)) {
            try {
                $instance = new $model;

                if ($instance instanceof Model) {
                    $keyName = $instance->getKeyName();

                    return [
                        $instance->getConnectionName(),
                        $instance->getTable(),
                        // A composite/absent key would make keyset paging
                        // meaningless; fall back to the conventional column
                        // rather than build a broken query.
                        is_string($keyName) && $keyName !== '' ? $keyName : self::FALLBACK_KEY,
                    ];
                }
            } catch (Throwable) {
                // A model that cannot be constructed without arguments is not
                // worth failing the whole redaction over — fall back below.
            }
        }

        return [null, self::FALLBACK_TABLE, self::FALLBACK_KEY];
    }
}
