<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen activity_log subject/causer id columns to char(36).
     *
     * The polymorphic subject/causer columns must hold ids from BOTH:
     *   - User           → uuid (36 chars)
     *   - Permission/Role → Spatie default bigint (numeric)
     *
     * create_activity_log_table uses nullableUuidMorphs, which produces native
     * uuid columns. Writing a bigint Spatie key into a native uuid column fails
     * (MariaDB 4078: "Cannot cast 'bigint' as 'uuid'"). char(36) accepts a
     * 36-char uuid and any numeric id alike, so a single polymorphic column
     * works for every audited model.
     *
     * Converges every prior state to char(36):
     *   - native uuid (current kit)        → char(36)
     *   - bigint (legacy pre-convert kit)  → char(36)
     *   - char(36) (legacy convert kit)    → no-op
     */
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name', 'activity_log');

        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->char('subject_id', 36)->nullable()->change();
            $table->char('causer_id', 36)->nullable()->change();
        });
    }

    /**
     * Revert to native uuid columns (the create migration's original type).
     */
    public function down(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name', 'activity_log');

        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->uuid('subject_id')->nullable()->change();
            $table->uuid('causer_id')->nullable()->change();
        });
    }
};
