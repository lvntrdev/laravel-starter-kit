<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Throwable;

/**
 * Kuyruk worker'ının fiilen iş tükettiğine dair hafif sinyaller arar —
 * sentinel job DISPATCH ETMEDEN. `database` driver'da bekleyen en eski
 * job'ın yaşına bakar (backlog-age sezgisi): uzun süredir bekleyen job'lar
 * worker'ın düştüğüne işarettir. `redis`/`sqs` gibi async driver'larda yaş
 * bilgisi ucuz alınamaz — canlılık doğrudan doğrulanamadığı için warn'la
 * worker çalıştırmayı hatırlatır. `sync` driver worker gerektirmez.
 */
class QueueWorkerCheck implements DoctorCheck
{
    /** Bekleyen job bu süreden (saniye) uzun süredir işlenmediyse worker down şüphesi. */
    private const STALE_THRESHOLD = 300;

    public function name(): string
    {
        return 'Queue Worker';
    }

    public function run(): DoctorReport
    {
        $driver = config('queue.default', 'sync');

        if ($driver === 'sync') {
            return DoctorReport::ok(
                $this->name(),
                'Queue driver is "sync" — jobs run inline; no worker process is required.'
            );
        }

        if ($driver === 'database') {
            return $this->checkDatabaseBacklog();
        }

        // redis / sqs / beanstalkd: bekleyen job yaşı ucuz sorgulanamıyor.
        return DoctorReport::warn(
            $this->name(),
            "Queue driver \"{$driver}\" is async — worker liveness cannot be verified automatically.",
            'Ensure a worker is running: php artisan queue:work (Supervisor or Horizon in production).'
        );
    }

    private function checkDatabaseBacklog(): DoctorReport
    {
        try {
            $connection = config('queue.connections.database.connection')
                ?: config('database.default');
            $table = (string) config('queue.connections.database.table', 'jobs');

            $schema = DB::connection($connection)->getSchemaBuilder();

            if (! $schema->hasTable($table)) {
                return DoctorReport::warn(
                    $this->name(),
                    "Queue table \"{$table}\" does not exist — jobs cannot be persisted.",
                    'Run php artisan queue:table && php artisan migrate.'
                );
            }

            $now = time();

            $oldest = DB::connection($connection)->table($table)
                ->whereNull('reserved_at')
                ->where('available_at', '<=', $now)
                ->orderBy('available_at')
                ->first(['available_at']);

            if ($oldest === null) {
                return DoctorReport::ok(
                    $this->name(),
                    'No pending jobs are waiting — the worker appears healthy (or the queue is empty).'
                );
            }

            $waitedFor = $now - (int) $oldest->available_at;

            if ($waitedFor >= self::STALE_THRESHOLD) {
                $pending = DB::connection($connection)->table($table)
                    ->whereNull('reserved_at')
                    ->where('available_at', '<=', $now)
                    ->count();

                return DoctorReport::warn(
                    $this->name(),
                    "{$pending} job(s) pending; the oldest has waited ".$this->humanize($waitedFor).' — the worker may be down.',
                    'Start or restart the queue worker: php artisan queue:work (or Supervisor/Horizon).'
                );
            }

            return DoctorReport::ok(
                $this->name(),
                'Pending jobs are within the expected processing window (oldest waited '.$this->humanize($waitedFor).').'
            );
        } catch (Throwable $e) {
            return DoctorReport::warn(
                $this->name(),
                'Could not inspect the queue backlog: '.$e->getMessage(),
                'Verify the queue database connection and jobs table.'
            );
        }
    }

    private function humanize(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }

        $minutes = intdiv($seconds, 60);

        if ($minutes < 60) {
            return "{$minutes}m";
        }

        return intdiv($minutes, 60).'h';
    }
}
