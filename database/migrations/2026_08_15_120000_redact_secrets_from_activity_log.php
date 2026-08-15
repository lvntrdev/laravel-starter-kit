<?php

use Illuminate\Database\Migrations\Migration;
use Lvntr\StarterKit\Console\Commands\RedactActivityLogSecretsCommand;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * DATA ONLY — this migration changes NO schema. It rewrites the JSON
     * payload of existing `activity_log` rows so that credentials written
     * before the kit's activity-log deny list existed stop being readable from
     * the admin activity-log screen.
     *
     * ## What it removes, and what it keeps
     *
     * Removed: `password`, `remember_token`, `two_factor_secret`,
     * `two_factor_recovery_codes` and any key ending in `_token` / `_secret`
     * (`Lvntr\StarterKit\Support\SensitiveLogAttributes`), from BOTH the
     * `attribute_changes` and the `properties` column — spatie/laravel-
     * activitylog 5.x writes model diffs to `attribute_changes`, while rows
     * from older installs carry the same diff inside `properties`, so touching
     * only one column would clean only half the history.
     *
     * Kept: the rows themselves and every other key in them. Deleting the rows
     * was rejected in the plan — that would destroy the audit trail, which is
     * exactly the evidence you want after a credential exposure.
     *
     * ## Why this file is vendor-resident, not a published stub
     *
     * `activity_log` is a kit-owned table: its schema migration lives in this
     * same package directory and is auto-loaded by
     * `StarterKitServiceProvider::registerMigrations()`, and `sk:update`
     * force-deletes any app copy of it (UpdateCommand::DEPRECATED_PATHS) so the
     * vendor tree stays the single source. This cleanup migration follows the
     * table. A published copy would only reach an app through `sk:update`'s
     * file sync, so a consumer who ran nothing but
     * `composer update lvntr/laravel-starter-kit && php artisan migrate` would
     * silently get the write-path guard (vendor-resident, lands immediately)
     * WITHOUT the cleanup — and every historical hash would stay readable on
     * the activity-log screen with nothing surfacing the fact. Living here, the
     * migration runs on the very `migrate` that follows the composer update.
     *
     * ## Every row is scanned — the `LIKE` pre-filter is deliberately skipped
     *
     * `redact()` can pre-filter candidate rows with `LIKE` on the sensitive key
     * names, and that is what the interactive command does by default. This
     * migration passes `scanEveryRow: true` instead, because the pre-filter is
     * only as case-correct as the column's collation: on MySQL a JSON column
     * compares under `utf8mb4_bin`, so `LIKE '%"password"%'` never matches a
     * row whose free-form `withProperties()` payload used `Password`. The
     * removal decision itself (`SensitiveLogAttributes::matches()`) is
     * case-INsensitive, so a pre-filtered pass would skip exactly the rows the
     * routine would otherwise have cleaned. A one-shot cleanup whose entire
     * purpose is "no credential is left behind" cannot be allowed to depend on
     * a collation, and upper/lower `LIKE` variants would still miss `PassWord`.
     * The price is wall-clock time, which is recoverable; a missed hash is not.
     *
     * ## Lock / runtime risk
     *
     * `activity_log` is typically the largest and most write-heavy table in the
     * app, so the routine pages with `chunkById` (keyset paging, 500 rows) and
     * commits one short transaction per chunk. Locks stay row-level and
     * short-lived; nothing here takes a table lock or rewrites the table. Rows
     * that carry no credential are decoded and discarded without a write, so
     * the full scan costs CPU and wall clock, not lock time. Expect the
     * migration step to run for a while on a huge table, but it will not block
     * writes for more than a chunk at a time.
     *
     * ## Missing table
     *
     * The routine guards with `Schema::hasTable()` (and `hasColumn()` per
     * column) because the kit ships to apps that never published Spatie's
     * activity-log migration, and because migration ordering across two
     * packages is not guaranteed. A missing table is a silent no-op, not a
     * failure — re-run `php artisan sk:redact-activity-secrets` after the table
     * exists.
     *
     * ## Verification
     *
     *   php artisan sk:redact-activity-secrets --dry-run
     *
     * reports 0 rows once this migration has run; any non-zero count means new
     * credential-bearing rows appeared and the write-path guard needs a look.
     * `sk:doctor` runs that same dry-run probe (Activity Log Secrets check) so
     * an operator who somehow skipped this migration is told about it instead
     * of having to ask.
     *
     * The routine is called directly rather than through `Artisan::call()`:
     * the command is only registered while `runningInConsole()` is true, and a
     * migration triggered from a web or deploy hook would otherwise fail on an
     * unknown command. The `class_exists()` guard keeps `migrate` from fataling
     * in an app that kept this published migration but no longer has the
     * package installed — there is no kit activity log to redact there.
     */
    public function up(): void
    {
        if (! class_exists(RedactActivityLogSecretsCommand::class)) {
            return;
        }

        RedactActivityLogSecretsCommand::redact(scanEveryRow: true);
    }

    /**
     * Reverse the migrations.
     *
     * INTENTIONAL NO-OP — the redaction is IRREVERSIBLE. The removed values
     * were password hashes and tokens; the whole point is that they no longer
     * exist anywhere, so there is nothing left to restore and nowhere to
     * restore it from. A `down()` that pretended otherwise would be a lie.
     *
     * Rolling this migration back therefore leaves the data redacted; only the
     * migration record disappears, and re-running `up()` is harmless (the
     * routine is idempotent).
     *
     * The recovery path is operational, not code: take a database backup BEFORE
     * `php artisan migrate`. If you restore that backup the hashes come back
     * with it, so run `php artisan sk:redact-activity-secrets` again
     * immediately afterwards.
     */
    public function down(): void
    {
        // No-op by design — see the docblock above.
    }
};
