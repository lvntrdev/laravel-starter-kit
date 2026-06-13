<?php

/*
|--------------------------------------------------------------------------
| SystemHealth — Controller + stub doğrulama testleri
|--------------------------------------------------------------------------
|
| v13.6.0 Faz 2: SystemHealthController vendor-first (src/Http/Controllers/
| Admin/SystemHealthController.php, namespace Lvntr\StarterKit\...). Publish
| edilmez; eski consumer'ların App\ referansı backward-compat alias ile çözülür.
| Route/lang/permission/Vue katmanları app-owned kaldığı için stub'tan okunur.
|
| Bu testler doğrular:
|   1. Controller — vendor'da, namespace doğru, run/Gate/Artisan mantığı korunmuş
|   2. Route stub — doğru prefix, route isimleri, vendor FQCN import
|   3. Lang stub — zorunlu anahtar varlığı (en ve tr)
|   4. Vue sayfa stub — Props, router.post, status badge referansları
|   5. Permission config — system.health.view custom_permissions içinde
|   6. Menu composable — system.health.view permission gate referansı
|
*/

// ──────────────────────────────────────────────────────────────────────────────
// 1. Controller (vendor-first)
// ──────────────────────────────────────────────────────────────────────────────

it('SystemHealthController vendor src/ içinde, stub publish edilmez', function (): void {
    $vendorPath = dirname(__DIR__, 3)
        .'/src/Http/Controllers/Admin/SystemHealthController.php';
    $stubPath = dirname(__DIR__, 3)
        .'/stubs/app/Http/Controllers/Admin/SystemHealthController.php';

    expect(is_file($vendorPath))->toBeTrue("Vendor controller bulunamadı: {$vendorPath}");
    expect(is_file($stubPath))->toBeFalse(
        "SystemHealthController hala stub'ta — Faz 2'de vendor'a taşınmalıydı: {$stubPath}"
    );
});

it('SystemHealthController vendor namespace içeriyor', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Controllers/Admin/SystemHealthController.php'
    );

    expect($contents)->toContain('namespace Lvntr\StarterKit\Http\Controllers\Admin;');
});

it('SystemHealthController run metodunu içeriyor', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Controllers/Admin/SystemHealthController.php'
    );

    expect($contents)
        ->toContain('public function run()')
        ->toContain('Artisan::call')
        ->toContain('sk:doctor')
        ->toContain('--json');
});

it('SystemHealthController Gate::authorize system.health.view kullanıyor', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Controllers/Admin/SystemHealthController.php'
    );

    expect($contents)->toContain("Gate::authorize('system.health.view')");
});

it('SystemHealthController run metodu RedirectResponse döndürüyor', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/src/Http/Controllers/Admin/SystemHealthController.php'
    );

    expect($contents)
        ->toContain('RedirectResponse')
        ->toContain('redirect()->route(');
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. Route stub
// ──────────────────────────────────────────────────────────────────────────────

it('system-health-route stub mevcut', function (): void {
    $path = dirname(__DIR__, 3).'/stubs/routes/web/system-health-route.php';

    expect(is_file($path))->toBeTrue("Stub bulunamadı: {$path}");
});

it('system-health-route stub doğru prefix ve route isimlerini içeriyor', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/stubs/routes/web/system-health-route.php'
    );

    expect($contents)
        ->toContain("prefix('system-health')")
        ->toContain("name('system-health.')")
        ->toContain("name('run')")
        ->toContain('SystemHealthController');
});

it('system-health-route stub vendor FQCN import kullanıyor (route adı sabit)', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/stubs/routes/web/system-health-route.php'
    );

    // Faz 2: import vendor FQCN'e döndü; route adı/prefix DEĞİŞMEDİ (permission haritası korunur).
    expect($contents)->toContain('use Lvntr\StarterKit\Http\Controllers\Admin\SystemHealthController;');
    expect($contents)->not->toContain('use App\Http\Controllers\Admin\SystemHealthController;');
});

it('system-health-route stub POST run route içeriyor', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/stubs/routes/web/system-health-route.php'
    );

    expect($contents)->toContain('Route::post(');
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. Lang dosyaları
// ──────────────────────────────────────────────────────────────────────────────

it('sk-system-health İngilizce lang stub mevcut', function (): void {
    $path = dirname(__DIR__, 3).'/resources/lang/en/sk-system-health.php';

    expect(is_file($path))->toBeTrue("Stub bulunamadı: {$path}");
});

