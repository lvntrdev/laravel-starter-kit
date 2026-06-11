<?php

namespace Lvntr\StarterKit\Domain\Setting\DTOs;

use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for general settings.
 */
readonly class GeneralSettingsDTO extends BaseDTO
{
    public function __construct(
        public string $appName,
        public string $timezone,
        public string $languages,
        public ?string $welcomeMessage,
        public ?string $tagline = null,
        public string $adminEmail = '',
        public ?string $supportEmail = null,
        public string $defaultLanguage = '',
        public string $currency = 'TRY',
        public string $dateFormat = 'd.m.Y',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $languages = $data['languages'];

        return new static(
            appName: $data['app_name'],
            timezone: $data['timezone'],
            languages: implode(',', $languages),
            welcomeMessage: $data['welcome_message'] ?? null,
            tagline: $data['tagline'] ?? null,
            adminEmail: $data['admin_email'] ?? '',
            supportEmail: $data['support_email'] ?? null,
            // Fall back to the first active language when not explicitly chosen.
            defaultLanguage: $data['default_language'] ?? ($languages[0] ?? ''),
            currency: $data['currency'] ?? 'TRY',
            dateFormat: $data['date_format'] ?? 'd.m.Y',
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'app_name' => $this->appName,
            'timezone' => $this->timezone,
            'languages' => $this->languages,
            'welcome_message' => $this->welcomeMessage,
            'tagline' => $this->tagline,
            'admin_email' => $this->adminEmail,
            'support_email' => $this->supportEmail,
            'default_language' => $this->defaultLanguage,
            'currency' => $this->currency,
            'date_format' => $this->dateFormat,
        ];
    }
}
