<?php

/*
|--------------------------------------------------------------------------
| MailDriverCheck Testleri
|--------------------------------------------------------------------------
*/

use Lvntr\StarterKit\Console\Doctor\Checks\MailDriverCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

test('log driver local ortamda warn döner', function () {
    config()->set('app.env', 'local');
    config()->set('mail.default', 'log');
    config()->set('mail.mailers.log.transport', 'log');

    $check = new MailDriverCheck;
    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Warn)
        ->and($report->message)->toContain('log')
        ->and($report->hint)->not->toBeEmpty();
});

test('log driver production config değeri ile fail döner', function () {
    // Testbench'te app()->environment() 'testing' hard-coded.
    // config('app.env') → 'production' set edilerek fail tetiklenir.
    config()->set('app.env', 'production');
    config()->set('mail.default', 'log');
    config()->set('mail.mailers.log.transport', 'log');

    $check = new MailDriverCheck;
    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Fail)
        ->and($report->message)->toContain('production');

    config()->set('app.env', 'testing');
});

test('smtp host tanımlı değilse fail döner', function () {
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp.transport', 'smtp');
    config()->set('mail.mailers.smtp.host', '');

    $check = new MailDriverCheck;
    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Fail)
        ->and($report->message)->toContain('SMTP host is not configured');
});

test('smtp bağlantısı başarısızsa fail döner', function () {
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp.transport', 'smtp');
    config()->set('mail.mailers.smtp.host', '127.0.0.1');
    config()->set('mail.mailers.smtp.port', 19997); // Olmayan port

    $check = new MailDriverCheck;
    $report = $check->run();

    // 2 saniye timeout ile fail beklenir
    expect($report->status)->toBe(DoctorStatus::Fail)
        ->and($report->hint)->toContain('MAIL_HOST');

    // Geri al
    config()->set('mail.default', 'log');
});

test('report name doğru', function () {
    $check = new MailDriverCheck;
    expect($check->name())->toBe('Mail Driver');
});
