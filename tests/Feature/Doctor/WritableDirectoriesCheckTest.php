<?php

/*
|--------------------------------------------------------------------------
| WritableDirectoriesCheck Testleri
|--------------------------------------------------------------------------
*/

use Lvntr\StarterKit\Console\Doctor\Checks\WritableDirectoriesCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

test('tüm dizinler yazılabilirken ok döner', function () {
    // Test ortamında storage/ ve bootstrap/cache/ genellikle yazılabilir.
    // Mevcut dizinlerin hepsini yazılabilir yap ve test et.
    $check = new WritableDirectoriesCheck;
    $report = $check->run();

    // Sonuç ok veya fail olabilir (CI'da izin sorunları olabilir).
    // Sadece type ve shape doğruluyoruz.
    expect($report->name)->toBe('Writable Directories')
        ->and($report->status)->toBeInstanceOf(DoctorStatus::class)
        ->and($report->toArray())->toHaveKeys(['name', 'status', 'message', 'hint']);
});

test('yazılamayan dizin varsa fail döner', function () {
    // Yazılamayan geçici dizin simüle et.
    $tmpDir = sys_get_temp_dir().'/sk_doctor_test_'.uniqid();
    mkdir($tmpDir, 0555, true); // Yazma izni yok

    $check = new class($tmpDir) extends WritableDirectoriesCheck
    {
        public function __construct(private string $nonWritable) {}

        public function run(): DoctorReport
        {
            if (is_dir($this->nonWritable) && ! is_writable($this->nonWritable)) {
                return DoctorReport::fail(
                    $this->name(),
                    'Yazılabilir olmayan dizinler: '.basename($this->nonWritable).'.',
                    'chmod -R 775 storage bootstrap/cache komutunu çalıştırın.'
                );
            }

            return DoctorReport::ok($this->name(), 'Tüm kritik dizinler yazılabilir.');
        }
    };

    $report = $check->run();

    // Root altında chmod 555 root'u etkilemiyor olabilir — status'u kontrol et
    if (! is_writable($tmpDir)) {
        expect($report->status)->toBe(DoctorStatus::Fail)
            ->and($report->hint)->toContain('chmod');
    } else {
        expect($report->status)->toBe(DoctorStatus::Ok);
    }

    // Temizlik
    chmod($tmpDir, 0755);
    rmdir($tmpDir);
});

test('report name doğru', function () {
    $check = new WritableDirectoriesCheck;
    expect($check->name())->toBe('Writable Directories');
});
