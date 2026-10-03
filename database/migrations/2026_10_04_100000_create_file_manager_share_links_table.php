<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_manager_share_links', function (Blueprint $table) {
            $table->id();

            // Which media record the share link was issued for
            $table->unsignedBigInteger('media_id');
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();

            // SHA256 (hex, 64 chars) of the signed URL's `signature` query parameter —
            // same hashing as file_manager_share_revocations so the two tables join.
            // Neither the URL nor the raw signature is ever stored.
            $table->string('signed_token_hash', 64);
            $table->unique(['media_id', 'signed_token_hash'], 'fm_share_links_media_token_unique');

            $table->timestamp('expires_at');

            // Who generated the link (users.id is a UUID)
            $table->uuid('created_by_user_id')->nullable();
            $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_manager_share_links');
    }
};
