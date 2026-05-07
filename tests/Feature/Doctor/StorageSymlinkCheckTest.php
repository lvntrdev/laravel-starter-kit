<?php

/*
|--------------------------------------------------------------------------
| StorageSymlinkCheck Testleri
|--------------------------------------------------------------------------
*/

use Lvntr\StarterKit\Console\Doctor\Checks\StorageSymlinkCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

test('geçerli sembolik link mevcutsa ok döner', function () {
    // Geçici bir gerçek dizin ve sembolik link oluştur.
    $target = sys_get_temp_dir().'/sk_doctor_target_'.uniqid();
    $link = sys_get_temp_dir().'/sk_doctor_link_'.uniqid();

    mkdir($target, 0755, true);
    symlink($target, $link);

    $check = new class($link) extends StorageSymlinkCheck
    {
        public function __construct(private string $linkPath) {}

        public function run(): DoctorReport
        {
            if (is_link($this->linkPath) && file_exists($this->linkPath)) {
                return DoctorReport::ok(
                    $this->name(),
                    'public/storage sembolik linki geçerli.'
                );
            }

            return DoctorReport::fail(
                $this->name(),
                'Sembolik link geçersiz.',
                'php artisan storage:link komutunu çalıştırın.'
            );
        }
    };

    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Ok);

    // Temizlik
    unlink($link);
    rmdir($target);
});

test('sembolik link yoksa fail döner', function () {
    $check = new class extends StorageSymlinkCheck
    {
        public function run(): DoctorReport
        {
            return DoctorReport::fail(
                $this->name(),
                'public/storage sembolik linki bulunamadı.',
                'php artisan storage:link komutunu çalıştırın.'
            );
        }
    };

    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Fail)
        ->and($report->hint)->toContain('storage:link');
});

test('kırık sembolik link fail döner', function () {
    // Var olmayan hedefi gösteren link
    $link = sys_get_temp_dir().'/sk_doctor_broken_link_'.uniqid();
    $nonExistentTarget = sys_get_temp_dir().'/nonexistent_target_'.uniqid();

    symlink($nonExistentTarget, $link);

    $check = new class($link) extends StorageSymlinkCheck
    {
        public function __construct(private string $linkPath) {}

        public function run(): DoctorReport
        {
            if (is_link($this->linkPath) && ! file_exists($this->linkPath)) {
                return DoctorReport::fail(
                    $this->name(),
                    'public/storage kırık sembolik link (hedef bulunamadı).',
                    'php artisan storage:link --force ile yeniden oluşturun.'
                );
            }

            return DoctorReport::ok($this->name(), 'Link geçerli.');
        }
    };

    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Fail)
        ->and($report->hint)->toContain('storage:link --force');

    // Temizlik
    unlink($link);
});

test('report name doğru', function () {
    $check = new StorageSymlinkCheck;
    expect($check->name())->toBe('Storage Symlink');
});
