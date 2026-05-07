<?php

/*
|--------------------------------------------------------------------------
| RedisConnectionCheck Testleri
|--------------------------------------------------------------------------
*/

use Lvntr\StarterKit\Console\Doctor\Checks\RedisConnectionCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

test('cache driver redis değilse warn döner', function () {
    config()->set('cache.default', 'file');
    config()->set('session.driver', 'file');

    $check = new RedisConnectionCheck;
    $report = $check->run();

    expect($report->status)->toBe(DoctorStatus::Warn)
        ->and($report->message)->toContain('Redis kullanmıyor')
        ->and($report->hint)->not->toBeEmpty();
});

test('cache driver redis iken bağlantı başarısızsa fail döner', function () {
    config()->set('cache.default', 'redis');
    config()->set('database.redis.default.host', '127.0.0.1');
    config()->set('database.redis.default.port', 19999); // Olmayan port

    $check = new RedisConnectionCheck;
    $report = $check->run();

    // 2 saniye TCP timeout ile fail beklenir
    expect($report->status)->toBe(DoctorStatus::Fail)
        ->and($report->hint)->not->toBeEmpty();
});

test('report name doğru', function () {
    $check = new RedisConnectionCheck;
    expect($check->name())->toBe('Redis Connection');
});
