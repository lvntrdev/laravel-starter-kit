<?php

namespace App\Domain\Setting\DTOs;

use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for appearance (theme) settings.
 */
readonly class ThemeSettingsDTO extends BaseDTO
{
    public function __construct(
        public string $theme,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            theme: $data['theme'] ?? 'default',
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'theme' => $this->theme,
        ];
    }
}
