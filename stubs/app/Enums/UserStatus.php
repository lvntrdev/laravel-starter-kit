<?php

namespace App\Enums;

use App\Enums\Attributes\InertiaShared;
use App\Enums\Contracts\HasDefinition;

#[InertiaShared]
enum UserStatus: string implements HasDefinition
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Banned = 'banned';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('enums.user_status.active'),
            self::Inactive => __('enums.user_status.inactive'),
            self::Banned => __('enums.user_status.banned'),
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'danger',
            self::Banned => 'contrast',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, severity: string}>
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
