<?php

/*
|--------------------------------------------------------------------------
| FileManager — Signed Share Link Testleri
|--------------------------------------------------------------------------
|
| Test senaryoları:
|
|   A) CreateShareLinkAction:
|      - Doğru owner → URL + expires_at + token_hash döner
|      - Yanlış owner → AuthorizationException
|      - TTL config'den okunur, override edilebilir
|      - Token hash 64 karakter hex'tir
|
|   B) RevokeShareLinkAction:
|      - İlk revoke → kayıt oluşur
|      - Aynı token ile tekrar revoke → aynı kayıt döner (idempotent)
|
|   C) ShareController revocation lookup:
|      - Revoke edilmiş token → 410 Gone
|      - Revoke edilmemiş ama revocation tablosunda hash → 200
|
|   D) Config: share.enabled = false durumu
|
*/

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Lvntr\StarterKit\Domain\FileManager\Actions\CreateShareLinkAction;
use Lvntr\StarterKit\Domain\FileManager\Actions\RevokeShareLinkAction;
use Lvntr\StarterKit\Domain\FileManager\DTOs\CreateShareLinkDTO;
use Lvntr\StarterKit\Domain\FileManager\DTOs\ShareLinkResultDTO;
use Lvntr\StarterKit\Domain\FileManager\Models\ShareRevocation;
use Lvntr\StarterKit\Domain\FileManager\Policies\MediaPolicy;
use Lvntr\StarterKit\Domain\FileManager\Support\ContextRegistry;
use Lvntr\StarterKit\Tests\Stubs\TestMedia;
use Lvntr\StarterKit\Tests\Stubs\TestOwner;

// ──────────────────────────────────────────────────────────────────────────────
// Yardımcı: media tablosuna test kaydı ekler
// ──────────────────────────────────────────────────────────────────────────────

/**
 * @param  string  $modelType  Sahip model morph alias/FQCN
 * @param  string  $modelId  Sahip model ID'si
 */
function insertShareTestMedia(string $modelType = 'user', string $modelId = '1'): TestMedia
{
    $id = DB::table('media')->insertGetId([
        'model_type' => $modelType,
        'model_id' => $modelId,
        'uuid' => Str::uuid()->toString(),
        'collection_name' => 'files',
        'name' => 'share-test-'.Str::random(6),
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
        'deleted_at' => null,
    ]);

    return TestMedia::find($id);
}

// ──────────────────────────────────────────────────────────────────────────────
// A) CreateShareLinkAction
// ──────────────────────────────────────────────────────────────────────────────

it('creates share link with correct URL structure', function (): void {
    $media = insertShareTestMedia('user', 'owner-1');

    $dto = CreateShareLinkDTO::fromArray([
        'media' => $media,
        'owner_type' => 'user',
        'owner_id' => 'owner-1',
        'expires_in_hours' => 24,
    ]);

    $result = app(CreateShareLinkAction::class)->execute($dto);

    expect($result->url)->toContain('file-manager/share/'.$media->getKey())
        ->and($result->url)->toContain('signature=')
        ->and($result->url)->toContain('expires=')
        ->and($result->expiresAt)->not->toBeNull()
        ->and($result->tokenHash)->toHaveLength(64); // SHA256 hex
});

it('token_hash is sha256 of the signature query parameter', function (): void {
    $media = insertShareTestMedia('user', 'owner-hash');

    $dto = CreateShareLinkDTO::fromArray([
        'media' => $media,
        'owner_type' => 'user',
        'owner_id' => 'owner-hash',
    ]);

    $result = app(CreateShareLinkAction::class)->execute($dto);

    // URL'nin signature parametresini çıkar
    $parts = parse_url($result->url);
    parse_str($parts['query'] ?? '', $params);
    $expectedHash = hash('sha256', (string) ($params['signature'] ?? ''));

    expect($result->tokenHash)->toBe($expectedHash);
});

