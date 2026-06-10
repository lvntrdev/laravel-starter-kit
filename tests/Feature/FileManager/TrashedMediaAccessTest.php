<?php

/*
|--------------------------------------------------------------------------
| FileManager — Trashed Media Access Guard Testleri
|--------------------------------------------------------------------------
|
| Güvenlik semantiği: trash = erişilemez (404). Soft-deleted media,
| geçerli bir signed share link veya authenticated download ile dahi
| servis edilmemeli; trash'teki dosya için YENİ share link üretilememeli.
| Dosya purge'a kadar diskte kalır; restore sonrası (revoke/expire yoksa)
| erişim geri gelir. Revoke bir kill-switch'tir — trash'te de çalışır.
|
| Mekanizma: StarterKitServiceProvider::boot() içinde cache-safe
| Route::model('media', config('media-library.media_model')) binder'ı.
| Configured model (TestMedia / consumer Media) SoftDeletes kullandığından
| global scope trashed satırları tüm {media} binding sitelerinde 404'e
| düşürür. ShareController::store scoped findOrFail, ::revoke withTrashed
| kullanır.
|
| Test senaryoları:
|   A) Provider boot binder kaydı ({media} + {folder})
|   B) Trashed + geçerli signed URL → share show 404 (410 DEĞİL — oracle yok)
|   C) Trashed → authenticated download 404
|   D) Trashed → {media} binding'li mutation route (rename) 404
|   E) Trashed → share store (yeni link) 404
|   F) Trashed → revoke ÇALIŞIR; restore sonrası link revoke edilmiş kalır (410)
|   G) Restore → revoke edilmemiş link yeniden çalışır (200)
|
*/

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable as AuthorizableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Lvntr\StarterKit\Domain\FileManager\Actions\CreateShareLinkAction;
use Lvntr\StarterKit\Domain\FileManager\DTOs\CreateShareLinkDTO;
use Lvntr\StarterKit\Domain\FileManager\Models\ShareRevocation;
use Lvntr\StarterKit\Tests\Stubs\TestMedia;

