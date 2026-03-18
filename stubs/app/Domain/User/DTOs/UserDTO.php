<?php

namespace App\Domain\User\DTOs;

use App\Domain\Shared\DTOs\BaseDTO;
use App\Enums\UserStatus;

/**
 * Data Transfer Object for creating or updating a user.
 * Carries validated data from FormRequest to Action layer.
 */
readonly class UserDTO extends BaseDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?string $password = null,
        public UserStatus $status = UserStatus::Active,
        public ?string $role = null,
        public ?string $gender = null,
    ) {}

    /**
     * Create a DTO from an array (typically from FormRequest::validated()).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            email: $data['email'],
            password: $data['password'] ?? null,
            status: UserStatus::from($data['status'] ?? UserStatus::Active->value),
            role: $data['role'] ?? null,
            gender: $data['gender'] ?? null,
        );
    }

    /**
     * Convert to array, excluding null password (for updates without password change).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'status' => $this->status->value,
            'gender' => $this->gender,
        ];

        if ($this->password !== null) {
            $data['password'] = $this->password;
        }

        return $data;
    }
}
