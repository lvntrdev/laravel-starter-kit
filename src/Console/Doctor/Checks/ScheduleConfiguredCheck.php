<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Throwable;

/**
 * Schedule'ın konfigüre edilip edilmediğini kontrol eder.
 * Son schedule:run zamanı için last_run_at log kaydına bakılır.
 */
class ScheduleConfiguredCheck implements DoctorCheck
{
    /** schedule:run heartbeat bu süreden (saniye) eskiyse cron durmuş olabilir. */
    private const STALE_THRESHOLD = 300;

    public function name(): string
    {
        return 'Schedule Configured';
    }

    public function run(): DoctorReport
    {
        try {
            /** @var Schedule $schedule */
            $schedule = app(Schedule::class);
            $events = $schedule->events();
            $count = count($events);

            if ($count === 0) {
                return DoctorReport::warn(
                    $this->name(),
                    'No scheduled tasks are defined.',
                    'Define tasks in routes/console.php or App\Console\Kernel.'
                );
            }

            // Cron canlılığı: schedule:run her dakika bu dosyaya heartbeat yazar
            // (StarterKitServiceProvider CommandFinished listener). Dosya yoksa
            // schedule:run hiç çalışmamış → cron kurulu olmayabilir.
            $lastRunFile = storage_path('framework/.schedule-last-run');

            if (! file_exists($lastRunFile)) {
                return DoctorReport::warn(
                    $this->name(),
                    "{$count} scheduled task(s) defined, but schedule:run has never been recorded.",
                    'The system cron entry may be missing. Add: * * * * * php artisan schedule:run >> /dev/null 2>&1'
                );
            }

            $timestamp = (int) file_get_contents($lastRunFile);
            $secondsAgo = now()->getTimestamp() - $timestamp;
            $diff = now()->diffForHumans(Carbon::createFromTimestamp($timestamp));

            if ($secondsAgo >= self::STALE_THRESHOLD) {
                return DoctorReport::warn(
                    $this->name(),
                    "{$count} scheduled task(s) defined, but the last schedule:run was {$diff}.",
                    'The cron may have stopped. Verify the crontab entry runs schedule:run every minute.'
                );
            }

            return DoctorReport::ok(
                $this->name(),
                "{$count} scheduled task(s) defined. Last run: {$diff}."
            );
        } catch (Throwable $e) {
            return DoctorReport::warn(
                $this->name(),
                'Could not check schedule status: '.$e->getMessage(),
                'Is the Schedule container binding correct? Try php artisan schedule:list.'
            );
        }
    }
}
