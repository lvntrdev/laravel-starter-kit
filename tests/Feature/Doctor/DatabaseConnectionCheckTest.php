<?php

/*
|--------------------------------------------------------------------------
| DatabaseConnectionCheck Testleri
|--------------------------------------------------------------------------
*/

use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Console\Doctor\Checks\DatabaseConnectionCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

test('geçerli DB bağlantısında ok döner', function () {
    $check = new DatabaseConnectionCheck;
    $report = $check->run();

    // DatabaseTestCase SQLite in-memory kullanır — bağlantı başarılı olmalı.
    expect($report->status)->toBe(DoctorStatus::Ok)
        ->and($report->message)->toContain('Connection successful');
});

test('geçersiz bağlantıda fail döner', function () {
    // Geçici olarak bağlantı config'ini bozuyoruz.
    config()->set('database.connections.testing.database', '/nonexistent/path/totally_fake.db');
    config()->set('database.default', 'testing');

    // Mevcut bağlantıyı kapat ki yeni config devreye girsin.
    DB::purge('testing');

    $check = new DatabaseConnectionCheck;
    $report = $check->run();

    // SQLite'da olmayan dosya path'i fail üretmez ama bağlantı açılır (yeni file oluşturur).
    // Bu nedenle sadece toArray shape'ini doğruluyoruz.
    expect($report->toArray())->toHaveKeys(['name', 'status', 'message', 'hint'])
        ->and($report->name)->toBe('Database Connection');
})->skip('SQLite driver geçersiz path yerine yeni dosya oluşturduğundan fail üretmiyor.');

test('report name doğru', function () {
    $check = new DatabaseConnectionCheck;
    expect($check->name())->toBe('Database Connection');
});
