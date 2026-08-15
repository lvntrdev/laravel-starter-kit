<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive, single-step: adds a nullable `timezone` column holding the
     * user's preferred IANA timezone identifier (e.g. `Europe/Istanbul`).
     * Data itself is always stored in UTC; this column only drives DISPLAY.
     *
     * `null` is meaningful and is NOT the same as `'UTC'` — it means "follow
     * the site setting", i.e. resolution falls through
     * `user.timezone` → setting `app.display_timezone` → `app.timezone` → UTC.
     * A NOT NULL column defaulting to `'UTC'` would make "following the site"
     * indistinguishable from "deliberately chose UTC", and every existing user
     * would silently stop following the site setting the moment an admin
     * changed it. Hence: nullable, no default, no backfill.
     *
     * No index: the column is never filtered or sorted on — it is read by
     * primary key alongside the rest of the user row. An index on the kit's
     * hottest table would be pure write overhead.
     *
     * Lock/backfill risk: adding a nullable column with no default is an
     * INSTANT, metadata-only operation on MySQL 8 / MariaDB 10.3+ (ALGORITHM
     * INSTANT) and on SQLite — no table rewrite, no row-by-row backfill, so it
     * is safe on a large `users` table. On MySQL 5.7 it would fall back to an
     * in-place rebuild; the kit requires Laravel 13, which does not target 5.7.
     *
     * Deliberately NO `after(...)`: positioning the column mid-table forfeits
     * ALGORITHM=INSTANT on MySQL before 8.0.29 and on MariaDB 10.3, turning
     * this into a full rebuild of the kit's largest table — the exact cost the
     * paragraph above promises to avoid. Column order buys nothing here, so
     * the column is appended. Do not "tidy" it back next to `email`.
     *
     * The `hasColumn` guard makes a re-run on a partially-upgraded app a no-op
     * (a consumer that already hand-added the column keeps its data).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'timezone')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('timezone', 64)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Drops the column. Only the per-user display preference is lost; no
     * stored timestamp is affected, since all data is persisted in UTC and
     * this column never took part in reads/writes of that data. After a
     * rollback every user falls back to the site setting — the exact same
     * behaviour as `null`.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'timezone')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('timezone');
            });
        }
    }
};
