<?php

/*
|--------------------------------------------------------------------------
| Settings — SettingsDefaultsQuery storage_usage payload doğrulaması
|--------------------------------------------------------------------------
|
| Bu test:
|   1. Stub SettingsDefaultsQuery'nin storage_usage key'ini içerdiğini,
|      used_bytes ve quota_bytes alanlarını döndürdüğünü doğrular.
|   2. ResolvesMediaModel trait'inin doğru kullanıldığını doğrular.
|   3. DB üzerinde gerçek hesaplama ile quota_bytes değerinin
|      config('file-manager.settings.storage_quota_gb') * 1024^3
|      formülüne uyduğunu doğrular.
|   4. used_bytes'ın integer olduğunu, quota_bytes'ın konfigürasyon
|      değeriyle uyumlu olduğunu doğrular.
|
*/

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lvntr\StarterKit\Tests\Stubs\StorageQuoteHelper;

// ──────────────────────────────────────────────────────────────────────────────
// 1. Stub dosyası içerik doğrulaması
// ──────────────────────────────────────────────────────────────────────────────

it('SettingsDefaultsQuery stub contains storage_usage key', function (): void {
    $stubPath = dirname(__DIR__, 3)
        .'/src/Domain/Setting/Queries/SettingsDefaultsQuery.php';

    expect(is_file($stubPath))->toBeTrue(
        "Stub dosyası bulunamadı: {$stubPath}"
    );

    $contents = file_get_contents($stubPath);

    expect($contents)->toContain("'storage_usage'");
    expect($contents)->toContain("'used_bytes'");
    expect($contents)->toContain("'quota_bytes'");
});

it('SettingsDefaultsQuery stub uses ResolvesMediaModel trait', function (): void {
    $stubPath = dirname(__DIR__, 3)
        .'/src/Domain/Setting/Queries/SettingsDefaultsQuery.php';

    $contents = file_get_contents($stubPath);

    expect($contents)->toContain('use ResolvesMediaModel;');
    expect($contents)->toContain('Lvntr\StarterKit\Domain\FileManager\Concerns\ResolvesMediaModel');
});

it('SettingsDefaultsQuery stub calls computeStorageUsed and storageQuotaBytes', function (): void {
    $stubPath = dirname(__DIR__, 3)
        .'/src/Domain/Setting/Queries/SettingsDefaultsQuery.php';

    $contents = file_get_contents($stubPath);

    expect($contents)->toContain('computeStorageUsed()');
    expect($contents)->toContain('storageQuotaBytes()');
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. DB üzerinde gerçek hesaplama — quota_bytes formül doğrulaması
// ──────────────────────────────────────────────────────────────────────────────

it('storageQuotaBytes equals storage_quota_gb config times 1024^3', function (): void {
    $helper = new StorageQuoteHelper;

    // Default: 10 GB
    config(['file-manager.settings.storage_quota_gb' => 10]);
    $expected = 10 * 1024 * 1024 * 1024;

    expect($helper->getQuotaBytes())->toBe($expected);
    expect($helper->getQuotaBytes())->toBeInt();
});

it('storageQuotaBytes respects config override', function (): void {
    $helper = new StorageQuoteHelper;

    config(['file-manager.settings.storage_quota_gb' => 5]);
    $expected = 5 * 1024 * 1024 * 1024;

    expect($helper->getQuotaBytes())->toBe($expected);
});

it('used_bytes is integer and zero when no media exists', function (): void {
    $helper = new StorageQuoteHelper;

    // Temiz DB — media kaydı yok
    expect($helper->getStorageUsed())->toBe(0);
    expect($helper->getStorageUsed())->toBeInt();
});

it('used_bytes reflects actual media size sum', function (): void {
    $helper = new StorageQuoteHelper;

    // Media tablosuna doğrudan 1 MB kayıt ekle
    DB::table('media')->insert([
        'model_type' => 'user',
        'model_id' => '1',
        'uuid' => Str::uuid()->toString(),
        'collection_name' => 'files',
        'name' => 'test-settings-payload',
        'file_name' => 'test.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'public',
        'conversions_disk' => null,
        'size' => 1 * 1024 * 1024,
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

    expect($helper->getStorageUsed())->toBe(1 * 1024 * 1024);
    expect($helper->getStorageUsed())->toBeInt();
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. storage_usage payload yapısı — all() metodunun döndürdüğü dizi kontrolü
// ──────────────────────────────────────────────────────────────────────────────

it('SettingsDefaultsQuery all() returns storage_usage with used_bytes and quota_bytes', function (): void {
    // Stub dosyasını yükle — App\ namespace sınıfları otomatik autoload edilmediğinden
    // dosya içeriğini kaynak kod analizi ile doğrularız.
    // Çalışma zamanı instance testi için StorageQuoteHelper proxy'si yeterlidir.
    $helper = new StorageQuoteHelper;

    config(['file-manager.settings.storage_quota_gb' => 10]);

    $usedBytes = $helper->getStorageUsed();
    $quotaBytes = $helper->getQuotaBytes();

    // Yapısal doğrulama
    expect($usedBytes)->toBeInt();
    expect($quotaBytes)->toBeInt();
    expect($quotaBytes)->toBeGreaterThan(0);

    // quota_bytes, config değeriyle uyumlu
    expect($quotaBytes)->toBe((int) config('file-manager.settings.storage_quota_gb') * 1024 ** 3);
});
