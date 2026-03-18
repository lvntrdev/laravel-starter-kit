<?php

namespace App\Domain\Setting\DTOs;

use App\Domain\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for general settings.
 */
readonly class GeneralSettingsDTO extends BaseDTO
{
    public function __construct(
        public string $appName,
        public string $appUrl,
        public string $timezone,
        public string $languages,
        public string $debug,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            appName: $data['app_name'],
            appUrl: $data['app_url'],
            timezone: $data['timezone'],
            languages: implode(',', $data['languages']),
            debug: $data['debug'] ? '1' : '0',
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'app_name' => $this->appName,
            'app_url' => $this->appUrl,
            'timezone' => $this->timezone,
            'languages' => $this->languages,
            'debug' => $this->debug,
        ];
    }
}
