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
        Schema::create('file_favorites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner_type');
            $table->uuid('owner_id');
            $table->string('favoritable_type', 16); // 'folder' | 'file'
            $table->string('favoritable_id'); // folder UUID or media integer id (stored as string)
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['owner_type', 'owner_id', 'favoritable_type', 'favoritable_id'],
                'file_favorites_unique',
            );
            $table->index(['owner_type', 'owner_id'], 'file_favorites_owner_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_favorites');
    }
};
