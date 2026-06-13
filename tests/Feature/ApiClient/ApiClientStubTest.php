<?php

/*
|--------------------------------------------------------------------------
| API Client UI — Vendor + Stub Dosya Yapısı Testleri
|--------------------------------------------------------------------------
|
| v13.6.0 Faz 2: ApiClient + ApiToken HTTP katmanı (controller/request/resource)
| vendor-first (src/Http/..., namespace Lvntr\StarterKit\...). Publish edilmez;
| eski consumer'ların App\ referansı backward-compat alias ile çözülür. Policy,
| lang, route stub, permission-resources.php ve AppServiceProvider app-owned
| kaldığı için stub/lang yolundan okunur. ApiClient domain Action'ları (Passport
| secret/token üretimi) zaten vendor (src/Domain/ApiClient).
|
| Test senaryoları:
|
|   A) Vendor controller/request/resource dosyaları doğru yolda; stub publish değil
|   B) Resource'lar doğru (vendor) namespace'e sahip mi?
|   C) Permission-resources.php api-clients/api-tokens içeriyor mu? (app-owned)
|   D) Lang dosyaları doğru key'lere sahip mi?
|   E) Route dosyaları doğru isimlendirme + vendor FQCN import kullanıyor mu?
|   F) AppServiceProvider Gate::before ve Passport::tokensCan içeriyor mu?
|
*/

use Illuminate\Validation\Validator;
use Lvntr\StarterKit\Http\Requests\Admin\ApiToken\StoreApiTokenRequest;
use Lvntr\StarterKit\Tests\TestCase;

uses(TestCase::class);

// ── A) Vendor dosyaları (controller/request/resource) + stub publish edilmez ───

test('ApiClientController vendor src/ içinde, stub publish edilmez', function (): void {
    $vendor = dirname(__DIR__, 3).'/src/Http/Controllers/Admin/ApiClientController.php';
    $stub = dirname(__DIR__, 3).'/stubs/app/Http/Controllers/Admin/ApiClientController.php';
    expect(file_exists($vendor))->toBeTrue();
    expect(file_exists($stub))->toBeFalse("ApiClientController hala stub'ta — Faz 2'de vendor'a taşınmalıydı.");
});

test('ApiTokenController vendor src/ içinde, stub publish edilmez', function (): void {
    $vendor = dirname(__DIR__, 3).'/src/Http/Controllers/Admin/ApiTokenController.php';
    $stub = dirname(__DIR__, 3).'/stubs/app/Http/Controllers/Admin/ApiTokenController.php';
    expect(file_exists($vendor))->toBeTrue();
    expect(file_exists($stub))->toBeFalse("ApiTokenController hala stub'ta — Faz 2'de vendor'a taşınmalıydı.");
});

test('StoreApiClientRequest vendor src/ içinde, stub publish edilmez', function (): void {
    $vendor = dirname(__DIR__, 3).'/src/Http/Requests/Admin/ApiClient/StoreApiClientRequest.php';
    $stub = dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/ApiClient/StoreApiClientRequest.php';
    expect(file_exists($vendor))->toBeTrue();
    expect(file_exists($stub))->toBeFalse();
});

test('UpdateApiClientRequest vendor src/ içinde, stub publish edilmez', function (): void {
    $vendor = dirname(__DIR__, 3).'/src/Http/Requests/Admin/ApiClient/UpdateApiClientRequest.php';
    $stub = dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/ApiClient/UpdateApiClientRequest.php';
    expect(file_exists($vendor))->toBeTrue();
    expect(file_exists($stub))->toBeFalse();
});

test('StoreApiTokenRequest vendor src/ içinde, stub publish edilmez', function (): void {
    $vendor = dirname(__DIR__, 3).'/src/Http/Requests/Admin/ApiToken/StoreApiTokenRequest.php';
    $stub = dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/ApiToken/StoreApiTokenRequest.php';
    expect(file_exists($vendor))->toBeTrue();
    expect(file_exists($stub))->toBeFalse();
});

test('ApiClientResource vendor src/ içinde, stub publish edilmez', function (): void {
    $vendor = dirname(__DIR__, 3).'/src/Http/Resources/Admin/ApiClient/ApiClientResource.php';
    $stub = dirname(__DIR__, 3).'/stubs/app/Http/Resources/Admin/ApiClient/ApiClientResource.php';
    expect(file_exists($vendor))->toBeTrue();
    expect(file_exists($stub))->toBeFalse();
});

