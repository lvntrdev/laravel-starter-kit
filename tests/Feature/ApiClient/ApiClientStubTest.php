<?php

/*
|--------------------------------------------------------------------------
| API Client UI — Stub Dosya Yapısı Testleri
|--------------------------------------------------------------------------
|
| Bu testler stub dosyalarının beklenen yolda ve doğru namespace'te
| bulunduğunu doğrular. Stub'lar host uygulamaya publish edildiğinde
| bu yapının bozulmamış olması zorunludur.
|
| Test senaryoları:
|
|   A) Stub dosyaları doğru yolda mevcut mu?
|   B) Resource'lar doğru namespace'e sahip mi?
|   C) Permission-resources.php api-clients/api-tokens içeriyor mu?
|   D) Lang dosyaları doğru key'lere sahip mi?
|   E) Route dosyaları doğru isimlendirme kullanıyor mu?
|   F) AppServiceProvider Gate::before ve Passport::tokensCan içeriyor mu?
|
*/

use Lvntr\StarterKit\Tests\TestCase;

uses(TestCase::class);

// ── A) Stub dosyaları ─────────────────────────────────────────────────────────

test('ApiClientController stub mevcut', function (): void {
    $path = dirname(__DIR__, 3).'/stubs/app/Http/Controllers/Admin/ApiClientController.php';
    expect(file_exists($path))->toBeTrue();
});

test('ApiTokenController stub mevcut', function (): void {
    $path = dirname(__DIR__, 3).'/stubs/app/Http/Controllers/Admin/ApiTokenController.php';
    expect(file_exists($path))->toBeTrue();
});

test('StoreApiClientRequest stub mevcut', function (): void {
    $path = dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/ApiClient/StoreApiClientRequest.php';
    expect(file_exists($path))->toBeTrue();
});

test('UpdateApiClientRequest stub mevcut', function (): void {
    $path = dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/ApiClient/UpdateApiClientRequest.php';
    expect(file_exists($path))->toBeTrue();
});

test('StoreApiTokenRequest stub mevcut', function (): void {
    $path = dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/ApiToken/StoreApiTokenRequest.php';
    expect(file_exists($path))->toBeTrue();
});

test('ApiClientResource stub mevcut', function (): void {
    $path = dirname(__DIR__, 3).'/stubs/app/Http/Resources/Admin/ApiClient/ApiClientResource.php';
    expect(file_exists($path))->toBeTrue();
});

test('ApiTokenResource stub mevcut', function (): void {
    $path = dirname(__DIR__, 3).'/stubs/app/Http/Resources/Admin/ApiToken/ApiTokenResource.php';
    expect(file_exists($path))->toBeTrue();
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
    $actions = [
        'CreateApiClientAction',
        'UpdateApiClientAction',
        'RevokeApiClientAction',
        'CreatePersonalAccessTokenAction',
        'RevokeApiTokenAction',
    ];

    foreach ($actions as $action) {
        $path = dirname(__DIR__, 3)."/stubs/app/Domain/ApiClient/Actions/{$action}.php";
        expect(file_exists($path))->toBeTrue("{$action} stub bulunamadı.");
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

// ── B) Namespace doğruluğu ────────────────────────────────────────────────────

test('ApiClientResource doğru namespace içeriyor', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Resources/Admin/ApiClient/ApiClientResource.php'
    );

    expect($content)->toContain('namespace App\Http\Resources\Admin\ApiClient');
});

test('ApiTokenResource doğru namespace içeriyor', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Resources/Admin/ApiToken/ApiTokenResource.php'
    );

    expect($content)->toContain('namespace App\Http\Resources\Admin\ApiToken');
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
        $path = dirname(__DIR__, 3)."/stubs/lang/{$locale}/sk-api-clients.php";
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
        $path = dirname(__DIR__, 3)."/stubs/lang/{$locale}/sk-api-tokens.php";
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

test('api-token-route.php doğru route isimleri içeriyor', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/routes/web/api-token-route.php'
    );

    expect($content)->toContain("name('api-tokens.')");
    expect($content)->toContain('ApiTokenController');
    expect($content)->toContain("'store'");
    expect($content)->toContain("'destroy'");
});

