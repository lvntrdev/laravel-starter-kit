<?php

namespace App\Enums;

use App\Enums\Attributes\InertiaShared;
use App\Enums\Contracts\HasDefinition;

#[InertiaShared]
enum YesNo: int implements HasDefinition
{
    case Yes = 1;
    case No = 0;

    public function label(): string
    {
        return match ($this) {
            self::Yes => __('enums.yes_no.yes'),
            self::No => __('enums.yes_no.no'),
        };
    }

    public function severity(): string
    {
        return match ($this) {
            self::Yes => 'success',
            self::No => 'danger',
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
