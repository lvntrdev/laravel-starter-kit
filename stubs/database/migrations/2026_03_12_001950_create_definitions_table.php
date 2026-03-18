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
        Schema::create('definitions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index();
            $table->string('value');
            $table->string('label');
            $table->text('explanation')->nullable();
            $table->string('severity')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('order')->default(0);
            $table->boolean('visibility')->default(true);
            $table->string('lang')->default('en');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['key', 'value', 'lang']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('definitions');
    }
};