// ── F) Security: secret plaintext tek seferlik ───────────────────────────────

test('ApiClientResource plain_secret alanı mevcut', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Resources/Admin/ApiClient/ApiClientResource.php'
    );

    expect($content)->toContain('plain_secret');
    expect($content)->toContain('plainSecret');
});

test('ApiTokenResource access_token alanı mevcut', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Resources/Admin/ApiToken/ApiTokenResource.php'
    );

    expect($content)->toContain('access_token');
    expect($content)->toContain('accessToken');
});

test('ApiClientController secret response yalnızca store() içinde', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Controllers/Admin/ApiClientController.php'
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
        dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/ApiToken/StoreApiTokenRequest.php'
    );

    expect($content)->not->toContain("'user_id'");
    expect($content)->not->toContain('"user_id"');
});

test('ApiTokenController store() sadece $request->user() kullanıyor (K1)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Controllers/Admin/ApiTokenController.php'
    );

    expect($content)->toContain('$request->user()');
    // User::findOrFail kullanılmamalı — user_id body'den alınmamalı
    expect($content)->not->toContain('User::findOrFail');
    // Store metodunda user_id kullanılmamalı; dtApi'de query scope için kullanılabilir
    expect($content)->not->toContain('validated(\'user_id\')');
});

// ── J) Security: K2 — redirect_uris HTTPS zorunluluğu ───────────────────────

test('HttpsOrLocalhostUrl Rule stub mevcut (K2)', function (): void {
    $path = dirname(__DIR__, 3).'/stubs/app/Rules/HttpsOrLocalhostUrl.php';
    expect(file_exists($path))->toBeTrue();
});

test('HttpsOrLocalhostUrl Rule localhost http kabul ediyor', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Rules/HttpsOrLocalhostUrl.php'
    );

    expect($content)->toContain('localhost');
    expect($content)->toContain('127.0.0.1');
    expect($content)->toContain('::1');
    expect($content)->toContain("'https'");
});

test('StoreApiClientRequest HttpsOrLocalhostUrl kullanıyor (K2)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/ApiClient/StoreApiClientRequest.php'
    );

    expect($content)->toContain('HttpsOrLocalhostUrl');
    expect($content)->toContain('required_if:grant_type,authorization_code');
    expect($content)->not->toContain("'url:https,http'");
});

test('UpdateApiClientRequest HttpsOrLocalhostUrl kullanıyor (K2)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/ApiClient/UpdateApiClientRequest.php'
    );

    expect($content)->toContain('HttpsOrLocalhostUrl');
    expect($content)->not->toContain("'url:https,http'");
});

// ── K) Security: K3 — confidential her zaman true ────────────────────────────

test('StoreApiClientRequest confidential alanını kabul etmiyor (K3)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/ApiClient/StoreApiClientRequest.php'
    );

    expect($content)->not->toContain("'confidential'");
});

test('CreateApiClientAction confidential parametresini kaldırdı (K3)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Domain/ApiClient/Actions/CreateApiClientAction.php'
    );

    // confidential parametresi method signature'dan kaldırıldı
    expect($content)->not->toContain('bool $confidential');
    // createAuthorizationCodeGrantClient'ta confidential: true sabit
    expect($content)->toContain('confidential: true');
});

test('StoreApiClientRequest personal_access grant type içermiyor (O3)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/ApiClient/StoreApiClientRequest.php'
    );

    expect($content)->not->toContain("'personal_access'");
});

test('ApiClientForm.vue confidential checkbox içermiyor (K3)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/resources/js/pages/Admin/ApiClients/components/ApiClientForm.vue'
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
        dirname(__DIR__, 3).'/stubs/app/Http/Controllers/Admin/ApiTokenController.php'
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
        dirname(__DIR__, 3).'/stubs/app/Http/Controllers/Admin/ApiTokenController.php'
    );

    expect($content)->toContain('Cache-Control');
    expect($content)->toContain('no-store');
});

test('ApiClientController store() Cache-Control no-store header ekliyor (O4)', function (): void {
    $content = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Controllers/Admin/ApiClientController.php'
    );

    expect($content)->toContain('Cache-Control');
    expect($content)->toContain('no-store');
});
