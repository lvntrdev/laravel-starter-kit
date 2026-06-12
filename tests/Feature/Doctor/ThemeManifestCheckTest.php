<?php

/*
|--------------------------------------------------------------------------
| ThemeManifestCheck Testleri
|--------------------------------------------------------------------------
|
| ThemeManifestCheck::run() base_path()'e bağlı olduğundan, gerçek mantığı
| (theme.css → _active.css import tespiti + _active.css varlığı) yalın bir
| port üzerinden doğrularız. Aynı dosya-sistemi kuralları test edilir.
*/

use Illuminate\Support\Facades\Artisan;
use Lvntr\StarterKit\Console\Doctor\Checks\ThemeManifestCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

/**
 * ThemeManifestCheck'in base_path() bağımlılığını test edilebilir bir
 * tema dizini ile değiştiren alt sınıf.
 */
function makeThemeManifestCheck(string $themeDir): ThemeManifestCheck
{
    return new class($themeDir) extends ThemeManifestCheck
    {
        public function __construct(private string $themeDir) {}

        public function run(): DoctorReport
        {
            $themeEntryPath = $this->themeDir.'/theme.css';

            if (! file_exists($themeEntryPath) || ! str_contains((string) file_get_contents($themeEntryPath), '_active.css')) {
                return DoctorReport::ok(
                    $this->name(),
                    'Theme resolver not in use (no _active.css import) — check skipped.'
                );
            }

            $activeManifestPath = $this->themeDir.'/_active.css';

            if (! file_exists($activeManifestPath)) {
                return DoctorReport::fail(
                    $this->name(),
                    'resources/css/theme/_active.css is missing (theme resolver output).',
                    'Run npm run theme:build or npm run build.'
                );
            }

            // İçerik denetimi üretim sınıfıyla AYNI protected metod üzerinden —
            // mantık burada kopyalanmaz (yalnızca base_path() yerine inject edilen
            // themeDir kullanılır).
            return $this->inspectManifest($activeManifestPath);
        }
    };
}

function makeThemeDir(): string
{
    $dir = sys_get_temp_dir().'/sk_doctor_theme_'.uniqid();
    mkdir($dir, 0755, true);

    return $dir;
}

test('theme.css _active.css import ediyor ama _active.css yoksa fail döner', function () {
    $dir = makeThemeDir();
    file_put_contents($dir.'/theme.css', "@import './_active.css';\n@import './_auth.scss';\n");
    // _active.css bilinçli olarak oluşturulmadı.

    $report = makeThemeManifestCheck($dir)->run();

    expect($report->status)->toBe(DoctorStatus::Fail)
        ->and($report->message)->toContain('_active.css')
        ->and($report->hint)->toContain('npm run');

    unlink($dir.'/theme.css');
    rmdir($dir);
});

test('theme.css _active.css import ediyor ve _active.css varsa ok döner', function () {
    $dir = makeThemeDir();
    file_put_contents($dir.'/theme.css', "@import './_active.css';\n@import './_auth.scss';\n");
    file_put_contents($dir.'/_active.css', "@import './main/tokens.css';\n");

    $report = makeThemeManifestCheck($dir)->run();

    expect($report->status)->toBe(DoctorStatus::Ok)
        ->and($report->message)->toContain('present');

    unlink($dir.'/theme.css');
    unlink($dir.'/_active.css');
    rmdir($dir);
});

test('_active.css tema kökü dışına çıkan (../) import içeriyorsa warn döner', function () {
    $dir = makeThemeDir();
    file_put_contents($dir.'/theme.css', "@import './_active.css';\n");
    // Traversal artefaktı: tema dizininden çıkan bir @import.
    file_put_contents($dir.'/_active.css', "@import './../../../outside/tokens.css';\n");

    $report = makeThemeManifestCheck($dir)->run();

    expect($report->status)->toBe(DoctorStatus::Warn)
        ->and($report->message)->toContain('escapes the theme directory');

    unlink($dir.'/theme.css');
    unlink($dir.'/_active.css');
    rmdir($dir);
});

test('theme.css _active.css import etmiyorsa (eski consumer) ok döner', function () {
    $dir = makeThemeDir();
    // Tema-resolver öncesi consumer: doğrudan partial import eden theme.css.
    file_put_contents($dir.'/theme.css', "@import './_base.scss';\n@import './_admin.scss';\n");

    $report = makeThemeManifestCheck($dir)->run();

    expect($report->status)->toBe(DoctorStatus::Ok)
        ->and($report->message)->toContain('skipped');

    unlink($dir.'/theme.css');
    rmdir($dir);
});

test('theme.css hiç yoksa ok döner (uygulanmaz)', function () {
    $dir = makeThemeDir();
    // theme.css yok.

    $report = makeThemeManifestCheck($dir)->run();

    expect($report->status)->toBe(DoctorStatus::Ok)
        ->and($report->message)->toContain('skipped');

    rmdir($dir);
});

test('report name doğru', function () {
    $check = new ThemeManifestCheck;

    expect($check->name())->toBe('Theme Manifest');
});

test('sk:doctor --only=theme-manifest sadece bu check\'i çalıştırır', function () {
    Artisan::call('sk:doctor', ['--json' => true, '--only' => 'theme-manifest']);
    $decoded = json_decode(Artisan::output(), true);

    expect($decoded['checks'])->toHaveCount(1)
        ->and($decoded['checks'][0]['name'])->toBe('Theme Manifest');
});

test('sk:doctor (filtresiz) Theme Manifest check\'ini içerir', function () {
    Artisan::call('sk:doctor', ['--json' => true]);
    $decoded = json_decode(Artisan::output(), true);

    $names = array_column($decoded['checks'], 'name');

    expect($names)->toContain('Theme Manifest');
});
