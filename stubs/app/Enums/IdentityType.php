<?php

namespace App\Enums;

use App\Enums\Contracts\HasDefinition;

enum IdentityType: int implements HasDefinition
{
    case Turkey = 1;
    case Foreign = 2;

    public function label(): string
    {
        return match ($this) {
            self::Turkey => __('enums.identity_type.turkey'),
            self::Foreign => __('enums.identity_type.foreign'),
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Turkey => 'contrast',
            self::Foreign => 'green,soft',
        };
    }

    /**
     * @return array<int, array{value: int, label: string, severity: string}>
     */
    public static function toArray(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'severity' => $case->severity(),
        ], self::cases());
    }
}
