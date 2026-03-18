<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert morph id columns from bigint to char(36) for UUID support.
     */
    public function up(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name', 'activity_log');

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->char('subject_id', 36)->nullable()->change();
            $table->char('causer_id', 36)->nullable()->change();
        });
    }

    /**
     * Revert to bigint unsigned.
     */
    public function down(): void
    {
        $connection = config('activitylog.database_connection');
        $table = config('activitylog.table_name', 'activity_log');

        Schema::connection($connection)->table($table, function (Blueprint $table) {
            $table->unsignedBigInteger('subject_id')->nullable()->change();
            $table->unsignedBigInteger('causer_id')->nullable()->change();
        });
    }
};
