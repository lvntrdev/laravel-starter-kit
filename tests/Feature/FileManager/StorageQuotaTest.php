<?php

/*
|--------------------------------------------------------------------------
| FileManager — Depolama Kotası Testleri (T6)
|--------------------------------------------------------------------------
|
| Plan T6 senaryoları:
|
|   A) Disk-genel hesaplama: farklı model_type/model_id çiftleri için
|      eklenen medyalar computeStorageUsed() ile aynı toplamı dönmeli.
|
|   B) withTrashed davranışı: soft-delete sonrası storage_used azalmamalı;
|      forceDelete sonrası azalmalı.
|
|   C) Upload validation: kota kısıtlandığında mevcut + gelen > kota
|      koşulu doğru hesaplanıyor mu?
|
|   D) Stats payload: üç Query (Folder, Favorites, Trash) response'unda
|      stats.storage_quota alanı bulunmalı.
|
*/

use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Domain\FileManager\Concerns\ResolvesMediaModel;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Lvntr\StarterKit\Domain\FileManager\Queries\FavoritesContentsQuery;
use Lvntr\StarterKit\Domain\FileManager\Queries\FolderContentsQuery;
use Lvntr\StarterKit\Domain\FileManager\Queries\TrashContentsQuery;
use Lvntr\StarterKit\Tests\Stubs\StorageQuoteHelper;
use Lvntr\StarterKit\Tests\Stubs\TestMedia;

// ──────────────────────────────────────────────────────────────────────────────
// Yardımcı: media tablosuna doğrudan kayıt ekler (dosya yükleme olmadan)
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Test fixture: media tablosuna minimal kayıt ekler.
 * Spatie'nin `addMedia()` pipeline'ı yerine direkt DB insert kullanılır;
 * bu testler dosya sistemi değil, boyut toplamı hesabını test ediyor.
 *
 * @param  string  $modelType  model_type (morph alias veya FQCN)
 * @param  string  $modelId
 * @param  int     $sizeBytes
 * @param  bool    $trashed    true ise deleted_at = now()
 * @return int  eklenen satır id'si
 */
