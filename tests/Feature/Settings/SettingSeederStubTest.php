<?php

/*
|--------------------------------------------------------------------------
| Settings Seeder — Stub şifreleme doğrulama testleri
|--------------------------------------------------------------------------
|
| _03_SettingSeeder hassas ayarları (mail.password, storage.*_secret) ESKİDEN
| doğrudan Setting::firstOrCreate ile PLAINTEXT yazıyordu — SettingService'in
| şifreleme yolunu bypass ederek. Bu testler fix'i kilitler:
|   1. Seeder yazımı SettingService::seedDefault üzerinden yapılır
|   2. Seeder artık raw Setting::firstOrCreate kullanmaz (regresyon guard'ı)
|   3. SettingService::seedDefault mevcut ve hassas anahtarları şifreler
|
| Not: stub App\ sınıfları paket test ortamında autoload edilmediği için
| (autoload-dev yalnızca Tests\ map'ler) doğrulama, repo'nun yerleşik stub
| içerik-assertion deseniyle yapılır — çalıştırma yerine içerik kontrolü.
|
*/

function settingSeederStub(): string
{
    return dirname(__DIR__, 3).'/stubs/database/seeders/_03_SettingSeeder.php';
}

function settingServiceStub(): string
{
    // v16.x (Faz 6): SettingService moved to vendor (src/Domain/Setting). The
    // encryption/seedDefault behavior is unchanged; only the file location moved.
    return dirname(__DIR__, 3).'/src/Domain/Setting/SettingService.php';
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. Seeder, SettingService::seedDefault üzerinden yazıyor
// ──────────────────────────────────────────────────────────────────────────────

it('_03_SettingSeeder stub mevcut', function (): void {
    expect(is_file(settingSeederStub()))->toBeTrue('Stub bulunamadı: '.settingSeederStub());
});

it('_03_SettingSeeder yazımı SettingService::seedDefault üzerinden yapıyor', function (): void {
    $contents = file_get_contents(settingSeederStub());

    expect($contents)
        ->toContain('use App\Domain\Setting\SettingService;')
        ->toContain('SettingService $settings')   // run() içine inject
        ->toContain('$settings->seedDefault(');     // şifreleyen yazım yolu
});

it('_03_SettingSeeder artık plaintext Setting::firstOrCreate kullanmıyor', function (): void {
    $contents = file_get_contents(settingSeederStub());

    // Regresyon guard'ı: hassas değerleri şifrelemeyen eski yazım yolu kalkmış olmalı.
    expect($contents)
        ->not->toContain('Setting::firstOrCreate')
        ->not->toContain('use App\Models\Setting;');
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. SettingService::seedDefault hassas anahtarları şifreliyor
// ──────────────────────────────────────────────────────────────────────────────

it('SettingService stub seedDefault metodunu içeriyor', function (): void {
    $contents = file_get_contents(settingServiceStub());

    expect($contents)->toContain('public function seedDefault(');
});

it('SettingService::seedDefault hassas anahtarları şifreler ve mevcut satırı ezmez', function (): void {
    $contents = file_get_contents(settingServiceStub());

    // seedDefault metodunun gövdesini izole et (imzadan sonraki ilk method'a kadar).
    $start = strpos($contents, 'public function seedDefault(');
    expect($start)->not->toBeFalse();

    $body = substr($contents, $start, 700);

    expect($body)
        ->toContain('$this->sensitiveKeys')        // hassasiyet kontrolü
        ->toContain('Crypt::encryptString')        // at-rest şifreleme
        ->toContain("'encrypted'")                  // encrypted flag yazımı
        ->toContain('firstOrCreate');               // mevcut değeri ezmez (yalnızca yoksa yaz)
});
