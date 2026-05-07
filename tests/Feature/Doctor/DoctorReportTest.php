<?php

/*
|--------------------------------------------------------------------------
| DoctorReport Value Object Testleri
|--------------------------------------------------------------------------
*/

use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

test('ok factory metodu doğru status oluşturur', function () {
    $report = DoctorReport::ok('Test Check', 'Her şey yolunda.');

    expect($report->status)->toBe(DoctorStatus::Ok)
        ->and($report->name)->toBe('Test Check')
        ->and($report->message)->toBe('Her şey yolunda.')
        ->and($report->hint)->toBe('')
        ->and($report->isOk())->toBeTrue()
        ->and($report->isWarn())->toBeFalse()
        ->and($report->isFail())->toBeFalse();
});

test('warn factory metodu hint ile doğru status oluşturur', function () {
    $report = DoctorReport::warn('Test Check', 'Uyarı var.', 'Şunu yapın.');

    expect($report->status)->toBe(DoctorStatus::Warn)
        ->and($report->hint)->toBe('Şunu yapın.')
        ->and($report->isWarn())->toBeTrue()
        ->and($report->isOk())->toBeFalse()
        ->and($report->isFail())->toBeFalse();
});

test('fail factory metodu hint ile doğru status oluşturur', function () {
    $report = DoctorReport::fail('Test Check', 'Hata oluştu.', 'Düzeltme önerisi.');

    expect($report->status)->toBe(DoctorStatus::Fail)
        ->and($report->hint)->toBe('Düzeltme önerisi.')
        ->and($report->isFail())->toBeTrue()
        ->and($report->isOk())->toBeFalse()
        ->and($report->isWarn())->toBeFalse();
});

test('toArray doğru alanları döner', function () {
    $report = DoctorReport::warn('PHP Extensions', 'redis eksik.', 'Kurun.');

    $array = $report->toArray();

    expect($array)->toHaveKeys(['name', 'status', 'message', 'hint'])
        ->and($array['name'])->toBe('PHP Extensions')
        ->and($array['status'])->toBe('warn')
        ->and($array['message'])->toBe('redis eksik.')
        ->and($array['hint'])->toBe('Kurun.');
});
