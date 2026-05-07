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

            // Cron kaydı varlığını kontrol et
            $lastRunFile = storage_path('framework/.schedule-last-run');
            $lastRunInfo = '';

            if (file_exists($lastRunFile)) {
                $timestamp = (int) file_get_contents($lastRunFile);
                $diff = now()->diffForHumans(Carbon::createFromTimestamp($timestamp));
                $lastRunInfo = " Last run: {$diff}.";
            }

            return DoctorReport::ok(
                $this->name(),
                "{$count} scheduled task(s) defined.{$lastRunInfo}"
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
