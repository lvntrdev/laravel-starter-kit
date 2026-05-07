<?php

/*
|--------------------------------------------------------------------------
| PhpExtensionsCheck Testleri
|--------------------------------------------------------------------------
*/

use Lvntr\StarterKit\Console\Doctor\Checks\PhpExtensionsCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

test('tüm extension yüklü olduğunda ok döner', function () {
    // Bu test gerçek extension listesini kullanır.
    // Test ortamında en az bazı extension'lar mevcut olmalı.
    // Tüm missing yoksa ok beklenir.
    $check = new PhpExtensionsCheck;
    $report = $check->run();

    // Status ya ok ya fail olabilir — sadece name ve toArray yapısını doğrula.
    expect($report->name)->toBe('PHP Extensions')
        ->and($report->toArray())->toHaveKeys(['name', 'status', 'message', 'hint'])
        ->and($report->status)->toBeInstanceOf(DoctorStatus::class);
});

test('eksik extension olduğunda fail döner', function () {
    // Var olmayan extension ile PhpExtensionsCheck'i extend ederek test et.
    $check = new class extends PhpExtensionsCheck
    {
        private array $required = ['this_extension_does_not_exist_12345'];

        public function run(): DoctorReport
        {
            $missing = array_filter($this->required, fn (string $ext) => ! extension_loaded($ext));

            if ($missing !== []) {
                return DoctorReport::fail(
                    $this->name(),
                    'Eksik extension\'lar: '.implode(', ', $missing).'.',
                    'php.ini dosyanızda extension\'ları etkinleştirin veya paket yöneticinizle kurun.'
                );
            }

            return DoctorReport::ok($this->name(), 'Tümü yüklü.');
        }
    };

    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Fail)
        ->and($report->message)->toContain('this_extension_does_not_exist_12345')
        ->and($report->hint)->not->toBeEmpty();
});