test('ApiTokenResource vendor src/ içinde, stub publish edilmez', function (): void {
    $vendor = dirname(__DIR__, 3).'/src/Http/Resources/Admin/ApiToken/ApiTokenResource.php';
    $stub = dirname(__DIR__, 3).'/stubs/app/Http/Resources/Admin/ApiToken/ApiTokenResource.php';
    expect(file_exists($vendor))->toBeTrue();
    expect(file_exists($stub))->toBeFalse();
});

test('ApiClientPolicy stub mevcut', function (): void {
    $path = dirname(__DIR__, 3).'/stubs/app/Policies/ApiClientPolicy.php';
    expect(file_exists($path))->toBeTrue();
});

test('ApiTokenPolicy stub mevcut', function (): void {
    $path = dirname(__DIR__, 3).'/stubs/app/Policies/ApiTokenPolicy.php';
    expect(file_exists($path))->toBeTrue();
});

test('Action dosyaları mevcut', function (): void {
    // v16.x (Faz 6): ApiClient runtime actions moved to vendor (src/Domain).
    // The HTTP layer (controller/request/resource/policy) stays app-owned;
    // consumer refs resolve through the backward-compat alias.
    $actions = [
        'CreateApiClientAction',
        'UpdateApiClientAction',
        'RevokeApiClientAction',
        'CreatePersonalAccessTokenAction',
        'RevokeApiTokenAction',
    ];

    foreach ($actions as $action) {
        $path = dirname(__DIR__, 3)."/src/Domain/ApiClient/Actions/{$action}.php";
        expect(file_exists($path))->toBeTrue("{$action} vendor runtime bulunamadı.");
    }
});

test('Route stub dosyaları mevcut', function (): void {
    $routes = [
        '/stubs/routes/web/api-client-route.php',
        '/stubs/routes/web/api-token-route.php',
    ];

    foreach ($routes as $route) {
        $path = dirname(__DIR__, 3).$route;
        expect(file_exists($path))->toBeTrue("{$route} bulunamadı.");
    }
});

// ── B) Namespace doğruluğu (vendor) ───────────────────────────────────────────

test('ApiClientResource doğru vendor namespace içeriyor', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Resources/Admin/ApiClient/ApiClientResource.php'
    );

    expect($content)->toContain('namespace Lvntr\StarterKit\Http\Resources\Admin\ApiClient');
});

test('ApiTokenResource doğru vendor namespace içeriyor', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Resources/Admin/ApiToken/ApiTokenResource.php'
    );

    expect($content)->toContain('namespace Lvntr\StarterKit\Http\Resources\Admin\ApiToken');
});

// ── C) Permission-resources.php ───────────────────────────────────────────────

test('permission-resources.php api-clients tanımlı', function (): void {
    $config = require dirname(__DIR__, 3).'/stubs/config/permission-resources.php';

    expect($config['resources'])->toHaveKey('api-clients');
    expect($config['resources']['api-clients'])->toContain('create');
    expect($config['resources']['api-clients'])->toContain('read');
    expect($config['resources']['api-clients'])->toContain('update');
    expect($config['resources']['api-clients'])->toContain('delete');
});

test('permission-resources.php api-tokens tanımlı', function (): void {
    $config = require dirname(__DIR__, 3).'/stubs/config/permission-resources.php';

    expect($config['resources'])->toHaveKey('api-tokens');
    expect($config['resources']['api-tokens'])->toContain('create');
    expect($config['resources']['api-tokens'])->toContain('read');
    expect($config['resources']['api-tokens'])->toContain('delete');
});

test("permission-resources.php 'api' permission_groups içeriyor", function (): void {
    $config = require dirname(__DIR__, 3).'/stubs/config/permission-resources.php';

    expect($config['permission_groups'])->toHaveKey('api');
    expect($config['permission_groups']['api'])->toContain('api-clients');
    expect($config['permission_groups']['api'])->toContain('api-tokens');
});

test('permission-resources.php display_names api-clients/api-tokens içeriyor', function (): void {
    $config = require dirname(__DIR__, 3).'/stubs/config/permission-resources.php';

    expect($config['display_names']['resources'])->toHaveKey('api-clients');
    expect($config['display_names']['resources'])->toHaveKey('api-tokens');
    expect($config['display_names']['resources']['api-clients'])->toHaveKey('en');
    expect($config['display_names']['resources']['api-clients'])->toHaveKey('tr');
    expect($config['display_names']['resources']['api-tokens'])->toHaveKey('en');
    expect($config['display_names']['resources']['api-tokens'])->toHaveKey('tr');
});

// ── D) Lang dosyaları ─────────────────────────────────────────────────────────

