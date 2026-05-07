<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;

/**
 * Mail driver konfigürasyonunu kontrol eder.
 * smtp driver için 2 saniye TCP ping dener.
 * log/array driver production'da uyarı üretir.
 */
class MailDriverCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'Mail Driver';
    }

    public function run(): DoctorReport
    {
        $mailer = config('mail.default', 'log');
        $transport = config("mail.mailers.{$mailer}.transport", $mailer);

        if (in_array($transport, ['log', 'array'], true)) {
            $env = config('app.env', app()->environment());

            if ($env === 'production') {
                return DoctorReport::fail(
                    $this->name(),
                    "Mail driver is \"{$transport}\" in production — emails cannot be sent.",
                    'Set MAIL_MAILER=smtp or use a driver such as Mailgun/SES.'
                );
            }

            return DoctorReport::warn(
                $this->name(),
                "Mail driver \"{$transport}\" — not suitable for production.",
                'Set MAIL_MAILER=smtp or use a driver such as Mailgun/SES.'
            );
        }

        if ($transport === 'smtp') {
            $host = config("mail.mailers.{$mailer}.host", '');
            $port = (int) config("mail.mailers.{$mailer}.port", 587);

            if (empty($host)) {
                return DoctorReport::fail(
                    $this->name(),
                    'SMTP host is not configured.',
                    'Set MAIL_HOST in your .env file.'
                );
            }

            // 2 saniye TCP ping
            $socket = @stream_socket_client(
                "tcp://{$host}:{$port}",
                $errno,
                $errstr,
                2.0
            );

            if ($socket === false) {
                return DoctorReport::fail(
                    $this->name(),
                    "Could not connect to SMTP server ({$host}:{$port}): {$errstr}.",
                    'Check MAIL_HOST, MAIL_PORT, MAIL_USERNAME, and MAIL_PASSWORD.'
                );
            }

            fclose($socket);

            return DoctorReport::ok(
                $this->name(),
                "SMTP connection successful ({$host}:{$port})."
            );
        }

        return DoctorReport::ok(
            $this->name(),
            "Mail driver \"{$transport}\" is configured."
        );
    }
}
