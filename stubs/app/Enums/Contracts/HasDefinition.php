<?php

namespace App\Enums\Contracts;

/**
 * Contract for enums that act as definition lookups.
 * Provides label, severity, and array serialization for API/frontend consumption.
 */
interface HasDefinition
{
    public function label(): string;

    public function severity(): string;

    /**
     * @return array<int, array{value: string|int, label: string, severity: string}>
     */
    public static function toArray(): array;
}