test('sk-api-clients lang dosyaları (en/tr) mevcut ve doğru yapıda', function (): void {
    foreach (['en', 'tr'] as $locale) {
        $path = dirname(__DIR__, 3)."/resources/lang/{$locale}/sk-api-clients.php";
        expect(file_exists($path))->toBeTrue("sk-api-clients.php [{$locale}] bulunamadı.");

        $lang = require $path;
        expect($lang)->toHaveKey('title');
        expect($lang)->toHaveKey('created');
        expect($lang)->toHaveKey('revoked');
        expect($lang)->toHaveKey('secret_modal');
        expect($lang['secret_modal'])->toHaveKey('title');
        expect($lang['secret_modal'])->toHaveKey('confirm');
    }
});

test('sk-api-tokens lang dosyaları (en/tr) mevcut ve doğru yapıda', function (): void {
    foreach (['en', 'tr'] as $locale) {
        $path = dirname(__DIR__, 3)."/resources/lang/{$locale}/sk-api-tokens.php";
        expect(file_exists($path))->toBeTrue("sk-api-tokens.php [{$locale}] bulunamadı.");

        $lang = require $path;
        expect($lang)->toHaveKey('title');
        expect($lang)->toHaveKey('created');
        expect($lang)->toHaveKey('revoked');
        expect($lang)->toHaveKey('token_modal');
        expect($lang['token_modal'])->toHaveKey('title');
        expect($lang['token_modal'])->toHaveKey('confirm');
    }
});

// ── E) Route dosyaları ────────────────────────────────────────────────────────

test('api-client-route.php doğru route isimleri içeriyor', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/routes/web/api-client-route.php'
    );

    expect($content)->toContain("name('api-clients.')");
    expect($content)->toContain('ApiClientController');
    expect($content)->toContain("'dtApi'");
    expect($content)->toContain("'store'");
    expect($content)->toContain("'update'");
    expect($content)->toContain("'destroy'");
});

test('api-client-route.php vendor FQCN import kullanıyor (route adı sabit)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/routes/web/api-client-route.php'
    );

    // Faz 2: import vendor FQCN'e döndü; route adı/permission DEĞİŞMEDİ.
    expect($content)->toContain('use Lvntr\StarterKit\Http\Controllers\Admin\ApiClientController;');
    expect($content)->not->toContain('use App\Http\Controllers\Admin\ApiClientController;');
});

test('api-token-route.php doğru route isimleri içeriyor', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/routes/web/api-token-route.php'
    );

    expect($content)->toContain("name('api-tokens.')");
    expect($content)->toContain('ApiTokenController');
    expect($content)->toContain("'store'");
    expect($content)->toContain("'destroy'");
});

test('api-token-route.php vendor FQCN import kullanıyor (route adı sabit)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/routes/web/api-token-route.php'
    );

    expect($content)->toContain('use Lvntr\StarterKit\Http\Controllers\Admin\ApiTokenController;');
    expect($content)->not->toContain('use App\Http\Controllers\Admin\ApiTokenController;');
});

// ── F) Security: secret plaintext tek seferlik ───────────────────────────────

test('ApiClientResource plain_secret alanı mevcut', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Resources/Admin/ApiClient/ApiClientResource.php'
    );

    expect($content)->toContain('plain_secret');
    expect($content)->toContain('plainSecret');
});

test('ApiTokenResource access_token alanı mevcut', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Resources/Admin/ApiToken/ApiTokenResource.php'
    );

    expect($content)->toContain('access_token');
    expect($content)->toContain('accessToken');
});

test('ApiClientController secret response yalnızca store() içinde', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Controllers/Admin/ApiClientController.php'
    );

    // store() dışındaki metodlarda plain_secret dönmemeli
    expect($content)->toContain('plain_secret')
        ->and($content)->not->toContain('plainSecret'); // Controller resource'u kullanır, doğrudan set etmez
});

// ── G) AppServiceProvider policy binding + StarterKitServiceProvider Passport ─

test('AppServiceProvider policy binding içeriyor, Passport kayıtları StarterKitServiceProvider\'da', function (): void {
    $appContent = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Providers/AppServiceProvider.php'
    );

    // Policy binding stub'da kalır (host-spesifik)
    expect($appContent)->toContain('ApiClientPolicy');
    expect($appContent)->toContain('ApiTokenPolicy');
    expect($appContent)->toContain('Gate::policy');

    // Gate::before ve Passport::tokensCan AppServiceProvider'dan kaldırıldı.
    // Tek kaynak StarterKitServiceProvider — duplicate kayıt silent override riski yaratır.
    expect($appContent)->not->toContain('Passport::tokensCan');

    // StarterKitServiceProvider'da Passport scope tanımları var
    $skContent = file_get_contents(
        dirname(__DIR__, 3).'/src/StarterKitServiceProvider.php'
    );

    expect($skContent)->toContain('Passport::tokensCan');
    expect($skContent)->toContain('Gate::before');
});

