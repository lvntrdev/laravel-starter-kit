<?php

namespace Lvntr\StarterKit\Domain\ContentLanguage\DTOs;

use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for a content language.
 *
 * Content languages drive the locale tabs of translatable content fields
 * (TranslatableInput) — a separate concept from the admin UI locale, which
 * stays bound to lang files via config('app.languages').
 *
 * Carries validated data from FormRequest to the Action layer.
 */
readonly class ContentLanguageDTO extends BaseDTO
{
    public function __construct(
        public string $code,
        public string $name,
        public string $nativeName,
        public string $direction = 'ltr',
        public ?string $flag = null,
        public bool $isActive = true,
        public bool $isDefault = false,
        public ?string $fallbackCode = null,
        public int $sortOrder = 0,
    ) {}

    /**
     * Create a DTO from an array (typically from FormRequest::validated()).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            code: $data['code'],
            name: $data['name'],
            nativeName: $data['native_name'],
            direction: $data['direction'] ?? 'ltr',
            flag: $data['flag'] ?? null,
            isActive: (bool) ($data['is_active'] ?? true),
            isDefault: (bool) ($data['is_default'] ?? false),
            fallbackCode: $data['fallback_code'] ?? null,
            sortOrder: (int) ($data['sort_order'] ?? 0),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'native_name' => $this->nativeName,
            'direction' => $this->direction,
            'flag' => $this->flag,
            'is_active' => $this->isActive,
            'is_default' => $this->isDefault,
            'fallback_code' => $this->fallbackCode,
            'sort_order' => $this->sortOrder,
        ];
    }
}
