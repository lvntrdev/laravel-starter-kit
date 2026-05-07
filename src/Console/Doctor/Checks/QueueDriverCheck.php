<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Throwable;

/**
 * Queue driver konfigürasyonunu kontrol eder.
 * sync driver: sadece uyarı (production'da uygun değil).
 * database/redis driver: bağlantı ping'i dener.
 */
class QueueDriverCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'Queue Driver';
    }

    public function run(): DoctorReport
    {
        $driver = config('queue.default', 'sync');

        if ($driver === 'sync') {
            $env = config('app.env', app()->environment());

            if ($env === 'production') {
                return DoctorReport::fail(
                    $this->name(),
                    'Queue driver is "sync" in production — jobs run inside the HTTP request.',
                    'Set QUEUE_CONNECTION=redis or database and start queue:work.'
                );
            }

            return DoctorReport::warn(
                $this->name(),
                "Queue driver \"{$driver}\" — not suitable for production.",
                'Set QUEUE_CONNECTION=redis or database.'
            );
        }

        if ($driver === 'database') {
            try {
                // jobs tablosunun varlığını kontrol et
                DB::connection()->getSchemaBuilder()->hasTable('jobs');

                return DoctorReport::ok(
                    $this->name(),
                    "Queue driver \"{$driver}\" is active."
                );
            } catch (Throwable $e) {
                return DoctorReport::warn(
                    $this->name(),
                    "Queue driver \"{$driver}\" but DB access failed: ".$e->getMessage(),
                    'Run php artisan queue:table && php artisan migrate.'
                );
            }
        }

        if ($driver === 'redis') {
            try {
                Queue::connection('redis')->size();

                return DoctorReport::ok(
                    $this->name(),
                    "Queue driver \"{$driver}\" is active and connection successful."
                );
            } catch (Throwable $e) {
                return DoctorReport::fail(
                    $this->name(),
                    "Queue driver \"{$driver}\" but Redis connection failed: ".$e->getMessage(),
                    'Make sure the Redis server is running.'
                );
            }
        }

        return DoctorReport::ok(
            $this->name(),
            "Queue driver \"{$driver}\" is configured."
        );
    }
}