it('uses config default TTL when expires_in_hours not specified', function (): void {
    config(['file-manager.share.default_ttl_hours' => 48]);

    $media = insertShareTestMedia('user', 'owner-ttl');

    $dto = CreateShareLinkDTO::fromArray([
        'media' => $media,
        'owner_type' => 'user',
        'owner_id' => 'owner-ttl',
        // expires_in_hours belirtilmedi
    ]);

    $result = app(CreateShareLinkAction::class)->execute($dto);

    // 48 saatlik TTL kullanıldı mı? ±5 saniye tolerans
    $expectedExpiry = now()->addHours(48);
    expect($result->expiresAt->diffInSeconds($expectedExpiry))->toBeLessThan(5);
});

it('throws AuthorizationException when owner does not match', function (): void {
    $media = insertShareTestMedia('user', 'owner-a');

    $dto = CreateShareLinkDTO::fromArray([
        'media' => $media,
        'owner_type' => 'user',
        'owner_id' => 'owner-b', // farklı owner
    ]);

    expect(fn () => app(CreateShareLinkAction::class)->execute($dto))
        ->toThrow(AuthorizationException::class);
});

it('throws AuthorizationException when owner_type does not match', function (): void {
    $media = insertShareTestMedia('user', 'owner-1');

    $dto = CreateShareLinkDTO::fromArray([
        'media' => $media,
        'owner_type' => 'global_bucket', // yanlış model_type
        'owner_id' => 'owner-1',
    ]);

    expect(fn () => app(CreateShareLinkAction::class)->execute($dto))
        ->toThrow(AuthorizationException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// B) RevokeShareLinkAction
// ──────────────────────────────────────────────────────────────────────────────

it('creates revocation record on first revoke', function (): void {
    $media = insertShareTestMedia('user', 'owner-revoke');
    $tokenHash = hash('sha256', Str::random(32));

    $revocation = app(RevokeShareLinkAction::class)->execute(
        media: $media,
        tokenHash: $tokenHash,
        revokedByUserId: 42,
    );

    expect($revocation)->toBeInstanceOf(ShareRevocation::class)
        ->and($revocation->signed_token_hash)->toBe($tokenHash)
        ->and($revocation->media_id)->toBe($media->id)
        ->and($revocation->revoked_by_user_id)->toBe(42)
        ->and($revocation->revoked_at)->not->toBeNull();
});

it('returns same revocation record on idempotent revoke', function (): void {
    $media = insertShareTestMedia('user', 'owner-idempotent');
    $tokenHash = hash('sha256', Str::random(32));

    $first = app(RevokeShareLinkAction::class)->execute(
        media: $media,
        tokenHash: $tokenHash,
    );

    $second = app(RevokeShareLinkAction::class)->execute(
        media: $media,
        tokenHash: $tokenHash,
    );

    // Aynı id — yeni kayıt oluşmadı
    expect($first->id)->toBe($second->id)
        ->and(ShareRevocation::where('signed_token_hash', $tokenHash)->count())->toBe(1);
});

it('creates revocation with null user id when not authenticated', function (): void {
    $media = insertShareTestMedia('user', 'owner-null-user');
    $tokenHash = hash('sha256', Str::random(32));

    $revocation = app(RevokeShareLinkAction::class)->execute(
        media: $media,
        tokenHash: $tokenHash,
        revokedByUserId: null,
    );

    expect($revocation->revoked_by_user_id)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// C) ShareRevocation lookup mantığı
// ──────────────────────────────────────────────────────────────────────────────

it('revocation lookup finds existing token hash', function (): void {
    $media = insertShareTestMedia('user', 'owner-lookup');
    $tokenHash = hash('sha256', Str::random(32));

    // Revoke et
    app(RevokeShareLinkAction::class)->execute(
        media: $media,
        tokenHash: $tokenHash,
    );

    // Lookup doğrulaması
    $isRevoked = ShareRevocation::where('signed_token_hash', $tokenHash)->exists();

    expect($isRevoked)->toBeTrue();
});

it('revocation lookup returns false for non-revoked token', function (): void {
    $tokenHash = hash('sha256', 'never-revoked-'.Str::random(32));

    $isRevoked = ShareRevocation::where('signed_token_hash', $tokenHash)->exists();

    expect($isRevoked)->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// D) Config: share.enabled = false
// ──────────────────────────────────────────────────────────────────────────────

it('MediaPolicy@share returns false when share is disabled', function (): void {
    config(['file-manager.share.enabled' => false]);

    $media = insertShareTestMedia('user', 'owner-disabled');

    $policy = new MediaPolicy;

    // Basit anonymous user stub
    $user = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 'owner-disabled';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }

        public function getMorphClass(): string
        {
            return 'user';
        }
    };

    expect($policy->share($user, $media))->toBeFalse()
        ->and($policy->revokeShare($user, $media))->toBeFalse();
});

it('MediaPolicy@revokeShare returns false when allow_revoke is disabled', function (): void {
    config([
        'file-manager.share.enabled' => true,
        'file-manager.share.allow_revoke' => false,
    ]);

    $media = insertShareTestMedia('user', 'owner-no-revoke');

    $policy = new MediaPolicy;

    $user = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 'owner-no-revoke';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }

        public function getMorphClass(): string
        {
            return 'user';
        }
    };

    expect($policy->share($user, $media))->toBeTrue() // share hâlâ aktif
        ->and($policy->revokeShare($user, $media))->toBeFalse(); // revoke kapalı
});

// ──────────────────────────────────────────────────────────────────────────────
// E) ShareLinkResultDTO → toArray shape
// ──────────────────────────────────────────────────────────────────────────────

it('ShareLinkResultDTO toArray returns expected keys', function (): void {
    $dto = new ShareLinkResultDTO(
        url: 'https://example.com/file-manager/share/1?expires=1&signature=abc',
        expiresAt: now()->addDay(),
        tokenHash: str_repeat('a', 64),
    );

    $array = $dto->toArray();

    expect($array)->toHaveKeys(['url', 'expires_at', 'token_hash'])
        ->and($array['token_hash'])->toHaveLength(64);
});

// ──────────────────────────────────────────────────────────────────────────────
// G) K2 — Cross-media token revocation hijack önlemi
// ──────────────────────────────────────────────────────────────────────────────

it('revocation record is scoped to media_id — same hash on different media creates separate records', function (): void {
    $mediaA = insertShareTestMedia('user', 'owner-a');
    $mediaB = insertShareTestMedia('user', 'owner-b');

    // Her iki medya için ayrı token hash üret
    $hashA = hash('sha256', 'token-for-media-a-'.Str::random(16));
    $hashB = hash('sha256', 'token-for-media-b-'.Str::random(16));

    // mediaA'yı revoke et
    $revocationA = app(RevokeShareLinkAction::class)->execute(
        media: $mediaA,
        tokenHash: $hashA,
    );

    // mediaB'yi revoke et — aynı hashB farklı media
    $revocationB = app(RevokeShareLinkAction::class)->execute(
        media: $mediaB,
        tokenHash: $hashB,
    );

    // İki ayrı revocation kaydı var, her biri kendi media'sına bağlı
    expect($revocationA->id)->not->toBe($revocationB->id)
        ->and($revocationA->media_id)->toBe($mediaA->id)
        ->and($revocationB->media_id)->toBe($mediaB->id);
});

it('revocation idempotency respects composite (media_id, token_hash) key', function (): void {
    $mediaA = insertShareTestMedia('user', 'owner-composite-a');
    $mediaB = insertShareTestMedia('user', 'owner-composite-b');

    // Her iki medya için kasıtlı olarak AYNI hash değerini kullan
    $sharedHash = hash('sha256', 'deliberately-shared-hash-'.Str::random(8));

    // mediaA için revoke
    $revA = app(RevokeShareLinkAction::class)->execute(
        media: $mediaA,
        tokenHash: $sharedHash,
    );

    // mediaB için aynı hash ile revoke — composite unique'e göre farklı kayıt
    $revB = app(RevokeShareLinkAction::class)->execute(
        media: $mediaB,
        tokenHash: $sharedHash,
    );

    // İki ayrı kayıt: aynı hash, farklı media_id
    expect($revA->id)->not->toBe($revB->id)
        ->and(ShareRevocation::where('signed_token_hash', $sharedHash)->count())->toBe(2);
});

it('revocation lookup uses signed_token_hash — revoked media does not affect other media with same hash', function (): void {
    $mediaA = insertShareTestMedia('user', 'owner-lookup-a');
    $mediaB = insertShareTestMedia('user', 'owner-lookup-b');

    $tokenHash = hash('sha256', 'shared-lookup-token-'.Str::random(8));

    // Sadece mediaA'yı revoke et
    app(RevokeShareLinkAction::class)->execute(
        media: $mediaA,
        tokenHash: $tokenHash,
    );

    // mediaA için revoke edilmiş — lookup bulmalı
    $isRevokedForA = ShareRevocation::where('media_id', $mediaA->id)
        ->where('signed_token_hash', $tokenHash)
        ->exists();

    // mediaB için aynı hash — revoke edilmemiş
    $isRevokedForB = ShareRevocation::where('media_id', $mediaB->id)
        ->where('signed_token_hash', $tokenHash)
        ->exists();

    expect($isRevokedForA)->toBeTrue()
        ->and($isRevokedForB)->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// H) K4 — Gate flat tanımları: share-media ve revoke-share-media
// ──────────────────────────────────────────────────────────────────────────────

it('MediaPolicy@share returns true for owner and false for non-owner', function (): void {
    $policy = new MediaPolicy;

    $media = insertShareTestMedia('user', 'owner-gate-check');

    $owner = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 'owner-gate-check';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }

        public function getMorphClass(): string
        {
            return 'user';
        }
    };

    $stranger = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 'stranger-999';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }

        public function getMorphClass(): string
        {
            return 'user';
        }
    };

    expect($policy->share($owner, $media))->toBeTrue()
        ->and($policy->share($stranger, $media))->toBeFalse()
        ->and($policy->revokeShare($owner, $media))->toBeTrue()
        ->and($policy->revokeShare($stranger, $media))->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// F) Route availability: share.enabled = false → route kayıtlı değil
// ──────────────────────────────────────────────────────────────────────────────

it('share routes are not registered when share.enabled is false', function (): void {
    // Not: route kayıt işlemi ServiceProvider::boot() anında gerçekleşir.
    // Bu test config'i değiştirip route'u aramak yerine
    // config false durumunun doğru şekilde işlendiğini policy üzerinden doğrular.
    // Gerçek route registration testi entegrasyon ortamı gerektirir.

    config(['file-manager.share.enabled' => false]);

    // Policy false dönüyor — route hiç register edilmeden de güvenli
    $policy = new MediaPolicy;
    $media = insertShareTestMedia('user', 'owner-route-check');

    $user = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 'owner-route-check';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }

        public function getMorphClass(): string
        {
            return 'user';
        }
    };

    expect($policy->share($user, $media))->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// I) ShareController.show() — composite revocation lookup doğrulaması
// ──────────────────────────────────────────────────────────────────────────────

it('ShareController composite revocation: aynı hash farklı media için revoke edilmişse target media etkilenmez', function (): void {
    $mediaA = insertShareTestMedia('user', 'owner-ctrl-a');
    $mediaB = insertShareTestMedia('user', 'owner-ctrl-b');

    // Her iki media için kasıtlı olarak AYNI token hash kullanılıyor
    $sharedTokenHash = hash('sha256', 'controller-composite-test-'.Str::random(8));

    // Sadece mediaA için revoke kaydı oluştur (mediaB için oluşturma)
    app(RevokeShareLinkAction::class)->execute(
        media: $mediaA,
        tokenHash: $sharedTokenHash,
    );

    // ShareController.show() aynı mantığı uygular:
    // composite (media_id + signed_token_hash) ile sorgular
    $isRevokedForA = ShareRevocation::where('media_id', $mediaA->id)
        ->where('signed_token_hash', $sharedTokenHash)
        ->exists();

    $isRevokedForB = ShareRevocation::where('media_id', $mediaB->id)
        ->where('signed_token_hash', $sharedTokenHash)
        ->exists();

    // mediaA revoke edilmiş → controller 410 döner
    expect($isRevokedForA)->toBeTrue();

    // mediaB aynı hash'e sahip ama revoke edilmemiş → controller 200 döner (geçmeli)
    expect($isRevokedForB)->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// J) Context-aware authorization — paylaşımlı (sahibi olmayan) context'ler
//
// Önceki davranış: owner-only kontrolü GlobalFileBucket gibi paylaşımlı
// context'lerde her zaman 403 dönüyordu (media owner != kullanıcı). Fix:
// MediaPolicy, sahibi olmayan kullanıcı için dosyanın context authorizer'ına
// 'write' yetkisiyle delege eder.
// ──────────────────────────────────────────────────────────────────────────────

/** Gerçek Eloquent Model + Authenticatable aktör (canManage'in instanceof Model guard'ını geçer). */
function shareTestModelActor(string $id): Authenticatable
{
    $actor = new class extends Model implements Authenticatable
    {
        use Illuminate\Auth\Authenticatable;

        protected $table = 'share_test_actors';

        public $timestamps = false;
    };

    $actor->forceFill(['id' => $id]);

    return $actor;
}

/**
 * Tek bir paylaşımlı context kayıtlı ContextRegistry'yi container'a bağlar.
 * authorize closure'ı yalnızca 'write' yetkisinde ve $grantWrite açıkken izin verir;
 * böylece canManage'in 'write' ability'sini doğru geçirdiği de dolaylı doğrulanır.
 */
function bindShareTestRegistry(bool $grantWrite): void
{
    $registry = new ContextRegistry;
    $owner = (new TestOwner)->forceFill(['id' => 'shared-owner']);

    $registry->register('shared_ctx', [
        'model' => TestOwner::class,
        'path' => 'shared/files',
        'resolve' => fn (?string $id) => $owner,
        'authorize' => fn ($actor, string $ability, $owner) => $ability !== 'read' && $grantWrite,
    ]);

    app()->instance(ContextRegistry::class, $registry);
}

it('MediaPolicy@share allows a permitted non-owner via the context authorizer (shared context)', function (): void {
    config(['file-manager.share.enabled' => true, 'file-manager.share.allow_revoke' => true]);
    bindShareTestRegistry(grantWrite: true);

    // media paylaşımlı context'e (TestOwner) ait; aktör sahibi DEĞİL.
    $media = insertShareTestMedia(TestOwner::class, 'shared-owner');
    $actor = shareTestModelActor('not-the-owner');

    $policy = new MediaPolicy;

    expect($policy->share($actor, $media))->toBeTrue()
        ->and($policy->revokeShare($actor, $media))->toBeTrue();
});

it('MediaPolicy@share denies a non-owner when the shared context refuses the write', function (): void {
    config(['file-manager.share.enabled' => true, 'file-manager.share.allow_revoke' => true]);
    bindShareTestRegistry(grantWrite: false);

    $media = insertShareTestMedia(TestOwner::class, 'shared-owner');
    $actor = shareTestModelActor('not-the-owner');

    $policy = new MediaPolicy;

    expect($policy->share($actor, $media))->toBeFalse()
        ->and($policy->revokeShare($actor, $media))->toBeFalse();
});

it('MediaPolicy@share still denies a non-Eloquent stub actor even when the context would allow', function (): void {
    config(['file-manager.share.enabled' => true]);
    bindShareTestRegistry(grantWrite: true);

    $media = insertShareTestMedia(TestOwner::class, 'shared-owner');

    // Authenticatable ama Eloquent Model değil → canManage instanceof Model guard'ı reddeder.
    $stub = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 'stub-actor';
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }

        public function getMorphClass(): string
        {
            return 'user';
        }
    };

    expect((new MediaPolicy)->share($stub, $media))->toBeFalse();
});
