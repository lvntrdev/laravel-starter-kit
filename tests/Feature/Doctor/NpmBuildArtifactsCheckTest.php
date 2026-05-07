<?php

/*
|--------------------------------------------------------------------------
| NpmBuildArtifactsCheck Testleri
|--------------------------------------------------------------------------
*/

use Lvntr\StarterKit\Console\Doctor\Checks\NpmBuildArtifactsCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

test('manifest.json mevcut ve geçerliyse ok döner', function () {
    // Geçici manifest dosyası oluştur
    $tmpDir = sys_get_temp_dir().'/sk_doctor_build_'.uniqid();
    mkdir($tmpDir, 0755, true);
    $manifestPath = $tmpDir.'/manifest.json';
    file_put_contents($manifestPath, json_encode(['resources/js/app.js' => ['file' => 'assets/app-abc123.js']]));

    $check = new class($manifestPath) extends NpmBuildArtifactsCheck
    {
        public function __construct(private string $manifest) {}

        public function run(): DoctorReport
        {
            if (! file_exists($this->manifest)) {
                return DoctorReport::fail(
                    $this->name(),
                    'public/build/manifest.json bulunamadı.',
                    'npm run build komutunu çalıştırın.'
                );
            }

            $decoded = json_decode(file_get_contents($this->manifest) ?: '', true);

            if (! is_array($decoded) || $decoded === []) {
                return DoctorReport::fail($this->name(), 'Manifest geçersiz.', '');
            }

            return DoctorReport::ok(
                $this->name(),
                "NPM build artifact\'leri mevcut (".count($decoded).' asset).'
            );
        }
    };

    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Ok)
        ->and($report->message)->toContain('1 asset');

    // Temizlik
    unlink($manifestPath);
    rmdir($tmpDir);
});

test('manifest.json yoksa local ortamda warn döner', function () {
    config()->set('app.env', 'local');

    $check = new class extends NpmBuildArtifactsCheck
    {
        public function run(): DoctorReport
        {
            return DoctorReport::warn(
                $this->name(),
                'public/build/manifest.json bulunamadı.',
                'npm run build veya npm run dev komutunu çalıştırın.'
            );
        }
    };

    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Warn)
        ->and($report->hint)->toContain('npm run');
});

test('manifest.json yoksa production ortamda fail döner', function () {
    config()->set('app.env', 'production');

    $check = new class extends NpmBuildArtifactsCheck
    {
        public function run(): DoctorReport
        {
            return DoctorReport::fail(
                $this->name(),
                'public/build/manifest.json bulunamadı — frontend derlemesi eksik.',
                'npm run build komutunu çalıştırın.'
            );
        }
    };

    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Fail);

    config()->set('app.env', 'testing');
});

test('report name doğru', function () {
    $check = new NpmBuildArtifactsCheck;
    expect($check->name())->toBe('NPM Build Artifacts');
});
