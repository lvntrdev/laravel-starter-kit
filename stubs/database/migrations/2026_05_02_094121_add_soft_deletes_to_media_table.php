<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a nullable `deleted_at` column to the Spatie media table so the
     * FileManager trash feature can soft-delete files. The column is nullable
     * with a null default, which preserves Spatie's default behaviour for any
     * code path that does not opt into the SoftDeletes trait.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
