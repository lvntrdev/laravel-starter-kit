<?php

namespace Lvntr\StarterKit\Domain\Setting\DTOs;

use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for appearance settings.
 *
 * Holds the admin-controlled global appearance defaults: the active build
 * theme, the default accent color, whether dark mode is on by default, and the
 * sidebar surface style. Logo/favicon files are handled by dedicated upload
 * endpoints (not this DTO).
 */
readonly class AppearanceSettingsDTO extends BaseDTO
{
    public function __construct(
        public string $theme,
        public string $accentColor,
        public bool $darkModeDefault,
        public string $sidebarStyle,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            theme: $data['theme'],
            accentColor: $data['accent_color'],
            // FormRequest casts to bool; coerce again here so the DTO is safe
            // to build from any write path (tinker, command, job).
            darkModeDefault: (bool) ($data['dark_mode_default'] ?? false),
            sidebarStyle: $data['sidebar_style'],
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'theme' => $this->theme,
            'accent_color' => $this->accentColor,
            // Persist as the same '1'/'0' string convention used by every other
            // boolean settings group so the read path's `=== '1'` check works.
            'dark_mode_default' => $this->darkModeDefault ? '1' : '0',
            'sidebar_style' => $this->sidebarStyle,
        ];
    }
}
