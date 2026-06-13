<?php

use App\Models\Setting;
use App\Providers\SettingsServiceProvider;
use Illuminate\Support\Facades\Validator;
use Lvntr\StarterKit\Domain\Setting\Actions\UpdateAuthSettingsAction;
use Lvntr\StarterKit\Domain\Setting\DTOs\AuthSettingsDTO;
use Lvntr\StarterKit\Domain\Setting\Queries\SettingsDefaultsQuery;
use Lvntr\StarterKit\Domain\Setting\SettingService;
use Lvntr\StarterKit\Http\Requests\Admin\Settings\UpdateAuthSettingsRequest;
use Lvntr\StarterKit\Tests\Stubs\TestSetting;

/*
|--------------------------------------------------------------------------
| Auth Settings — yeni anahtarların uçtan uca akışı (security-settings-redesign T2)
|--------------------------------------------------------------------------
|
| 6 yeni auth.* anahtarı (login_throttle, password_min_length,
| password_expiry_days, password_require_mixed_case/_numbers/_symbols):
|
|   1. SettingsDefaultsQuery::auth() fallback'leri — DB boşken MEVCUT
|      kurulum davranışını koruyan değerler döner (throttle açık, süre
|      sınırsız, parola politikası pre-feature hardened baseline'a eşit:
|      eski stub AppServiceProvider Password::defaults()'u min 10 + mixed
|      case + numbers + symbols'a yükseltiyordu).
|   2. AuthSettingsDTO geri-uyumluluk sözleşmesi — eski (yalnız 4 alanlı)
|      form payload'u yeni alanları NULL bırakır ve toArray() bunları
|      persist payload'undan çıkarır → admin'in kaydettiği değerler
|      sessizce default'a sıfırlanmaz.
|   3. UpdateAuthSettingsAction persist — tam payload DB'ye yazılır,
|      kısmi payload mevcut satırları ezmez.
|   4. UpdateAuthSettingsRequest validation sınırları — yeni alanlar
|      `sometimes` (required DEĞİL), sayısal sınırlar 6-128 / 0-3650.
|   5. SettingsServiceProvider köprüsü — login_throttle '0' iken
|      fortify.limiters.login null'lanır; parola politikası
|      starter-kit.password_policy.* runtime config'ine yazılır.
|
| SettingService App\Models\Setting FQCN'ine yazar; package test ortamında
| App\ autoload edilmediğinden TestSetting stub'ı alias'lanır
| (SensitiveKeysFallbackTest ile aynı desen, guard'lı).
|
*/

if (! class_exists(Setting::class)) {
    class_alias(TestSetting::class, Setting::class);
}

// v13.6.0: UpdateAuthSettingsRequest vendor'a taşındı (PSR-4 autoload). Eski
// consumer'ların App\ import'u alias köprüsüyle bu sınıfa çözülür. SettingsServiceProvider
// hâlâ stub'da (kapsam dışı) — App\ namespace'inde autoload edilmediğinden doğrudan yüklenir.
require_once dirname(__DIR__, 3).'/stubs/app/Providers/SettingsServiceProvider.php';

function legacyAuthPayload(): array
{
    return [
        'registration' => true,
        'email_verification' => true,
        'two_factor' => true,
        'password_reset' => true,
    ];
}

function fullAuthPayload(): array
{
    return legacyAuthPayload() + [
        'login_throttle' => false,
        'password_min_length' => 12,
        'password_expiry_days' => 90,
        'password_require_mixed_case' => true,
        'password_require_numbers' => true,
        'password_require_symbols' => false,
    ];
}

