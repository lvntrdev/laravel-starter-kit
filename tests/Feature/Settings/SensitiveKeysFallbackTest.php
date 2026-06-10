<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Lvntr\StarterKit\Domain\Setting\SettingService;
use Lvntr\StarterKit\Tests\Stubs\TestSetting;

/*
|--------------------------------------------------------------------------
| SettingService — sensitive_keys constructor fallback (divergence guard)
|--------------------------------------------------------------------------
|
| config/settings.php app-owned'dır ve service provider tarafından merge
| EDİLMEZ: publish edilmemiş / eski kopyalı consumer'da tek güvenlik ağı
| SettingService constructor'ındaki fallback dizisidir. Fallback stub
| config'deki `sensitive_keys` listesinden ayrışırsa eksik kalan secret'lar
| DB'ye PLAINTEXT yazılır (geçmişte aws_secret/turnstile/postman/apidog
| için yaşandı). Bu test iki şeyi garanti eder:
|
|   1. Parity: fallback dizisi stubs/config/settings.php ile aynı set.
|   2. Runtime: config yokken fallback'teki her key encrypted yazılır,
|      decrypt round-trip çalışır; sensitive olmayan key plaintext kalır.
|
| SettingService App\Models\Setting FQCN'ine yazar; package test ortamında
| App\ autoload edilmediğinden TestSetting stub'ı alias'lanır.
|
*/

if (! class_exists(Setting::class)) {
    class_alias(TestSetting::class, Setting::class);
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. Divergence guard: fallback ≡ stubs/config/settings.php sensitive_keys
// ──────────────────────────────────────────────────────────────────────────────

it('keeps the constructor fallback in sync with stubs/config/settings.php sensitive_keys', function (): void {
    // Test ortamında settings config'i yüklenmemiş olmalı — fallback'in
    // gerçekten devreye girdiğini garanti eder. (offsetUnset KULLANILMAZ:
    // key'i null'a set eder ve config() default yerine null döndürür.)
    expect(config()->has('settings.sensitive_keys'))->toBeFalse(
        'Test ortamında settings.sensitive_keys set edilmiş — fallback test edilemiyor.'
    );

    $stubConfig = require dirname(__DIR__, 3).'/stubs/config/settings.php';

    expect($stubConfig)->toHaveKey('sensitive_keys');

    $service = new SettingService;
    $fallback = new ReflectionProperty(SettingService::class, 'sensitiveKeys')
        ->getValue($service);

    expect($fallback)->toEqualCanonicalizing(
        $stubConfig['sensitive_keys'],
        'SettingService fallback dizisi stubs/config/settings.php sensitive_keys '
        .'listesinden ayrışmış. İkisini birebir eşitle — aksi halde config '
        .'publish etmemiş consumer\'larda eksik key\'ler plaintext yazılır.'
    );
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. Runtime: config yokken fallback her sensitive key'i encrypted yazar
// ──────────────────────────────────────────────────────────────────────────────

it('encrypts sensitive setting writes via the fallback when config is absent', function (string $path): void {
    expect(config()->has('settings.sensitive_keys'))->toBeFalse();

    $plain = 'super-secret-'.str_replace('.', '-', $path);

    app(SettingService::class)->setValue($path, $plain);

    [$group, $key] = explode('.', $path, 2);
    $row = TestSetting::query()->where('group', $group)->where('key', $key)->firstOrFail();

    expect($row->encrypted)->toBeTrue()
        ->and($row->value)->not->toBe($plain)
        ->and($row->value)->not->toContain($plain)
        ->and(Crypt::decryptString($row->value))->toBe($plain);

    // Okuma yolu: decryptIfNeeded round-trip
    expect(app(SettingService::class)->getValue($path))->toBe($plain);
})->with([
    'mail.password',
    'storage.spaces_secret',
    'storage.aws_secret',
    'turnstile.secret_key',
    'postman.api_key',
    'apidog.access_token',
]);

// ──────────────────────────────────────────────────────────────────────────────
// 3. Sanity: sensitive olmayan key plaintext kalır (over-encryption regresyonu yok)
// ──────────────────────────────────────────────────────────────────────────────

it('stores non-sensitive settings as plaintext with encrypted=false', function (): void {
    app(SettingService::class)->setValue('general.app_name', 'Starter Kit');

    $row = TestSetting::query()->where('group', 'general')->where('key', 'app_name')->firstOrFail();

    expect($row->encrypted)->toBeFalse()
        ->and($row->value)->toBe('Starter Kit');
});
