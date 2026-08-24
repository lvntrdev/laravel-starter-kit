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
        // config(), not env(): once the configuration is cached, .env is not
        // loaded and env() would report the default instead of the effective
        // runtime setting. logging.channels.stack.channels is the array
        // LOG_STACK is exploded into by the framework's logging config.
        $channels = array_map(
            static fn ($channel): string => trim((string) $channel),
            (array) config('logging.channels.stack.channels', ['single'])
        );

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
