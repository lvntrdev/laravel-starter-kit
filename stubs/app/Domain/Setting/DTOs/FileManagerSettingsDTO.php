<?php

namespace App\Domain\Setting\DTOs;

use App\Domain\Shared\DTOs\BaseDTO;

readonly class FileManagerSettingsDTO extends BaseDTO
{
    /**
     * @param  array<int, string>  $acceptedMimes
     */
    public function __construct(
        public int $maxSizeKb,
        public array $acceptedMimes,
        public bool $allowVideo,
        public bool $allowAudio,
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
            maxSizeKb: (int) ($data['max_size_kb'] ?? 10240),
            acceptedMimes: array_values(array_filter(array_map('strval', (array) $mimes))),
            allowVideo: (bool) ($data['allow_video'] ?? false),
            allowAudio: (bool) ($data['allow_audio'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'max_size_kb' => (string) $this->maxSizeKb,
            'accepted_mimes' => json_encode($this->acceptedMimes),
            'allow_video' => $this->allowVideo ? '1' : '0',
            'allow_audio' => $this->allowAudio ? '1' : '0',
        ];
    }
}
