<?php

namespace App\Domain\Role\DTOs;

use App\Domain\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for creating or updating a role.
 * Carries validated data from FormRequest to Action layer.
 */
readonly class RoleDTO extends BaseDTO
{
    /**
     * @param  array<string, string>  $displayName
     * @param  array<string>  $permissions
     */
    public function __construct(
        public string $name,
        public array $displayName = [],
        public array $permissions = [],
        public ?string $group = null,
    ) {}

    /**
     * Create a DTO from an array (typically from FormRequest::validated()).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            name: $data['name'],
            displayName: $data['display_name'] ?? [],
            permissions: $data['permissions'] ?? [],
            group: $data['group'] ?? null,
        );
    }

    /**
     * Convert to array for role model persistence (excludes permissions).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'guard_name' => 'web',
            'display_name' => $this->displayName,
            'group' => $this->group,
        ];
    }
}