function invokeAuthBridge(): void
{
    $provider = new SettingsServiceProvider(app());

    new ReflectionMethod($provider, 'bridgeAuthSecurityConfig')->invoke($provider);
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. SettingsDefaultsQuery::auth() — geri-uyumlu fallback'ler
// ──────────────────────────────────────────────────────────────────────────────

it('auth() returns behavior-preserving fallbacks when the DB has no new keys', function (): void {
    $auth = app(SettingsDefaultsQuery::class)->auth();

    // Mevcut kurulum davranışı DEĞİŞMEZ: throttle bugün zaten aktif, süre
    // sınırsız ve parola fallback'i pre-feature hardened baseline'a EŞİT —
    // eski stub AppServiceProvider Password::defaults()'u min 10 + mixed
    // case + numbers + symbols'a yükseltiyordu; daha zayıf bir fallback
    // sk:update sonrası politikayı sessizce gevşetirdi (final review B1).
    expect($auth['login_throttle'])->toBeTrue()
        ->and($auth['password_min_length'])->toBe(10)
        ->and($auth['password_expiry_days'])->toBe(0)
        ->and($auth['password_require_mixed_case'])->toBeTrue()
        ->and($auth['password_require_numbers'])->toBeTrue()
        ->and($auth['password_require_symbols'])->toBeTrue();

    // Legacy 4 anahtar fallback'leri regresyonsuz.
    expect($auth['registration'])->toBeTrue()
        ->and($auth['email_verification'])->toBeTrue()
        ->and($auth['two_factor'])->toBeTrue()
        ->and($auth['password_reset'])->toBeTrue();
});

it('auth() reflects stored values with int/bool casts', function (): void {
    app(SettingService::class)->setGroup('auth', [
        'login_throttle' => '0',
        'password_min_length' => '14',
        'password_expiry_days' => '180',
        'password_require_mixed_case' => '1',
        'password_require_numbers' => '0',
        'password_require_symbols' => '1',
    ]);

    $auth = app(SettingsDefaultsQuery::class)->auth();

    expect($auth['login_throttle'])->toBeFalse()
        ->and($auth['password_min_length'])->toBe(14)
        ->and($auth['password_expiry_days'])->toBe(180)
        ->and($auth['password_require_mixed_case'])->toBeTrue()
        ->and($auth['password_require_numbers'])->toBeFalse()
        ->and($auth['password_require_symbols'])->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. AuthSettingsDTO — eski-form sözleşmesi (omit-on-missing)
// ──────────────────────────────────────────────────────────────────────────────

it('DTO maps a full payload to string-persisted values', function (): void {
    $dto = AuthSettingsDTO::fromArray(fullAuthPayload());

    expect($dto->toArray())->toBe([
        'registration' => '1',
        'email_verification' => '1',
        'two_factor' => '1',
        'password_reset' => '1',
        'login_throttle' => '0',
        'password_min_length' => '12',
        'password_expiry_days' => '90',
        'password_require_mixed_case' => '1',
        'password_require_numbers' => '1',
        'password_require_symbols' => '0',
    ]);
});

it('DTO omits the new keys entirely when a legacy 4-field payload is submitted', function (): void {
    $dto = AuthSettingsDTO::fromArray(legacyAuthPayload());

    // Eksik alanlar fallback değerle DOLDURULMAZ — payload'dan çıkarılır.
    // setGroup yalnızca verilen key'leri upsert ettiğinden DB'deki admin
    // değerleri korunur (sessiz policy-downgrade vektörü kapalı).
    expect($dto->toArray())->toBe([
        'registration' => '1',
        'email_verification' => '1',
        'two_factor' => '1',
        'password_reset' => '1',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. UpdateAuthSettingsAction — persist + no-clobber
// ──────────────────────────────────────────────────────────────────────────────

it('action persists all new keys from a full payload', function (): void {
    app(UpdateAuthSettingsAction::class)->execute(AuthSettingsDTO::fromArray(fullAuthPayload()));

    $service = app(SettingService::class);

    expect($service->getValue('auth.login_throttle'))->toBe('0')
        ->and($service->getValue('auth.password_min_length'))->toBe('12')
        ->and($service->getValue('auth.password_expiry_days'))->toBe('90')
        ->and($service->getValue('auth.password_require_mixed_case'))->toBe('1')
        ->and($service->getValue('auth.password_require_numbers'))->toBe('1')
        ->and($service->getValue('auth.password_require_symbols'))->toBe('0');
});

it('action does not clobber stored policy values when a legacy payload is submitted', function (): void {
    $service = app(SettingService::class);

    // Admin yeni UI'dan politikayı sıkılaştırmış olsun.
    $service->setValue('auth.password_min_length', '12');
    $service->setValue('auth.login_throttle', '1');

    // Özelleştirilmiş (eski) SecurityTab yalnız 4 alan POST'lar.
    app(UpdateAuthSettingsAction::class)->execute(AuthSettingsDTO::fromArray(legacyAuthPayload()));

    // Admin'in kaydettiği politika sessizce default'a (baseline '10') inmemeli.
    expect($service->getValue('auth.password_min_length'))->toBe('12')
        ->and($service->getValue('auth.login_throttle'))->toBe('1')
        ->and($service->getValue('auth.registration'))->toBe('1');
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. UpdateAuthSettingsRequest — sometimes + sınırlar
// ──────────────────────────────────────────────────────────────────────────────

it('request accepts a legacy 4-field payload (new keys are sometimes, not required)', function (): void {
    $rules = new UpdateAuthSettingsRequest()->rules();

    expect(Validator::make(legacyAuthPayload(), $rules)->passes())->toBeTrue();
});

it('request still requires the four legacy toggles', function (): void {
    $rules = new UpdateAuthSettingsRequest()->rules();

    $validator = Validator::make([
        'email_verification' => true,
        'two_factor' => true,
        'password_reset' => true,
    ], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('registration'))->toBeTrue();
});

it('request enforces numeric bounds and boolean types on the new keys', function (array $overrides, bool $shouldPass): void {
    $rules = new UpdateAuthSettingsRequest()->rules();

    $validator = Validator::make(legacyAuthPayload() + $overrides, $rules);

    expect($validator->passes())->toBe($shouldPass);
})->with([
    'min_length 5 reddedilir' => [['password_min_length' => 5], false],
    'min_length 6 kabul' => [['password_min_length' => 6], true],
    'min_length 128 kabul' => [['password_min_length' => 128], true],
    'min_length 129 reddedilir' => [['password_min_length' => 129], false],
    'expiry -1 reddedilir' => [['password_expiry_days' => -1], false],
    'expiry 0 kabul (sınırsız)' => [['password_expiry_days' => 0], true],
    'expiry 3650 kabul' => [['password_expiry_days' => 3650], true],
    'expiry 3651 reddedilir' => [['password_expiry_days' => 3651], false],
    'login_throttle string reddedilir' => [['login_throttle' => 'abc'], false],
    'login_throttle bool kabul' => [['login_throttle' => false], true],
    'kural toggle bool kabul' => [['password_require_symbols' => true], true],
    'kural toggle string reddedilir' => [['password_require_numbers' => 'yes'], false],
]);

// ──────────────────────────────────────────────────────────────────────────────
// 5. SettingsServiceProvider köprüsü — throttle gate + policy config
// ──────────────────────────────────────────────────────────────────────────────

it('nulls fortify.limiters.login only when login_throttle is explicitly disabled', function (): void {
    config(['fortify.limiters.login' => 'login']);

    // Key DB'de yokken (mevcut kurulum) gate DOKUNMAZ — throttle açık kalır.
    invokeAuthBridge();
    expect(config('fortify.limiters.login'))->toBe('login');

    // Açıkça '1' iken de dokunmaz.
    app(SettingService::class)->setValue('auth.login_throttle', '1');
    invokeAuthBridge();
    expect(config('fortify.limiters.login'))->toBe('login');

    // Yalnızca bilinçli '0' kaydında null'lanır.
    app(SettingService::class)->setValue('auth.login_throttle', '0');
    invokeAuthBridge();
    expect(config('fortify.limiters.login'))->toBeNull();
});

it('bridges password policy into starter-kit.password_policy with safe defaults', function (): void {
    invokeAuthBridge();

    // DB boş → fallback pre-feature hardened baseline'a eşittir (eski stub
    // AppServiceProvider Password::defaults(): min 10 + mixed case +
    // numbers + symbols) — sk:update mevcut politikayı zayıflatamaz.
    expect(config('starter-kit.password_policy.min_length'))->toBe(10)
        ->and(config('starter-kit.password_policy.expiry_days'))->toBe(0)
        ->and(config('starter-kit.password_policy.require_mixed_case'))->toBeTrue()
        ->and(config('starter-kit.password_policy.require_numbers'))->toBeTrue()
        ->and(config('starter-kit.password_policy.require_symbols'))->toBeTrue();

    app(SettingService::class)->setGroup('auth', [
        'password_min_length' => '14',
        'password_expiry_days' => '60',
        'password_require_mixed_case' => '1',
        'password_require_numbers' => '1',
        'password_require_symbols' => '1',
    ]);

    invokeAuthBridge();

    expect(config('starter-kit.password_policy.min_length'))->toBe(14)
        ->and(config('starter-kit.password_policy.expiry_days'))->toBe(60)
        ->and(config('starter-kit.password_policy.require_mixed_case'))->toBeTrue()
        ->and(config('starter-kit.password_policy.require_numbers'))->toBeTrue()
        ->and(config('starter-kit.password_policy.require_symbols'))->toBeTrue();
});

it('clamps a corrupted min_length to a safety floor of 1', function (): void {
    // Validation yazım yolunda 6-128 zorlar; bu klamp yalnızca elle
    // bozulmuş DB satırına karşı — Password::min(0) asla oluşmamalı.
    app(SettingService::class)->setValue('auth.password_min_length', '0');

    invokeAuthBridge();

    expect(config('starter-kit.password_policy.min_length'))->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// 6. Stub içerik guard'ları — seeder + provider
// ──────────────────────────────────────────────────────────────────────────────

it('_03_SettingSeeder seeds the six new auth keys with the pre-feature hardened baseline', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/stubs/database/seeders/_03_SettingSeeder.php');

    // Seeder değerleri read-time fallback'lerle birebir aynı baseline:
    // min 10 + mixed case + numbers + symbols (eski AppServiceProvider
    // Password::defaults() hardened default'u — final review Blocker 1).
    expect($contents)
        ->toContain("'login_throttle' => '1'")
        ->toContain("'password_min_length' => '10'")
        ->toContain("'password_expiry_days' => '0'")
        ->toContain("'password_require_mixed_case' => '1'")
        ->toContain("'password_require_numbers' => '1'")
        ->toContain("'password_require_symbols' => '1'");
});

it('SettingsServiceProvider stub applies the throttle gate before provider boot', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/stubs/app/Providers/SettingsServiceProvider.php');

    // Gate, booting callback'inde uygulanmalı — Fortify paket provider'ı
    // route'larını (throttle:login middleware dahil) app boot'larından
    // ÖNCE kaydeder; boot() içine yazılan gate route-level middleware'e
    // geç kalır.
    expect($contents)
        ->toContain('$this->app->booting(')
        ->toContain("'fortify.limiters.login' => null")
        ->toContain("'starter-kit.password_policy.min_length'");
});

it('SettingsServiceProvider bridge reads via the query builder, never Eloquent', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/stubs/app/Providers/SettingsServiceProvider.php');

    // Köprü booting() callback'inde koşar — Eloquent'in statik connection
    // resolver'ı DatabaseServiceProvider::boot()'ta set edilir, bu noktada
    // henüz null. Setting::getGroup() (Eloquent) "Call to a member function
    // connection() on null" ile patlar; köprü container'daki `db`'yi kullanan
    // query builder ile okumalı. Bu guard, regresyonu kalıcı olarak kilitler.
    expect($contents)
        ->toContain("DB::table('settings')")
        ->not->toContain("Setting::getGroup('auth')");
});
