<?php

namespace Lvntr\StarterKit\Domain\Setting\DTOs;

use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for storage settings.
 */
readonly class StorageSettingsDTO extends BaseDTO
{
    public function __construct(
        public ?string $mediaDisk,
        public ?string $spacesKey,
        public ?string $spacesSecret,
        public ?string $spacesRegion,
        public ?string $spacesBucket,
        public ?string $spacesEndpoint,
        public ?string $spacesUrl,
        public ?string $awsKey,
        public ?string $awsSecret,
        public ?string $awsRegion,
        public ?string $awsBucket,
        public ?string $awsUrl,
        public ?string $awsEndpoint,
        public ?string $hetznerKey = null,
        public ?string $hetznerSecret = null,
        public ?string $hetznerRegion = null,
        public ?string $hetznerBucket = null,
        public ?string $hetznerEndpoint = null,
        public ?string $hetznerUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            mediaDisk: $data['media_disk'] ?? null,
            spacesKey: $data['spaces_key'] ?? null,
            spacesSecret: $data['spaces_secret'] ?? null,
            spacesRegion: $data['spaces_region'] ?? null,
            spacesBucket: $data['spaces_bucket'] ?? null,
            spacesEndpoint: $data['spaces_endpoint'] ?? null,
            spacesUrl: $data['spaces_url'] ?? null,
            awsKey: $data['aws_key'] ?? null,
            awsSecret: $data['aws_secret'] ?? null,
            awsRegion: $data['aws_region'] ?? null,
            awsBucket: $data['aws_bucket'] ?? null,
            awsUrl: $data['aws_url'] ?? null,
            awsEndpoint: $data['aws_endpoint'] ?? null,
            hetznerKey: $data['hetzner_key'] ?? null,
            hetznerSecret: $data['hetzner_secret'] ?? null,
            hetznerRegion: $data['hetzner_region'] ?? null,
            hetznerBucket: $data['hetzner_bucket'] ?? null,
            hetznerEndpoint: $data['hetzner_endpoint'] ?? null,
            hetznerUrl: $data['hetzner_url'] ?? null,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        $data = [
            'media_disk' => $this->mediaDisk,
            'spaces_key' => $this->spacesKey,
            'spaces_region' => $this->spacesRegion,
            'spaces_bucket' => $this->spacesBucket,
            'spaces_endpoint' => $this->spacesEndpoint,
            'spaces_url' => $this->spacesUrl,
            'aws_key' => $this->awsKey,
            'aws_region' => $this->awsRegion,
            'aws_bucket' => $this->awsBucket,
            'aws_url' => $this->awsUrl,
            'aws_endpoint' => $this->awsEndpoint,
            'hetzner_key' => $this->hetznerKey,
            'hetzner_region' => $this->hetznerRegion,
            'hetzner_bucket' => $this->hetznerBucket,
            'hetzner_endpoint' => $this->hetznerEndpoint,
            'hetzner_url' => $this->hetznerUrl,
        ];

        // Omit secrets when blank so the existing stored values are preserved.
        if ($this->spacesSecret !== null && $this->spacesSecret !== '') {
            $data['spaces_secret'] = $this->spacesSecret;
        }

        if ($this->awsSecret !== null && $this->awsSecret !== '') {
            $data['aws_secret'] = $this->awsSecret;
        }

        if ($this->hetznerSecret !== null && $this->hetznerSecret !== '') {
            $data['hetzner_secret'] = $this->hetznerSecret;
        }

        return $data;
    }
}
