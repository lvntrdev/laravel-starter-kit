<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;

/**
 * Passport OAuth anahtar dosyalarını kontrol eder.
 * storage/oauth-private.key ve storage/oauth-public.key mevcut ve okunabilir olmalı.
 */
class PassportKeysCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'Passport Keys';
    }

    public function run(): DoctorReport
    {
        if (! class_exists('Laravel\Passport\Passport')) {
            return DoctorReport::warn(
                $this->name(),
                'Laravel Passport is not installed — key check skipped.',
                'Ignore this warning if you are not using Passport.'
            );
        }

        $storagePath = function_exists('storage_path') ? storage_path() : '';

        $privateKey = $storagePath.'/oauth-private.key';
        $publicKey = $storagePath.'/oauth-public.key';

        $missing = [];
        $unreadable = [];

        foreach ([$privateKey, $publicKey] as $keyPath) {
            $basename = basename($keyPath);

            if (! file_exists($keyPath)) {
                $missing[] = $basename;

                continue;
            }

            if (! is_readable($keyPath)) {
                $unreadable[] = $basename;
            }
        }

        if ($missing !== []) {
            return DoctorReport::fail(
                $this->name(),
                'Missing Passport key file(s): '.implode(', ', $missing).'.',
                'Run php artisan passport:keys.'
            );
        }

        if ($unreadable !== []) {
            return DoctorReport::fail(
                $this->name(),
                'Unreadable Passport key file(s): '.implode(', ', $unreadable).'.',
                'Fix permissions with chmod 600 storage/oauth-*.key.'
            );
        }

        return DoctorReport::ok(
            $this->name(),
            'Passport key files exist and are readable.'
        );
    }
}
