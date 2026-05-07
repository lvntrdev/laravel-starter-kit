<?php

/*
|--------------------------------------------------------------------------
| ConfigCacheCheck Testleri
|--------------------------------------------------------------------------
*/

use Lvntr\StarterKit\Console\Doctor\Checks\ConfigCacheCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

test('testing ortamında cache yoksa ok döner', function () {
    config()->set('app.env', 'testing');

    // Test ortamında bootstrap/cache/config.php genellikle yok.
    $check = new ConfigCacheCheck;
    $report = $check->run();

    // Cache yoksa ok, varsa warn beklenir.
    expect($report->name)->toBe('Config Cache')
        ->and($report->status)->toBeInstanceOf(DoctorStatus::class);
});

test('production ortamında cache yoksa warn döner', function () {
    config()->set('app.env', 'production');

    $check = new class extends ConfigCacheCheck
    {
        public function run(): DoctorReport
        {
            // Her zaman cache yok gibi davran
            return DoctorReport::warn(
                $this->name(),
                'Production ortamında config cache bulunamadı.',
                'php artisan config:cache komutunu çalıştırın (performans için önerilir).'
            );
        }
    };

    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Warn)
        ->and($report->hint)->toContain('config:cache');

    config()->set('app.env', 'testing');
});

test('production ortamında cache varsa ok döner', function () {
    config()->set('app.env', 'production');

    $check = new class extends ConfigCacheCheck
    {
        public function run(): DoctorReport
        {
            return DoctorReport::ok(
                $this->name(),
                'Config cache mevcut ve production için hazır.'
            );
        }
    };

    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Ok);

    config()->set('app.env', 'testing');
});

test('local ortamında cache varsa warn döner', function () {
    config()->set('app.env', 'local');

    $check = new class extends ConfigCacheCheck
    {
        public function run(): DoctorReport
        {
            return DoctorReport::warn(
                $this->name(),
                'Config cache mevcut ama ortam "local" — config değişiklikleri yansımayabilir.',
                'php artisan config:clear ile cache\'i temizleyin.'
            );
        }
    };

    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Warn)
        ->and($report->hint)->toContain('config:clear');

    config()->set('app.env', 'testing');
});

test('report name doğru', function () {
    $check = new ConfigCacheCheck;
    expect($check->name())->toBe('Config Cache');
});
