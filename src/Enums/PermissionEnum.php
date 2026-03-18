<?php

namespace Lvntr\StarterKit\Enums;

/**
 * Base permission abilities.
 * Combined with resources to form full permissions like "users.create".
 */
enum PermissionEnum: string
{
    case Create = 'create';
    case Read = 'read';
    case Update = 'update';
    case Delete = 'delete';
    case Import = 'import';
    case Export = 'export';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Create',
            self::Read => 'Read',
            self::Update => 'Update',
            self::Delete => 'Delete',
            self::Import => 'Import',
            self::Export => 'Export',
        };
    }

    /**
     * Generate a resource-scoped permission string.
     * Example: PermissionEnum::Create->for('users') => 'users.create'
     */
    public function for(string $resource): string
    {
        return "{$resource}.{$this->value}";
    }

    /**
     * Generate all abilities for a given resource.
     *
     * @param  string[]|null  $abilities  Subset of abilities, or null for all
     * @return string[]
     */
    public static function allFor(string $resource, ?array $abilities = null): array
    {
        $cases = $abilities
            ? array_filter(self::cases(), fn (self $case) => in_array($case->value, $abilities))
            : self::cases();

        return array_map(fn (self $case) => $case->for($resource), $cases);
    }
}
