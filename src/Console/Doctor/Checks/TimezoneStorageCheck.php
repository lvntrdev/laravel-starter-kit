<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Throwable;

class TimezoneStorageCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'Timezone Storage';
    }

    public function run(): DoctorReport
    {
        $timezone = config('app.timezone');

        if ($timezone !== 'UTC') {
            $configured = is_string($timezone) ? $timezone : 'unset';

            return DoctorReport::fail(
                $this->name(),
                "Application timezone is {$configured}; timestamps are being stored outside UTC and existing rows are already ambiguous.",
                'Set APP_TIMEZONE=UTC. Use APP_DISPLAY_TIMEZONE or the General/user settings for display timezones.'
            );
        }

        try {
            $connection = DB::connection();
            $driver = strtolower($connection->getDriverName());

            if (! in_array($driver, ['mysql', 'mariadb'], true)) {
                return DoctorReport::ok(
                    $this->name(),
                    "Application timezone is UTC; the database session timezone check does not apply to the {$driver} driver."
                );
            }

            $result = $connection->selectOne('SELECT @@session.time_zone AS time_zone');
            $sessionTimezone = is_object($result) && isset($result->time_zone)
                ? (string) $result->time_zone
                : '';

            if ($sessionTimezone === '') {
                return DoctorReport::warn(
                    $this->name(),
                    'Could not verify the database session timezone even though the application timezone is UTC: the query returned no value.',
                    'Check the database connection and the timezone key for the default connection in config/database.php.'
                );
            }
        } catch (Throwable $e) {
            return DoctorReport::warn(
                $this->name(),
                'Could not verify the database session timezone even though the application timezone is UTC: '.$e->getMessage(),
                'Check the database connection and the timezone key for the default connection in config/database.php.'
            );
        }

        if (! in_array($sessionTimezone, ['+00:00', 'UTC'], true)) {
            return DoctorReport::fail(
                $this->name(),
                "Application timezone is UTC, but the {$driver} session timezone is {$sessionTimezone}. TIMESTAMP columns are being written through a non-UTC session conversion, so rows on disk are offset even though the application reads them back consistently.",
                "Set 'timezone' => '+00:00' on the default {$driver} connection in config/database.php."
            );
        }

        return DoctorReport::ok(
            $this->name(),
            "Application timezone is UTC and the {$driver} session timezone is {$sessionTimezone}; TIMESTAMP storage uses UTC without session conversion offsets."
        );
    }
}
