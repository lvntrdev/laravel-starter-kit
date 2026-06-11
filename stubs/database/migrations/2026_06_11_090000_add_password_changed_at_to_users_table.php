<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive, single-step: adds a nullable `password_changed_at` timestamp so
     * the kit can track password age (used by the password-expiry policy).
     * Existing rows are backfilled with the current time so the expiry counter
     * starts at deploy rather than retroactively expiring everyone. The backfill
     * is idempotent (whereNull) — re-running on a partially-populated table only
     * touches rows that are still null.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('password_changed_at')->nullable()->after('password');
            });
        }

        // Idempotent backfill: stamp existing users so password-age tracking
        // begins now. Null rows only — safe to re-run.
        DB::table('users')
            ->whereNull('password_changed_at')
            ->update(['password_changed_at' => now()]);
    }

    /**
     * Reverse the migrations.
     *
     * Drops the column. Only the column's data is lost (acceptable — it is
     * derived tracking state, not user-authored content).
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'password_changed_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('password_changed_at');
            });
        }
    }
};
