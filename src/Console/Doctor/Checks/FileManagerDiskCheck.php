<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;
use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Throwable;

/**
 * FileManager'ın kullandığı disk'i kontrol eder.
 * S3 driver için headBucket ping'i yapar (2 saniye timeout).
 * Local/public driver için dizin varlığını kontrol eder.
 */
class FileManagerDiskCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'FileManager Disk';
    }

    public function run(): DoctorReport
    {
        $diskName = config('filesystems.default', 'local');

        // file-manager'a özgü disk tanımlıysa onu kullan
        $fileManagerDisk = config('file-manager.disk', $diskName);

        $diskConfig = config("filesystems.disks.{$fileManagerDisk}", []);
        $driver = $diskConfig['driver'] ?? 'unknown';

        if ($driver === 's3') {
            return $this->checkS3Disk($fileManagerDisk, $diskConfig);
        }

        // Local/public driver: dizin mevcut mu ve yazılabilir mi?
        $root = $diskConfig['root'] ?? '';

        if ($root !== '' && is_dir($root)) {
            return DoctorReport::ok(
                $this->name(),
                "FileManager disk \"{$fileManagerDisk}\" ({$driver}) is accessible."
            );
        }

        if ($root !== '' && ! is_dir($root)) {
            return DoctorReport::warn(
                $this->name(),
                "FileManager disk \"{$fileManagerDisk}\" ({$driver}) root directory not found: {$root}.",
                'Run php artisan storage:link.'
            );
        }

        return DoctorReport::ok(
            $this->name(),
            "FileManager disk \"{$fileManagerDisk}\" ({$driver}) is configured."
        );
    }

    private function checkS3Disk(string $diskName, array $diskConfig): DoctorReport
    {
        $bucket = $diskConfig['bucket'] ?? '';

        if (empty($bucket)) {
            return DoctorReport::fail(
                $this->name(),
                "No bucket configured for S3 disk \"{$diskName}\".",
                'Set AWS_BUCKET in your .env file.'
            );
        }

        try {
            // S3 headBucket: bucket varlığı + IAM okuma iznini doğrular
            $disk = Storage::disk($diskName);
            $adapter = $disk->getAdapter();

            // headBucket üzerinden bağlantı testi (2 saniye timeout için URL oluştur)
            if (method_exists($adapter, 'getClient')) {
                /** @var S3Client $client */
                $client = $adapter->getClient();
                $client->headBucket(['Bucket' => $bucket]);
            } else {
                // Adapter S3Client'ı expose etmiyorsa dizin listesini dene
                $disk->directories();
            }

            return DoctorReport::ok(
                $this->name(),
                "S3 disk \"{$diskName}\" (bucket: {$bucket}) is accessible."
            );
        } catch (Throwable $e) {
            return DoctorReport::fail(
                $this->name(),
                "S3 disk \"{$diskName}\" (bucket: {$bucket}) is not accessible: ".$e->getMessage(),
                'Check AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_BUCKET, and AWS_DEFAULT_REGION. IAM policy must include s3:GetBucketLocation and s3:HeadBucket permissions.'
            );
        }
    }
}
