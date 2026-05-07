<?php

/*
|--------------------------------------------------------------------------
| QueueDriverCheck Testleri
|--------------------------------------------------------------------------
*/

use Lvntr\StarterKit\Console\Doctor\Checks\QueueDriverCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

test('sync driver local ortamda warn döner', function () {
    config()->set('queue.default', 'sync');
    config()->set('app.env', 'local');

    $check = new QueueDriverCheck;
    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Warn)
        ->and($report->message)->toContain('sync')
        ->and($report->hint)->not->toBeEmpty();
});

test('sync driver production config değeri ile fail döner', function () {
    // Testbench'te app()->environment() 'testing' hard-coded olduğundan
    // config('app.env') ile production simüle ediyoruz.
    config()->set('app.env', 'production');
    config()->set('queue.default', 'sync');

    $check = new QueueDriverCheck;
    $report = $check->run();

    // config('app.env') 'production' → fail beklenir
    expect($report->status)->toBe(DoctorStatus::Fail)
        ->and($report->message)->toContain('production')
        ->and($report->hint)->toContain('QUEUE_CONNECTION');

    config()->set('app.env', 'testing');
});

test('redis driver başarısız bağlantıda fail döner', function () {
    config()->set('queue.default', 'redis');
    config()->set('queue.connections.redis.connection', 'default');
    config()->set('database.redis.default.host', '127.0.0.1');
    config()->set('database.redis.default.port', 19998); // Olmayan port

    $check = new QueueDriverCheck;
    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Fail)
        ->and($report->hint)->not->toBeEmpty();

    // Driver'ı geri al
    config()->set('queue.default', 'sync');
});

test('report name doğru', function () {
    $check = new QueueDriverCheck;
    expect($check->name())->toBe('Queue Driver');
});
