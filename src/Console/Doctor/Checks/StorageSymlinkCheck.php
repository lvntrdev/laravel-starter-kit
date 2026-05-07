<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;

/**
 * public/storage sembolik linkinin varlığını ve geçerliliğini kontrol eder.
 */
class StorageSymlinkCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'Storage Symlink';
    }

    public function run(): DoctorReport
    {
        $publicPath = function_exists('public_path') ? public_path('storage') : base_path('public/storage');

        if (! file_exists($publicPath) && ! is_link($publicPath)) {
            return DoctorReport::fail(
                $this->name(),
                'public/storage symlink not found.',
                'Run php artisan storage:link.'
            );
        }

        if (is_link($publicPath) && ! file_exists($publicPath)) {
            return DoctorReport::fail(
                $this->name(),
                'public/storage is a broken symlink (target not found).',
                'Recreate it with php artisan storage:link --force.'
            );
        }

        return DoctorReport::ok(
            $this->name(),
            'public/storage symlink is valid.'
        );
    }
}
