<?php

namespace Lvntr\StarterKit\Domain\Setting\DTOs;

use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;

readonly class FileManagerSettingsDTO extends BaseDTO
{
    /**
     * @param  array<int, string>  $acceptedMimes
     */
    public function __construct(
        public int $maxSizeMb,
        public int $storageQuotaGb,
        public array $acceptedMimes,
        public bool $allowVideo,
        public bool $allowAudio,
        public bool $enableTrash,
        public int $trashRetentionDays,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $mimes = $data['accepted_mimes'] ?? [];
        if (is_string($mimes)) {
            $decoded = json_decode($mimes, true);
            $mimes = is_array($decoded) ? $decoded : [];
        }

        return new static(
            maxSizeMb: (int) ($data['max_size_mb'] ?? 10),
            storageQuotaGb: (int) ($data['storage_quota_gb'] ?? 10),
            acceptedMimes: array_values(array_filter(array_map('strval', (array) $mimes))),
            allowVideo: (bool) ($data['allow_video'] ?? false),
            allowAudio: (bool) ($data['allow_audio'] ?? false),
            enableTrash: (bool) ($data['enable_trash'] ?? true),
            trashRetentionDays: max(1, (int) ($data['trash_retention_days'] ?? 7)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'max_size_mb' => (string) $this->maxSizeMb,
            'storage_quota_gb' => (string) $this->storageQuotaGb,
            'accepted_mimes' => json_encode($this->acceptedMimes),
            'allow_video' => $this->allowVideo ? '1' : '0',
            'allow_audio' => $this->allowAudio ? '1' : '0',
            'enable_trash' => $this->enableTrash ? '1' : '0',
            'trash_retention_days' => (string) $this->trashRetentionDays,
        ];
    }
}
