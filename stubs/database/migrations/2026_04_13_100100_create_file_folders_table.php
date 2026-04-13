<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_folders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_id')->nullable()->index();
            $table->string('name');
            $table->string('owner_type')->nullable();
            $table->uuid('owner_id')->nullable();
            $table->uuid('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_type', 'owner_id']);
            $table->unique(['owner_type', 'owner_id', 'parent_id', 'name'], 'file_folders_owner_parent_name_unique');

            $table->foreign('parent_id')->references('id')->on('file_folders')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_folders');
    }
};