// ── H) Policy dosyası doğruluğu ──────────────────────────────────────────────

test('ApiTokenPolicy privilege escalation koruması içeriyor', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Policies/ApiTokenPolicy.php'
    );

    // Kullanıcı kendi token'ını revoke edebilir
    expect($content)->toContain('user_id');
    // api-tokens.delete izni olanlar başkasının token'ını silebilir
    expect($content)->toContain('api-tokens.delete');
});

test('ApiClientPolicy doğru izinleri kontrol ediyor', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Policies/ApiClientPolicy.php'
    );

    expect($content)->toContain('api-clients.read');
    expect($content)->toContain('api-clients.create');
    expect($content)->toContain('api-clients.update');
    expect($content)->toContain('api-clients.delete');
});

// ── I) Security: K1 — PAT user_id privilege escalation koruması ──────────────

test('StoreApiTokenRequest user_id alanı içermiyor (K1 privilege escalation koruması)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Requests/Admin/ApiToken/StoreApiTokenRequest.php'
    );

    expect($content)->not->toContain("'user_id'");
    expect($content)->not->toContain('"user_id"');
});

test('ApiTokenController store() sadece $request->user() kullanıyor (K1)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Controllers/Admin/ApiTokenController.php'
    );

    expect($content)->toContain('$request->user()');
    // User::findOrFail kullanılmamalı — user_id body'den alınmamalı
    expect($content)->not->toContain('User::findOrFail');
    // Store metodunda user_id kullanılmamalı; dtApi'de query scope için kullanılabilir
    expect($content)->not->toContain('validated(\'user_id\')');
});

// ── I2) Security: PAT scope allow-list (scope escalation koruması) ──────────

test('StoreApiTokenRequest scopes.* Rule::in allow-list kullanıyor', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Requests/Admin/ApiToken/StoreApiTokenRequest.php'
    );

    expect($content)->toContain('Rule::in')
        ->and($content)->toContain("config('starter-kit.passport.scopes'")
        // Passport'ta `*` hardcoded wildcard — allow-list'ten her zaman düşülmeli
        ->and($content)->toContain('array_diff')
        ->and($content)->toContain("['*']");
});

/**
 * Vendor FormRequest'i behavioral test eder: testbench app'e route'lanmadığı için
 * rules() doğrudan Validator'a verilir. Sınıf vendor PSR-4 ile autoload edilir
 * (Lvntr\StarterKit\Http\Requests\Admin\ApiToken\StoreApiTokenRequest).
 * config('starter-kit.passport.scopes') ServiceProvider merge'i ile testbench'te mevcuttur.
 */
function makeApiTokenScopeValidator(array $data): Validator
{
    $rules = (new StoreApiTokenRequest)->rules();

    return Illuminate\Support\Facades\Validator::make($data, $rules);
}

test('tanımsız scope reddedilir (422)', function (): void {
    $validator = makeApiTokenScopeValidator([
        'name' => 'test-token',
        'scopes' => ['nonexistent'],
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('scopes.0'))->toBeTrue();
});

test('config kataloğundaki scope kabul edilir', function (): void {
    $validator = makeApiTokenScopeValidator([
        'name' => 'test-token',
        'scopes' => ['files.read', 'users.read'],
    ]);

    expect($validator->passes())->toBeTrue();
});

test('* scope config kataloğuna eklenmiş olsa bile reddedilir', function (): void {
    config()->set('starter-kit.passport.scopes', [
        '*' => 'Wildcard — asla request üzerinden verilmemeli',
        'files.read' => 'Read files',
    ]);

    $validator = makeApiTokenScopeValidator([
        'name' => 'test-token',
        'scopes' => ['*'],
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('scopes.0'))->toBeTrue();
});

test('scope kataloğu boşken gönderilen her scope reddedilir', function (): void {
    config()->set('starter-kit.passport.scopes', []);

    $validator = makeApiTokenScopeValidator([
        'name' => 'test-token',
        'scopes' => ['files.read'],
    ]);

    expect($validator->fails())->toBeTrue();
});

test('scope kataloğu boşken scope gönderilmeyen istek geçer', function (): void {
    config()->set('starter-kit.passport.scopes', []);

    $validator = makeApiTokenScopeValidator(['name' => 'test-token']);

    expect($validator->passes())->toBeTrue();
});

// ── J) Security: K2 — redirect_uris HTTPS zorunluluğu ───────────────────────

test('HttpsOrLocalhostUrl Rule stub mevcut (K2)', function (): void {
    $path = dirname(__DIR__, 3).'/stubs/app/Rules/HttpsOrLocalhostUrl.php';
    expect(file_exists($path))->toBeTrue();
});

test('HttpsOrLocalhostUrl Rule localhost http kabul ediyor', function (): void {
    // Rule logic now runs from vendor (src/Rules); the stub is a thin alias.
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Rules/HttpsOrLocalhostUrl.php'
    );

    expect($content)->toContain('localhost');
    expect($content)->toContain('127.0.0.1');
    expect($content)->toContain('::1');
    expect($content)->toContain("'https'");
});

test('StoreApiClientRequest HttpsOrLocalhostUrl kullanıyor (K2)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Requests/Admin/ApiClient/StoreApiClientRequest.php'
    );

    expect($content)->toContain('HttpsOrLocalhostUrl');
    expect($content)->toContain('required_if:grant_type,authorization_code');
    expect($content)->not->toContain("'url:https,http'");
});