function insertMediaRow(string $modelType, string $modelId, int $sizeBytes, bool $trashed = false): int
{
    return DB::table('media')->insertGetId([
        'model_type'           => $modelType,
        'model_id'             => $modelId,
        'uuid'                 => \Illuminate\Support\Str::uuid()->toString(),
        'collection_name'      => 'files',
        'name'                 => 'test-file-'.\Illuminate\Support\Str::random(6),
        'file_name'            => 'test.pdf',
        'mime_type'            => 'application/pdf',
        'disk'                 => 'public',
        'conversions_disk'     => null,
        'size'                 => $sizeBytes,
        'manipulations'        => '[]',
        'custom_properties'    => '[]',
        'generated_conversions' => '[]',
        'responsive_images'    => '[]',
        'order_column'         => null,
        'folder_id'            => null,
        'created_at'           => now(),
        'updated_at'           => now(),
        'deleted_at'           => $trashed ? now() : null,
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// A) Disk-genel hesaplama
// ──────────────────────────────────────────────────────────────────────────────

it('computeStorageUsed returns disk-wide total across different model types', function (): void {
    $helper = new StorageQuoteHelper;

    // Başlangıçta sıfır
    expect($helper->getStorageUsed())->toBe(0);

    // user context: 500 KB
    insertMediaRow('user', '1', 500 * 1024);

    // global context: 300 KB
    insertMediaRow('global_bucket', '1', 300 * 1024);

    // vehicle context: 200 KB
    insertMediaRow('vehicle', '42', 200 * 1024);

    $expected = (500 + 300 + 200) * 1024;

    expect($helper->getStorageUsed())->toBe($expected);
});

it('computeStorageUsed returns same total regardless of queried context', function (): void {
    $helper = new StorageQuoteHelper;

    // user A: 1 MB
    insertMediaRow('user', 'user-a', 1 * 1024 * 1024);

    // user B: 2 MB
    insertMediaRow('user', 'user-b', 2 * 1024 * 1024);

    $total = $helper->getStorageUsed();

    // Her iki context için de hesaplama aynı toplamı vermeli
    // (context parametresi artık yok — disk-genel)
    expect($total)->toBe(3 * 1024 * 1024);
});

// ──────────────────────────────────────────────────────────────────────────────
// B) withTrashed davranışı
// ──────────────────────────────────────────────────────────────────────────────

it('computeStorageUsed includes soft-deleted (trashed) records', function (): void {
    $helper = new StorageQuoteHelper;

    // 1 MB boyutunda normal dosya
    $normalId = insertMediaRow('user', '1', 1 * 1024 * 1024);

    // 512 KB boyutunda çöp kutusundaki dosya
    insertMediaRow('user', '1', 512 * 1024, trashed: true);

    $expected = (1024 + 512) * 1024;

    expect($helper->getStorageUsed())->toBe($expected);
});

it('computeStorageUsed decreases only after forceDelete', function (): void {
    $helper = new StorageQuoteHelper;

    $fileSize = 2 * 1024 * 1024; // 2 MB

    // Normal dosya ekle
    $mediaId = insertMediaRow('user', '1', $fileSize);

    expect($helper->getStorageUsed())->toBe($fileSize);

    // Soft delete — storage_used azalmamalı
    TestMedia::query()->where('id', $mediaId)->update(['deleted_at' => now()]);
    expect($helper->getStorageUsed())->toBe($fileSize, 'Soft-delete sonrası storage_used azalmamalı');

    // Force delete — storage_used artık azalmalı
    TestMedia::withTrashed()->where('id', $mediaId)->forceDelete();
    expect($helper->getStorageUsed())->toBe(0, 'forceDelete sonrası storage_used sıfıra dönmeli');
});

// ──────────────────────────────────────────────────────────────────────────────
// C) Upload kota validation mantığı
// ──────────────────────────────────────────────────────────────────────────────

it('quota calculation: current + incoming exceeds quota', function (): void {
    $helper = new StorageQuoteHelper;

    // Kotayı 1 MB olarak ayarla
    config(['file-manager.settings.storage_quota_mb' => 1]);

    // 800 KB mevcut kullanım
    insertMediaRow('user', '1', 800 * 1024);

    $current  = $helper->getStorageUsed();       // 819200 bytes
    $quota    = $helper->getQuotaBytes();         // 1 * 1024 * 1024 = 1048576
    $incoming = 300 * 1024;                       // 307200 bytes — toplam 1126400 > 1048576

    expect($current + $incoming)->toBeGreaterThan($quota);
});

it('quota calculation: current + incoming within quota is allowed', function (): void {
    $helper = new StorageQuoteHelper;

    config(['file-manager.settings.storage_quota_mb' => 1]);

    // 500 KB mevcut kullanım
    insertMediaRow('user', '1', 500 * 1024);

    $current  = $helper->getStorageUsed();
    $quota    = $helper->getQuotaBytes();
    $incoming = 200 * 1024; // 700 KB toplam < 1 MB

    expect($current + $incoming)->toBeLessThanOrEqual($quota);
});

// ──────────────────────────────────────────────────────────────────────────────
// D) Stats payload — üç Query storage_quota anahtarını döner
// ──────────────────────────────────────────────────────────────────────────────

it('FavoritesContentsQuery stats includes storage_quota key', function (): void {
    // Boş context için minimal owner oluştur
    $context = makeTestContext('user', 'test-user-1');

    $result = (new FavoritesContentsQuery)->execute($context);

    expect($result['stats'])->toHaveKey('storage_quota');
    expect($result['stats']['storage_quota'])->toBeInt();
    expect($result['stats']['storage_quota'])->toBeGreaterThan(0);
});

it('TrashContentsQuery stats includes storage_quota key', function (): void {
    $context = makeTestContext('user', 'test-user-2');

    $result = (new TrashContentsQuery)->execute($context);

    expect($result['stats'])->toHaveKey('storage_quota');
    expect($result['stats']['storage_quota'])->toBeInt();
    expect($result['stats']['storage_quota'])->toBeGreaterThan(0);
});

it('FolderContentsQuery stats includes storage_quota key', function (): void {
    $context = makeTestContext('user', 'test-user-3');

    $result = (new FolderContentsQuery)->execute($context);

    expect($result['stats'])->toHaveKey('storage_quota');
    expect($result['stats']['storage_quota'])->toBeInt();
    expect($result['stats']['storage_quota'])->toBeGreaterThan(0);
});

it('stats.storage_quota equals storageQuotaBytes() helper value', function (): void {
    config(['file-manager.settings.storage_quota_mb' => 512]);

    $helper     = new StorageQuoteHelper;
    $expected   = $helper->getQuotaBytes(); // 512 * 1024 * 1024

    $context = makeTestContext('user', 'test-user-4');
    $result  = (new FavoritesContentsQuery)->execute($context);

    expect($result['stats']['storage_quota'])->toBe($expected);
});

// ──────────────────────────────────────────────────────────────────────────────
// E) storageQuotaBytes config'den MB → byte çevrimini doğru yapar
// ──────────────────────────────────────────────────────────────────────────────

it('storageQuotaBytes correctly converts MB to bytes', function (): void {
    $helper = new StorageQuoteHelper;

    config(['file-manager.settings.storage_quota_mb' => 10240]);
    expect($helper->getQuotaBytes())->toBe(10240 * 1024 * 1024);

    config(['file-manager.settings.storage_quota_mb' => 1]);
    expect($helper->getQuotaBytes())->toBe(1 * 1024 * 1024);

    config(['file-manager.settings.storage_quota_mb' => 1048576]);
    expect($helper->getQuotaBytes())->toBe(1048576 * 1024 * 1024);
});

// ──────────────────────────────────────────────────────────────────────────────
// Yardımcı: test context oluşturucu
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Test ortamında FileManagerContextDTO'yu minimal şekilde oluşturur.
 * ContextRegistry'ye bağımlılık olmadan doğrudan constructor çağrısı.
 *
 * @internal Sadece StorageQuotaTest içinde kullanılır.
 */
function makeTestContext(string $context, string $ownerId): FileManagerContextDTO
{
    // Minimal Eloquent model stub — gerçek tabloya ihtiyaç yok
    $owner = new class extends \Illuminate\Database\Eloquent\Model {
        protected $table = 'test_owners_virtual';

        protected $keyType = 'string';

        public $incrementing = false;
    };

    // Model'e id ata (reflection olmadan)
    $owner->setAttribute('id', $ownerId);
    $owner->exists = false;

    return new FileManagerContextDTO(
        context: $context,
        contextId: $ownerId,
        owner: $owner,
        ownerType: $context,
        ownerId: $ownerId,
    );
}
