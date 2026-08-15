<?php

/*
|--------------------------------------------------------------------------
| Task 17 — Yeni Doctor Check'leri + Timeout Guard Testleri
|--------------------------------------------------------------------------
| NodeVersionCheck, QueueWorkerCheck, ScheduleConfiguredCheck (warn/stale)
| ve DoctorCommand::runGuarded timeout/hata koruması.
*/

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Console\Commands\DoctorCommand;
use Lvntr\StarterKit\Console\Doctor\Checks\NodeVersionCheck;
use Lvntr\StarterKit\Console\Doctor\Checks\QueueWorkerCheck;
use Lvntr\StarterKit\Console\Doctor\Checks\ScheduleConfiguredCheck;
use Lvntr\StarterKit\Console\Doctor\Checks\TimezoneStorageCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Lvntr\StarterKit\Console\Doctor\DoctorStatus;

/*
| NodeVersionCheck
*/

test('NodeVersionCheck geçerli bir rapor döner', function () {
    $report = (new NodeVersionCheck)->run();

    expect($report)->toBeInstanceOf(DoctorReport::class)
        ->and($report->name)->toBe('Node Version')
        // Node kurulu (ok) ya da eksik/eski (warn) — fail üretmez.
        ->and($report->status)->toBeIn([DoctorStatus::Ok, DoctorStatus::Warn]);
});

test('NodeVersionCheck Node kuruluysa sürümü raporlar', function () {
    $report = (new NodeVersionCheck)->run();

    // Bu ortamda node mevcut → ok ve sürüm mesajda yer alır.
    if ($report->isOk()) {
        expect($report->message)->toContain('Node.js')
            ->and($report->message)->toContain('minimum requirement');
    } else {
        // node yoksa: warn + kurulum hint'i
        expect($report->hint)->toContain('Node.js');
    }
});

/*
| QueueWorkerCheck
*/

test('QueueWorkerCheck sync driver için OK döner (worker gerekmez)', function () {
    config()->set('queue.default', 'sync');

    $report = (new QueueWorkerCheck)->run();

    expect($report->isOk())->toBeTrue()
        ->and($report->message)->toContain('sync');
});

test('QueueWorkerCheck async (redis) driver için warn döner', function () {
    config()->set('queue.default', 'redis');

    $report = (new QueueWorkerCheck)->run();

    expect($report->isWarn())->toBeTrue()
        ->and($report->message)->toContain('async')
        ->and($report->hint)->toContain('queue:work');

    config()->set('queue.default', 'sync');
});

test('QueueWorkerCheck database driver jobs tablosu yoksa warn döner', function () {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.connection', config('database.default'));
    config()->set('queue.connections.database.table', 'jobs_missing_table');

    $report = (new QueueWorkerCheck)->run();

    expect($report->isWarn())->toBeTrue()
        ->and($report->message)->toContain('does not exist')
        ->and($report->hint)->toContain('queue:table');

    config()->set('queue.default', 'sync');
});

/*
| ScheduleConfiguredCheck — warn/stale davranışı
*/

function skScheduleLastRunPath(): string
{
    return storage_path('framework/.schedule-last-run');
}

function skClearScheduleLastRun(): void
{
    $path = skScheduleLastRunPath();

    if (file_exists($path)) {
        @unlink($path);
    }

    @mkdir(dirname($path), 0777, true);
}

function skDefineOneScheduledTask(): void
{
    /** @var Schedule $schedule */
    $schedule = app(Schedule::class);
    $schedule->call(static fn () => null);
}

test('ScheduleConfiguredCheck görev yoksa warn döner', function () {
    // Varsayılan: schedule boş.
    $report = (new ScheduleConfiguredCheck)->run();

    expect($report->isWarn())->toBeTrue()
        ->and($report->message)->toContain('No scheduled tasks');
});

test('ScheduleConfiguredCheck görev var ama heartbeat dosyası yoksa cron uyarısı verir', function () {
    skClearScheduleLastRun();
    skDefineOneScheduledTask();

    $report = (new ScheduleConfiguredCheck)->run();

    expect($report->isWarn())->toBeTrue()
        ->and($report->message)->toContain('never been recorded')
        ->and($report->hint)->toContain('schedule:run');
});

test('ScheduleConfiguredCheck heartbeat tazeyse OK döner', function () {
    skClearScheduleLastRun();
    skDefineOneScheduledTask();
    file_put_contents(skScheduleLastRunPath(), (string) time());

    $report = (new ScheduleConfiguredCheck)->run();

    expect($report->isOk())->toBeTrue()
        ->and($report->message)->toContain('Last run:');

    skClearScheduleLastRun();
});

test('ScheduleConfiguredCheck heartbeat bayatsa stale uyarısı verir', function () {
    skClearScheduleLastRun();
    skDefineOneScheduledTask();
    // 10 dakika önce → 300sn eşiğini aşar.
    file_put_contents(skScheduleLastRunPath(), (string) (time() - 600));

    $report = (new ScheduleConfiguredCheck)->run();

    expect($report->isWarn())->toBeTrue()
        ->and($report->message)->toContain('last schedule:run was');

    skClearScheduleLastRun();
});