// ──────────────────────────────────────────────────────────────────────────────
// Yardımcılar
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Media tablosuna test kaydı ekler (opsiyonel olarak trash'te).
 */
function insertTrashedGuardMedia(string $modelType = 'user', string $modelId = '1', bool $trashed = false): TestMedia
{
    $id = DB::table('media')->insertGetId([
        'model_type' => $modelType,
        'model_id' => $modelId,
        'uuid' => Str::uuid()->toString(),
        'collection_name' => 'files',
        'name' => 'trash-guard-'.Str::random(6),
        'file_name' => 'document.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'public',
        'conversions_disk' => null,
        'size' => 1024,
        'manipulations' => '[]',
        'custom_properties' => '[]',
        'generated_conversions' => '[]',
        'responsive_images' => '[]',
        'order_column' => null,
        'folder_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => $trashed ? now() : null,
    ]);

    return TestMedia::withTrashed()->find($id);
}

/**
 * actingAs için Eloquent Model + Authenticatable aktör.
 *
 * getMorphClass 'user' döner — media.model_type ile eşleşir, MediaPolicy
 * owner fast-path'i (isOwner) geçer.
 */
function trashedGuardActor(string $id): Authenticatable
{
    $actor = new class extends Model implements Authenticatable, AuthorizableContract
    {
        use AuthenticatableTrait, AuthorizableTrait;

        protected $table = 'users';

        protected $guarded = [];

        public $timestamps = false;

        // String id ('owner-…') int'e cast edilmesin — getAuthIdentifier()
        // aksi halde 0 döner ve MediaPolicy owner eşleşmesi sessizce kırılır.
        public $incrementing = false;

        protected $keyType = 'string';

        public function getMorphClass(): string
        {
            return 'user';
        }
    };

    return $actor->forceFill(['id' => $id]);
}

// ──────────────────────────────────────────────────────────────────────────────
// A) Cache-safe binder kaydı — provider boot
// ──────────────────────────────────────────────────────────────────────────────

it('registers cache-safe {media} and {folder} route binders in provider boot', function (): void {
    // {media} binder'ı YALNIZ StarterKitServiceProvider::boot() içinde
    // kayıtlıdır (routes dosyasında değil) — varlığı, route:cache altında da
    // çalışan provider-boot kayıt yolunu kanıtlar.
    $router = $this->app['router'];

    expect($router->getBindingCallback('media'))->not->toBeNull()
        ->and($router->getBindingCallback('folder'))->not->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// B) Trashed media + geçerli signed URL → 404
// ──────────────────────────────────────────────────────────────────────────────

it('returns 404 for trashed media even with a still-valid signed share URL', function (): void {
    Storage::fake('public');

    $media = insertTrashedGuardMedia('user', 'owner-trash-show');
    Storage::disk('public')->put($media->getPathRelativeToRoot(), 'trash-guard-bytes');

    $url = URL::temporarySignedRoute(
        'file-manager.share.show',
        now()->addHour(),
        ['media' => $media->getKey()],
    );

    // Sanity: dosya canlıyken link çalışıyor.
    $this->get($url)->assertOk();

    // Soft delete → trash. Dosya diskte KALIR (Spatie observer yalnız force
    // delete'te fiziksel siler) ama artık dışarı servis edilmemeli.
    $media->delete();
    expect(Storage::disk('public')->exists($media->getPathRelativeToRoot()))->toBeTrue();

    // 404 — 410 DEĞİL: var olmayan media ile birebir aynı görünüm,
    // trash/revocation durumu public endpoint'ten sızdırılmaz.
    $this->get($url)->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// C) Trashed media → authenticated download 404
// ──────────────────────────────────────────────────────────────────────────────

it('returns 404 for trashed media on the authenticated download route', function (): void {
    $actor = trashedGuardActor('owner-trash-dl');
    $media = insertTrashedGuardMedia('user', 'owner-trash-dl', trashed: true);

    $this->actingAs($actor)
        ->get(route('file-manager.files.download', [
            'media' => $media->getKey(),
            'context' => 'user',
        ]))
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// D) Trashed media → {media} binding'li mutation route 404 (temsilci: rename)
// ──────────────────────────────────────────────────────────────────────────────

it('returns 404 for trashed media on mutation routes resolved via the {media} binder', function (): void {
    $actor = trashedGuardActor('owner-trash-rename');
    $media = insertTrashedGuardMedia('user', 'owner-trash-rename', trashed: true);

    $this->actingAs($actor)
        ->patchJson(route('file-manager.files.rename', ['media' => $media->getKey()]), [
            'name' => 'renamed.pdf',
            'context' => 'user',
        ])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// E) Trashed media → YENİ share link üretilemez (404)
// ──────────────────────────────────────────────────────────────────────────────

it('returns 404 when creating a new share link for trashed media', function (): void {
    $actor = trashedGuardActor('owner-trash-store');
    $media = insertTrashedGuardMedia('user', 'owner-trash-store', trashed: true);

    // Sahibi bile olsa: trash'teki dosya için yeni paylaşım açılamaz.
    // Request authorize (withTrashed + owner) geçer; enforcement noktası
    // controller'ın scoped findOrFail'i → 404.
    $this->actingAs($actor)
        ->postJson(route('file-manager.share.store'), ['media_id' => $media->getKey()])
        ->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// F) Revoke kill-switch — trash'te ÇALIŞIR, restore sonrası link ölü kalır
// ──────────────────────────────────────────────────────────────────────────────

it('allows revoking a share link while the media is trashed and keeps it dead after restore', function (): void {
    Storage::fake('public');

    $actor = trashedGuardActor('owner-trash-revoke');
    $media = insertTrashedGuardMedia('user', 'owner-trash-revoke');
    Storage::disk('public')->put($media->getPathRelativeToRoot(), 'revoke-bytes');

    // Dosya canlıyken link üret.
    $result = app(CreateShareLinkAction::class)->execute(CreateShareLinkDTO::fromArray([
        'media' => $media,
        'owner_type' => 'user',
        'owner_id' => 'owner-trash-revoke',
    ]));

    // Trash'e taşı.
    $media->delete();

    // Trash'teyken revoke çalışmalı (güvenlik kill-switch'i).
    $this->actingAs($actor)
        ->postJson(route('file-manager.share.revoke'), [
            'media_id' => $media->getKey(),
            'token' => $result->tokenHash,
        ])
        ->assertOk();

    // ShareRevocation satırı yazıldı.
    expect(
        ShareRevocation::where('media_id', $media->getKey())
            ->where('signed_token_hash', $result->tokenHash)
            ->exists(),
    )->toBeTrue();

    // Restore sonrası link revoke edilmiş KALMALI → 410 Gone.
    $media->restore();
    $this->get($result->url)->assertStatus(410);
});

// ──────────────────────────────────────────────────────────────────────────────
// G) Restore → revoke edilmemiş, süresi dolmamış link yeniden çalışır
// ──────────────────────────────────────────────────────────────────────────────

it('serves the file again after restore when the link was never revoked or expired', function (): void {
    Storage::fake('public');

    $media = insertTrashedGuardMedia('user', 'owner-trash-restore');
    Storage::disk('public')->put($media->getPathRelativeToRoot(), 'restore-bytes');

    $url = URL::temporarySignedRoute(
        'file-manager.share.show',
        now()->addHour(),
        ['media' => $media->getKey()],
    );

    // Trash'te erişilemez…
    $media->delete();
    $this->get($url)->assertNotFound();

    // …restore sonrası erişim geri gelir (istenen semantiği belgeler).
    $media->restore();

    $response = $this->get($url);
    $response->assertOk();
    expect($response->streamedContent())->toBe('restore-bytes');
});
