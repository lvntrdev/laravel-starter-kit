<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;

class LogStackCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'Log Stack';
    }

    public function run(): DoctorReport
    {
        $logStack = env('LOG_STACK', 'single');
        $channels = array_map('trim', explode(',', (string) $logStack));

        if (in_array('single', $channels, strict: true)) {
            return DoctorReport::warn(
                $this->name(),
                'LOG_STACK contains "single" — logs accumulate in one file and will grow unbounded.',
                'Set LOG_STACK=daily in .env to enable automatic log rotation.'
            );
        }

        return DoctorReport::ok(
            $this->name(),
            'LOG_STACK="'.implode(',', $channels).'" — daily rotation active.'
        );
    }
}
