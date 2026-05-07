<?php

/*
|--------------------------------------------------------------------------
| PassportKeysCheck Testleri
|--------------------------------------------------------------------------
*/

use Lvntr\StarterKit\Console\Doctor\Checks\PassportKeysCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

test('Passport kurulu değilse warn döner', function () {
    // Test ortamında Passport class'ı yoksa warn beklenir.
    if (class_exists('Laravel\Passport\Passport')) {
        $this->markTestSkipped('Passport kurulu — bu test atlandı.');
    }

    $check = new PassportKeysCheck;
    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Warn)
        ->and($report->message)->toContain('kurulu değil');
});

test('anahtar dosyaları eksikse fail döner', function () {
    if (! class_exists('Laravel\Passport\Passport')) {
        $this->markTestSkipped('Passport kurulu değil.');
    }

    // storage_path() olmayan bir dizine yönlendir
    $check = new class extends PassportKeysCheck
    {
        public function run(): DoctorReport
        {
            // Var olmayan yola bak
            $fakeKey = sys_get_temp_dir().'/nonexistent_oauth_private_'.uniqid().'.key';

            if (! file_exists($fakeKey)) {
                return DoctorReport::fail(
                    $this->name(),
                    'Eksik Passport anahtar dosyası: '.basename($fakeKey).'.',
                    'php artisan passport:keys komutunu çalıştırın.'
                );
            }

            return DoctorReport::ok($this->name(), 'Anahtarlar mevcut.');
        }
    };

    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Fail)
        ->and($report->hint)->toContain('passport:keys');
});

test('anahtar dosyaları mevcutsa ok döner', function () {
    if (! class_exists('Laravel\Passport\Passport')) {
        $this->markTestSkipped('Passport kurulu değil.');
    }

    // Geçici anahtar dosyaları oluştur
    $tmpDir = sys_get_temp_dir();
    $privateKey = $tmpDir.'/oauth-private.key';
    $publicKey = $tmpDir.'/oauth-public.key';

    file_put_contents($privateKey, 'FAKE_PRIVATE_KEY');
    file_put_contents($publicKey, 'FAKE_PUBLIC_KEY');

    $check = new class($privateKey, $publicKey) extends PassportKeysCheck
    {
        public function __construct(
            private string $privateKeyPath,
            private string $publicKeyPath,
        ) {}

        public function run(): DoctorReport
        {
            foreach ([$this->privateKeyPath, $this->publicKeyPath] as $keyPath) {
                if (! file_exists($keyPath)) {
                    return DoctorReport::fail(
                        $this->name(),
                        'Eksik anahtar: '.basename($keyPath).'.',
                        'php artisan passport:keys komutunu çalıştırın.'
                    );
                }
            }

            return DoctorReport::ok(
                $this->name(),
                'Passport anahtar dosyaları mevcut ve okunabilir.'
            );
        }
    };

    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Ok)
        ->and($report->message)->toContain('mevcut ve okunabilir');

    // Temizlik
    @unlink($privateKey);
    @unlink($publicKey);
});

test('report name doğru', function () {
    $check = new PassportKeysCheck;
    expect($check->name())->toBe('Passport Keys');
});