it('sk-system-health Türkçe lang stub mevcut', function (): void {
    $path = dirname(__DIR__, 3).'/resources/lang/tr/sk-system-health.php';

    expect(is_file($path))->toBeTrue("Stub bulunamadı: {$path}");
});

it('İngilizce lang stub zorunlu anahtarları içeriyor', function (): void {
    $en = require dirname(__DIR__, 3).'/resources/lang/en/sk-system-health.php';

    $requiredKeys = [
        'title', 'subtitle',
        'status_ok', 'status_warn', 'status_fail',
        'run_button', 'running',
        'run_ok', 'run_warn', 'run_fail',
        'col_check', 'col_status', 'col_message', 'col_hint',
        'no_results', 'load_error', 'generated_at',
    ];

    foreach ($requiredKeys as $key) {
        expect(array_key_exists($key, $en))->toBeTrue("İngilizce lang dosyasında '{$key}' anahtarı eksik");
    }
});

it('Türkçe lang stub zorunlu anahtarları içeriyor', function (): void {
    $tr = require dirname(__DIR__, 3).'/resources/lang/tr/sk-system-health.php';

    $requiredKeys = [
        'title', 'subtitle',
        'status_ok', 'status_warn', 'status_fail',
        'run_button', 'running',
        'run_ok', 'run_warn', 'run_fail',
        'col_check', 'col_status', 'col_message', 'col_hint',
        'no_results', 'load_error', 'generated_at',
    ];

    foreach ($requiredKeys as $key) {
        expect(array_key_exists($key, $tr))->toBeTrue("Türkçe lang dosyasında '{$key}' anahtarı eksik");
    }
});

it('Türkçe ve İngilizce lang dosyaları aynı anahtar setine sahip', function (): void {
    $en = require dirname(__DIR__, 3).'/resources/lang/en/sk-system-health.php';
    $tr = require dirname(__DIR__, 3).'/resources/lang/tr/sk-system-health.php';

    expect(array_keys($en))->toBe(array_keys($tr));
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. SystemHealthTab bileşeni
// ──────────────────────────────────────────────────────────────────────────────

it('SystemHealthTab.vue stub mevcut', function (): void {
    $path = dirname(__DIR__, 3)
        .'/resources/js/pages/Admin/Settings/components/SystemHealthTab.vue';

    expect(is_file($path))->toBeTrue("Stub bulunamadı: {$path}");
});

it('SystemHealthTab.vue stub DoctorReport interface içeriyor', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/resources/js/pages/Admin/Settings/components/SystemHealthTab.vue'
    );

    expect($contents)
        ->toContain('interface DoctorCheck')
        ->toContain('interface DoctorSummary')
        ->toContain('interface DoctorReport')
        ->toContain("status: 'ok' | 'warn' | 'fail'");
});

it('SystemHealthTab.vue stub system-health.run çağrısı yapıyor', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/resources/js/pages/Admin/Settings/components/SystemHealthTab.vue'
    );

    expect($contents)->toContain('system-health.run');
});

it('SystemHealthTab.vue stub status badge stilleri tanımlı', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/resources/js/pages/Admin/Settings/components/SystemHealthTab.vue'
    );

    expect($contents)
        ->toContain('ok:')
        ->toContain('warn:')
        ->toContain('fail:');
});

it('SystemHealthTab.vue stub text-sm kullanmıyor (kit kuralı: text-base minimum)', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/resources/js/pages/Admin/Settings/components/SystemHealthTab.vue'
    );

    expect($contents)->not->toContain('text-sm');
});

// ──────────────────────────────────────────────────────────────────────────────
// 5. Permission config
// ──────────────────────────────────────────────────────────────────────────────

it('permission-resources config system.health.view custom_permissions içinde', function (): void {
    $config = require dirname(__DIR__, 3).'/stubs/config/permission-resources.php';

    expect($config['custom_permissions'])->toContain('system.health.view');
});

// ──────────────────────────────────────────────────────────────────────────────
// 6. SystemHealthTab route referansı
// ──────────────────────────────────────────────────────────────────────────────

it('SystemHealthTab.vue system-health.run.url() kullanıyor', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/resources/js/pages/Admin/Settings/components/SystemHealthTab.vue'
    );

    expect($contents)
        ->toContain("import systemHealth from '@/routes/system-health'")
        ->toContain('systemHealth.run.url()');
});
