<?php

namespace Lvntr\StarterKit\Domain\Shared\DTOs;

/**
 * Base Data Transfer Object.
 * All domain DTOs should extend this class.
 *
 * DTOs carry data between layers (Controller → Action → Repository)
 * and enforce immutability via readonly properties.
 */
abstract readonly class BaseDTO
{
    /**
     * Create a DTO instance from an array of attributes.
     *
     * @param  array<string, mixed>  $data
     */
    abstract public static function fromArray(array $data): static;
}
