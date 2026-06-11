<?php

namespace Database\Seeders;

use App\Domain\Setting\SettingService;
use Illuminate\Database\Seeder;

class _03_SettingSeeder extends Seeder
{
    /**
     * Seed default settings from current config/.env values.
     *
     * Writes go through SettingService::seedDefault() so that sensitive keys
     * (mail.password, storage.*_secret) are encrypted-at-rest just like values
     * saved from the admin panel — the seeder never stores secrets in plaintext.
     */
    public function run(SettingService $settings): void
    {
        $defaults = [
            'general' => [
                'app_name' => 'LVNTR Laravel Starter Kit',
                'timezone' => config('app.display_timezone', 'UTC'),
                'languages' => implode(',', array_keys(config('app.languages', ['en' => 'English']))),
                'tagline' => null,
                'admin_email' => config('mail.from.address', 'admin@example.com'),
                'support_email' => null,
                'default_language' => (string) array_key_first(config('app.languages', ['en' => 'English'])),
                'currency' => 'TRY',
                'date_format' => 'd.m.Y',
            ],
            'auth' => [
                'registration' => '1',
                'email_verification' => '0',
                'two_factor' => '0',
                'password_reset' => '1',
                // Pre-feature hardened baseline: the old stub
                // AppServiceProvider raised Password::defaults() to min 10 +
                // mixed case + numbers + symbols. Fresh installs seed these
                // same values; existing installs get them as read-time
                // fallbacks (seedDefault never overwrites an existing row).
                'login_throttle' => '1',
                'password_min_length' => '10',
                'password_expiry_days' => '0',
                'password_require_mixed_case' => '1',
                'password_require_numbers' => '1',
                'password_require_symbols' => '1',
            ],
            'mail' => [
                'mailer' => 'smtp',
                'host' => config('mail.mailers.smtp.host', '127.0.0.1'),
                'port' => (string) config('mail.mailers.smtp.port', 587),
                'username' => config('mail.mailers.smtp.username'),
                'password' => config('mail.mailers.smtp.password'),
                'encryption' => config('mail.mailers.smtp.encryption', 'tls'),
                'from_address' => config('mail.from.address', 'hello@example.com'),
                'from_name' => config('mail.from.name', config('app.name')),
            ],
            'storage' => [
                'media_disk' => 'local',
                'spaces_key' => config('filesystems.disks.do.key'),
                'spaces_secret' => config('filesystems.disks.do.secret'),
                'spaces_region' => config('filesystems.disks.do.region'),
                'spaces_bucket' => config('filesystems.disks.do.bucket'),
                'spaces_endpoint' => config('filesystems.disks.do.endpoint'),
                'spaces_url' => config('filesystems.disks.do.url'),
                'aws_key' => config('filesystems.disks.s3.key'),
                'aws_secret' => config('filesystems.disks.s3.secret'),
                'aws_region' => config('filesystems.disks.s3.region'),
                'aws_bucket' => config('filesystems.disks.s3.bucket'),
                'aws_url' => config('filesystems.disks.s3.url'),
                'aws_endpoint' => config('filesystems.disks.s3.endpoint'),
            ],
            'file_manager' => [
                'max_size_mb' => '10',
                'storage_quota_gb' => '10',
                'accepted_mimes' => json_encode([
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp',
                    'application/pdf',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/plain',
                    'text/csv',
                ]),
                'allow_video' => '0',
                'allow_audio' => '0',
            ],
        ];

        foreach ($defaults as $group => $groupSettings) {
            foreach ($groupSettings as $key => $value) {
                $settings->seedDefault($group, $key, $value);
            }
        }
    }
}
