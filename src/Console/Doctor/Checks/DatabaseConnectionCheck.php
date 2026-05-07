<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Throwable;

/**
 * Varsayılan veritabanı bağlantısını test eder (PDO ping).
 */
class DatabaseConnectionCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'Database Connection';
    }

    public function run(): DoctorReport
    {
        try {
            DB::connection()->getPdo();

            $driver = DB::connection()->getDriverName();
            $database = DB::connection()->getDatabaseName();

            return DoctorReport::ok(
                $this->name(),
                "Connection successful ({$driver}: {$database})."
            );
        } catch (Throwable $e) {
            return DoctorReport::fail(
                $this->name(),
                'Could not connect to the database: '.$e->getMessage(),
                'Check DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD in your .env file.'
            );
        }
    }
}
