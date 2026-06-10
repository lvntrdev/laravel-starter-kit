<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::table($tableNames['roles'], static function (Blueprint $table) {
            // Tailwind color name (e.g. 'indigo') or PrimeVue severity — used by role tags in the UI.
            $table->string('color', 32)->nullable()->after('group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['roles'], static function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
