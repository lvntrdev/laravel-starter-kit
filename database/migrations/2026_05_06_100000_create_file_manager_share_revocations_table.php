<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_manager_share_revocations', function (Blueprint $table) {
            $table->id();

            // Multi-tenant support: null = single-tenant ortam
            $table->string('tenant_id')->nullable()->index();

            // Hangi medya kaydının paylaşımı revoke edildi
            $table->unsignedBigInteger('media_id');
            $table->foreign('media_id')->references('id')->on('media')->cascadeOnDelete();

            // Signed URL'nin `signature` query parametresinin SHA256 hash'i.
            // Revocation lookup'ı O(1) yapar; uzunluk 64 karakter (hex).
            //
            // K2 (security): Composite unique (media_id, signed_token_hash) yerine
            // tekil signed_token_hash unique artık yok. Composite unique, User B'nin
            // User A'nın token_hash'ini kendi media_id'siyle revoke etmesini engeller.
            // Her (media, token) çifti benzersizdir; farklı medyalar aynı hash'i paylaşamaz.
            $table->string('signed_token_hash', 64);
            $table->unique(['media_id', 'signed_token_hash'], 'fm_share_rev_media_token_unique');

            // Revocation zamanı — firstOrCreate idempotency için nullable değil.
            $table->timestamp('revoked_at');

            // Kim revoke etti (nullable: programatik revoke durumunda null olabilir)
            $table->unsignedBigInteger('revoked_by_user_id')->nullable();
            $table->foreign('revoked_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_manager_share_revocations');
    }
};
