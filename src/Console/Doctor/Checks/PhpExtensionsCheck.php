<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;

/**
 * Starter Kit'in gerektirdiği PHP extension'larını kontrol eder.
 */
class PhpExtensionsCheck implements DoctorCheck
{
    /** @var list<string> */
    private array $required = [
        'gd',
        'intl',
        'pdo_mysql',
        'redis',
        'fileinfo',
        'openssl',
    ];

    public function name(): string
    {
        return 'PHP Extensions';
    }

    public function run(): DoctorReport
    {
        $missing = array_filter($this->required, fn (string $ext) => ! extension_loaded($ext));

        if ($missing === []) {
            return DoctorReport::ok(
                $this->name(),
                'All required PHP extensions are loaded ('.implode(', ', $this->required).').'
            );
        }

        return DoctorReport::fail(
            $this->name(),
            'Missing extensions: '.implode(', ', $missing).'.',
            'Enable the extensions in your php.ini or install them via your package manager.'
        );
    }
}
