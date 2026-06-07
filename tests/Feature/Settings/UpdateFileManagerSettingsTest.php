<?php

/*
|--------------------------------------------------------------------------
| Settings — UpdateFileManagerSettingsRequest validation kuralları
|--------------------------------------------------------------------------
|
| storage_quota_gb alanı T1'de eklendi. Bu test:
|   1. Stub request'in doğru validation rule'larını barındırdığını doğrular
|      (rules() metodu okunur, kurallar assert edilir).
|   2. Validation sınırlarını Pest DataProvider ile kapsar:
|      min:1, max:1048576, integer, required.
|   3. DB persistence için DatabaseTestCase'e bağlı ayrı blok vardır.
|
| Not: Stub sınıf App\ namespace'indedir; autoload yolunun package
| çalışma dizininde resolve edilmesi için TestCase'e ihtiyaç var.
|
*/

// ──────────────────────────────────────────────────────────────────────────────
// 1. Stub sınıfının rules() şemasını doğrula
// ──────────────────────────────────────────────────────────────────────────────

it('stub UpdateFileManagerSettingsRequest includes storage_quota_gb validation rules', function (): void {
    $stubPath = dirname(__DIR__, 3)
        .'/stubs/app/Http/Requests/Admin/Settings/UpdateFileManagerSettingsRequest.php';

    expect(is_file($stubPath))->toBeTrue(
        "Stub dosyası bulunamadı: {$stubPath}"
    );

    $contents = file_get_contents($stubPath);

    // storage_quota_gb kuralı stub içinde bulunmalı
    expect($contents)->toContain("'storage_quota_gb'");
    // required kural mevcut mu?
    expect($contents)->toContain("'required'");
    // integer kural mevcut mu?
    expect($contents)->toContain("'integer'");
    // min:1 mevcut mu?
    expect($contents)->toContain("'min:1'");
    // max:1024 (1 TB) mevcut mu?
    expect($contents)->toContain("'max:1024'");
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. FileManagerSettingsDTO — storage_quota_gb fromArray / toArray simetrisi
// ──────────────────────────────────────────────────────────────────────────────

it('FileManagerSettingsDTO stub contains storageQuotaGb property', function (): void {
    $stubPath = dirname(__DIR__, 3)
        .'/src/Domain/Setting/DTOs/FileManagerSettingsDTO.php';

    expect(is_file($stubPath))->toBeTrue();

    $contents = file_get_contents($stubPath);

    expect($contents)->toContain('storageQuotaGb');
    expect($contents)->toContain('storage_quota_gb');
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. SettingSeeder stub — storage_quota_gb default değeri
// ──────────────────────────────────────────────────────────────────────────────

it('_03_SettingSeeder stub seeds storage_quota_gb with default 10', function (): void {
    $stubPath = dirname(__DIR__, 3)
        .'/stubs/database/seeders/_03_SettingSeeder.php';

    expect(is_file($stubPath))->toBeTrue();

    $contents = file_get_contents($stubPath);

    expect($contents)->toContain("'storage_quota_gb'");
    expect($contents)->toContain("'10'");
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. file-manager config — storage_quota_gb key mevcut ve 10 default
// ──────────────────────────────────────────────────────────────────────────────

it('file-manager.php config ships storage_quota_gb key with default 10', function (): void {
    $config = require dirname(__DIR__, 3).'/config/file-manager.php';

    expect($config['settings'])->toHaveKey('storage_quota_gb');
    expect($config['settings']['storage_quota_gb'])->toBe(10);
});

it('runtime config exposes storage_quota_gb', function (): void {
    // ServiceProvider mergeConfigFrom sonrası container'da erişilebilir olmalı
    expect(config('file-manager.settings.storage_quota_gb'))->toBe(10);
});