test('UpdateApiClientRequest HttpsOrLocalhostUrl kullanıyor (K2)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Requests/Admin/ApiClient/UpdateApiClientRequest.php'
    );

    expect($content)->toContain('HttpsOrLocalhostUrl');
    expect($content)->not->toContain("'url:https,http'");
});

// ── K) Security: K3 — confidential her zaman true ────────────────────────────

test('StoreApiClientRequest confidential alanını kabul etmiyor (K3)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Requests/Admin/ApiClient/StoreApiClientRequest.php'
    );

    expect($content)->not->toContain("'confidential'");
});

test('CreateApiClientAction confidential parametresini kaldırdı (K3)', function (): void {
    // Action logic now runs from vendor (src/Domain/ApiClient); behavior unchanged.
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Domain/ApiClient/Actions/CreateApiClientAction.php'
    );

    // confidential parametresi method signature'dan kaldırıldı
    expect($content)->not->toContain('bool $confidential');
    // createAuthorizationCodeGrantClient'ta confidential: true sabit
    expect($content)->toContain('confidential: true');
});

test('StoreApiClientRequest personal_access grant type içermiyor (O3)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Requests/Admin/ApiClient/StoreApiClientRequest.php'
    );

    expect($content)->not->toContain("'personal_access'");
});

test('ApiClientForm.vue confidential checkbox içermiyor (K3)', function (): void {
    // v13.6.0: Settings tab bileşenleri vendor'a taşındı (resources/js/pages/Admin/...).
    $content = file_get_contents(
        dirname(__DIR__, 3).'/resources/js/pages/Admin/Settings/components/ApiClientForm.vue'
    );

    expect($content)->not->toContain('ac_confidential');
    expect($content)->not->toContain('confidential:');
    expect($content)->not->toContain('personal_access');
});

// ── L) Security: K5 — kullanıcı kendi token'ını revoke edebilir ─────────────

test('ApiTokenPolicy viewAny api-tokens.create iznini de kabul ediyor (K5)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Policies/ApiTokenPolicy.php'
    );

    expect($content)->toContain('api-tokens.create');
    expect($content)->toContain('api-tokens.read');
});

test('ApiTokenController dtApi kullanıcı bazlı scope yapıyor (K5)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Controllers/Admin/ApiTokenController.php'
    );

    expect($content)->toContain('api-tokens.read');
    expect($content)->toContain('user_id');
    expect($content)->toContain('$user->id');
});

// ── M) Security: O1 — Rate limit middleware ───────────────────────────────────

test('api-client-route.php throttle middleware içeriyor (O1)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/routes/web/api-client-route.php'
    );

    expect($content)->toContain('throttle:30,1');
});

test('api-token-route.php throttle middleware içeriyor (O1)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/routes/web/api-token-route.php'
    );

    expect($content)->toContain('throttle:10,1');
    expect($content)->toContain('throttle:30,1');
});

// ── N) Security: O4 — Cache-Control: no-store secret response'larda ──────────

test('ApiTokenController store() Cache-Control no-store header ekliyor (O4)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Controllers/Admin/ApiTokenController.php'
    );

    expect($content)->toContain('Cache-Control');
    expect($content)->toContain('no-store');
});

test('ApiClientController store() Cache-Control no-store header ekliyor (O4)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Controllers/Admin/ApiClientController.php'
    );

    expect($content)->toContain('Cache-Control');
    expect($content)->toContain('no-store');
});
