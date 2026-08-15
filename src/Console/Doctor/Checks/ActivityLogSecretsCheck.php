<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Commands\RedactActivityLogSecretsCommand;
use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Throwable;

/**
 * Are there activity-log rows that still carry a credential?
 *
 * The write-path guard (`HasActivityLogging`) is vendor-resident, so a plain
 * `composer update` closes the leak for NEW rows immediately and silently. The
 * cleanup half — the data migration that strips credentials out of rows written
 * before the deny list existed — only runs on `php artisan migrate`. An
 * operator who updated the package but never migrated therefore sees no error
 * anywhere while every historical password hash stays readable on the
 * activity-log screen. This check is what surfaces that.
 *
 * It calls `RedactActivityLogSecretsCommand::probe()`: read-only, bounded to a
 * fixed number of rows, and case-insensitive because the sensitivity decision
 * runs in PHP rather than under a database collation. A non-zero finding is a
 * FAIL, not a warn — an exposed credential is not a style issue.
 *
 * What it deliberately does NOT call is `redact(dryRun: true)`, which answers
 * the same question exactly but cannot be trusted inside a five-second budget:
 * its pre-filter misses a `Password`-cased key on MySQL/MariaDB (reporting OK
 * over a dirty row, which is worse than not checking at all), and without the
 * pre-filter — PostgreSQL and every other driver — it decodes the entire table.
 * probe() carries the reasoning for the shape that replaced it.
 *
 * The cost of a bounded probe is that it cannot prove a large table clean, and
 * the messages below say so instead of implying a total: a positive finding is
 * reported as a floor ("at least N"), and a clean bounded result names the
 * window it covered and points at the exhaustive command. Only when the probe
 * saw the whole table does it speak in absolute counts.
 */
class ActivityLogSecretsCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'Activity Log Secrets';
    }

    public function run(): DoctorReport
    {
        try {
            $stats = RedactActivityLogSecretsCommand::probe();
        } catch (Throwable $e) {
            return DoctorReport::warn(
                $this->name(),
                'Could not inspect the activity log for stored credentials: '.$e->getMessage(),
                'Check the database connection, then run `php artisan sk:redact-activity-secrets --dry-run --all` by hand.'
            );
        }

        if ($stats['table'] === null) {
            return DoctorReport::ok(
                $this->name(),
                'No activity log table on this connection — there is nothing that could hold a credential.'
            );
        }

        if ($stats['columns'] === []) {
            return DoctorReport::ok(
                $this->name(),
                "Table [{$stats['table']}] has no JSON payload column — there is nowhere for a credential to be stored."
            );
        }

        if ($stats['dirty'] > 0) {
            return DoctorReport::fail(
                $this->name(),
                $stats['exhaustive']
                    ? sprintf(
                        '%d row(s) in [%s] still contain credentials (password hashes, tokens or secrets) readable from the activity-log screen.',
                        $stats['dirty'],
                        $stats['table'],
                    )
                    : sprintf(
                        'At least %d row(s) in [%s] still contain credentials (password hashes, tokens or secrets) readable from the activity-log screen. The probe stopped after %d row(s), so that is a floor, not the total.',
                        $stats['dirty'],
                        $stats['table'],
                        $stats['scanned'],
                    ),
                'Back up the database, then run `php artisan migrate` (or `php artisan sk:redact-activity-secrets`). Removal is irreversible.'
            );
        }

        if ($stats['invalid'] > 0) {
            return DoctorReport::warn(
                $this->name(),
                sprintf(
                    '%d JSON payload(s) in [%s] could not be decoded, so they could not be checked for credentials.',
                    $stats['invalid'],
                    $stats['table'],
                ),
                'Inspect those rows by hand — `php artisan sk:redact-activity-secrets --dry-run --all` reports the count over the whole table.'
            );
        }

        // Two different statements, because they are not equally strong: the
        // first rules out a credential in that table, the second only in the
        // window the probe could afford to read.
        return DoctorReport::ok(
            $this->name(),
            $stats['exhaustive']
                ? "No credential-bearing rows found in [{$stats['table']}] (all {$stats['scanned']} row(s) inspected)."
                : sprintf(
                    'No credential-bearing rows in the first %d row(s) of [%s] — a bounded probe, not a full audit. Run `php artisan sk:redact-activity-secrets --dry-run --all` for the exhaustive count.',
                    $stats['scanned'],
                    $stats['table'],
                )
        );
    }
}