/*
| TimezoneStorageCheck
*/

test('TimezoneStorageCheck UTC application ve MySQL session timezone için OK döner', function () {
    config()->set('app.timezone', 'UTC');

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
    $connection->shouldReceive('selectOne')
        ->once()
        ->with('SELECT @@session.time_zone AS time_zone')
        ->andReturn((object) ['time_zone' => '+00:00']);
    DB::shouldReceive('connection')->once()->andReturn($connection);

    $report = (new TimezoneStorageCheck)->run();

    expect($report->isOk())->toBeTrue()
        ->and($report->message)->toContain('Application timezone is UTC')
        ->and($report->message)->toContain('+00:00');
});

test('TimezoneStorageCheck UTC dışı storage timezone için fail döner', function () {
    config()->set('app.timezone', 'Europe/Istanbul');

    $report = (new TimezoneStorageCheck)->run();

    expect($report->isFail())->toBeTrue()
        ->and($report->message)->toContain('Europe/Istanbul')
        ->and($report->message)->toContain('ambiguous')
        ->and($report->hint)->toContain('APP_TIMEZONE=UTC');

    config()->set('app.timezone', 'UTC');
});

test('TimezoneStorageCheck MySQL SYSTEM session timezone için fail döner', function () {
    config()->set('app.timezone', 'UTC');

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
    $connection->shouldReceive('selectOne')
        ->once()
        ->with('SELECT @@session.time_zone AS time_zone')
        ->andReturn((object) ['time_zone' => 'SYSTEM']);
    DB::shouldReceive('connection')->once()->andReturn($connection);

    $report = (new TimezoneStorageCheck)->run();

    expect($report->isFail())->toBeTrue()
        ->and($report->message)->toContain('SYSTEM')
        ->and($report->message)->toContain('TIMESTAMP')
        ->and($report->message)->toContain('rows on disk')
        ->and($report->hint)->toContain('config/database.php')
        ->and($report->hint)->toContain("'timezone' => '+00:00'");
});

test('TimezoneStorageCheck MySQL dışı driver için session kontrolünü uygulanamaz raporlar', function () {
    config()->set('app.timezone', 'UTC');

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDriverName')->once()->andReturn('sqlite');
    $connection->shouldNotReceive('selectOne');
    DB::shouldReceive('connection')->once()->andReturn($connection);

    $report = (new TimezoneStorageCheck)->run();

    expect($report->isOk())->toBeTrue()
        ->and($report->message)->toContain('does not apply')
        ->and($report->message)->toContain('sqlite');
});

test('TimezoneStorageCheck bağlantı hatasında warn döner ve kontrolü başarılı saymaz', function () {
    config()->set('app.timezone', 'UTC');

    DB::shouldReceive('connection')
        ->once()
        ->andThrow(new RuntimeException('connection refused'));

    $report = (new TimezoneStorageCheck)->run();

    expect($report->isWarn())->toBeTrue()
        ->and($report->isOk())->toBeFalse()
        ->and($report->message)->toContain('connection refused')
        ->and($report->message)->toContain('Could not verify');
});

test('TimezoneStorageCheck --only seçicisiyle tek başına çalışır', function () {
    config()->set('app.timezone', 'UTC');

    Artisan::call('sk:doctor', ['--json' => true, '--only' => 'timezone-storage']);
    $decoded = json_decode(Artisan::output(), true);

    expect($decoded['checks'])->toHaveCount(1)
        ->and($decoded['checks'][0]['name'])->toBe('Timezone Storage')
        ->and($decoded['checks'][0]['status'])->toBe('ok');
});

/*
| DoctorCommand::runGuarded — hata/timeout koruması
*/

test('runGuarded fırlatan bir check için warn üretir, exception sızdırmaz', function () {
    $throwing = new class implements DoctorCheck
    {
        public function name(): string
        {
            return 'Boom Check';
        }

        public function run(): DoctorReport
        {
            throw new RuntimeException('kaboom');
        }
    };

    $command = new DoctorCommand;
    $method = new ReflectionMethod($command, 'runGuarded');
    $method->setAccessible(true);

    /** @var DoctorReport $report */
    $report = $method->invoke($command, $throwing);

    expect($report)->toBeInstanceOf(DoctorReport::class)
        ->and($report->isWarn())->toBeTrue()
        ->and($report->name)->toBe('Boom Check')
        ->and($report->message)->toContain('unexpected error');
});

test('runGuarded normal bir check sonucunu değiştirmeden döner', function () {
    $okCheck = new class implements DoctorCheck
    {
        public function name(): string
        {
            return 'Fast Check';
        }

        public function run(): DoctorReport
        {
            return DoctorReport::ok('Fast Check', 'all good');
        }
    };

    $command = new DoctorCommand;
    $method = new ReflectionMethod($command, 'runGuarded');
    $method->setAccessible(true);

    /** @var DoctorReport $report */
    $report = $method->invoke($command, $okCheck);

    expect($report->isOk())->toBeTrue()
        ->and($report->message)->toBe('all good');
});
