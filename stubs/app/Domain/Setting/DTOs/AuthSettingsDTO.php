<?php

namespace App\Domain\Setting\DTOs;

use App\Domain\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for authentication settings.
 */
readonly class AuthSettingsDTO extends BaseDTO
{
    public function __construct(
        public string $registration,
        public string $emailVerification,
        public string $twoFactor,
        public string $passwordReset,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            registration: $data['registration'] ? '1' : '0',
            emailVerification: $data['email_verification'] ? '1' : '0',
            twoFactor: $data['two_factor'] ? '1' : '0',
            passwordReset: $data['password_reset'] ? '1' : '0',
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'registration' => $this->registration,
            'email_verification' => $this->emailVerification,
            'two_factor' => $this->twoFactor,
            'password_reset' => $this->passwordReset,
        ];
    }
}
