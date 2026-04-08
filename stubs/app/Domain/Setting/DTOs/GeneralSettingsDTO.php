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
        public string $timezone,
        public string $languages,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            appName: $data['app_name'],
            timezone: $data['timezone'],
            languages: implode(',', $data['languages']),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'app_name' => $this->appName,
            'timezone' => $this->timezone,
            'languages' => $this->languages,
        ];
    }
}
