<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;

/**
 * Kritik dizinlerin yazılabilir olup olmadığını kontrol eder.
 */
class WritableDirectoriesCheck implements DoctorCheck
{
    /** @var list<string> */
    private array $directories = [
        'storage',
        'storage/app',
        'storage/app/public',
        'storage/framework',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/logs',
        'bootstrap/cache',
    ];

    public function name(): string
    {
        return 'Writable Directories';
    }

    public function run(): DoctorReport
    {
        $basePath = function_exists('base_path') ? base_path() : '';

        $notWritable = [];

        foreach ($this->directories as $dir) {
            $fullPath = $basePath.'/'.$dir;

            if (is_dir($fullPath) && ! is_writable($fullPath)) {
                $notWritable[] = $dir;
            }
        }

        if ($notWritable !== []) {
            return DoctorReport::fail(
                $this->name(),
                'Non-writable directories: '.implode(', ', $notWritable).'.',
                'Run chmod -R 775 storage bootstrap/cache.'
            );
        }

        return DoctorReport::ok(
            $this->name(),
            'All critical directories are writable.'
        );
    }
}
