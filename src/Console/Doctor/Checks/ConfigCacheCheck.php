<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;

/**
 * Production ortamında config cache'in mevcut olup olmadığını kontrol eder.
 * Local/testing ortamında config cache önerilmez.
 */
class ConfigCacheCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'Config Cache';
    }

    public function run(): DoctorReport
    {
        $env = config('app.env', app()->environment());
        $cachePath = function_exists('base_path')
            ? base_path('bootstrap/cache/config.php')
            : 'bootstrap/cache/config.php';

        $cacheExists = file_exists($cachePath);

        if ($env === 'production') {
            if (! $cacheExists) {
                return DoctorReport::warn(
                    $this->name(),
                    'Config cache not found in production environment.',
                    'Run php artisan config:cache (recommended for performance).'
                );
            }

            return DoctorReport::ok(
                $this->name(),
                'Config cache exists and is ready for production.'
            );
        }

        // Local/testing: cache varsa uyarı (stale config riski)
        if ($cacheExists) {
            return DoctorReport::warn(
                $this->name(),
                "Config cache exists but environment is \"{$env}\" — config changes may not be reflected.",
                'Clear the cache with php artisan config:clear.'
            );
        }

        return DoctorReport::ok(
            $this->name(),
            "Environment \"{$env}\" — config cache is not required."
        );
    }
}
