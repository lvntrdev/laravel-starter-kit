<?php

namespace Lvntr\StarterKit\Domain\Setting\DTOs;

use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;

/**
 * Data Transfer Object for authentication settings.
 *
 * Backward-compatibility contract: the four legacy toggles are always
 * present (the FormRequest keeps them `required`), while the newer keys
 * (login throttle + password policy) are OPTIONAL. A consumer with a
 * customized SecurityTab may still POST only the four legacy fields —
 * absent keys stay `null` here and are OMITTED from toArray(), so
 * `SettingService::setGroup()` never overwrites previously stored values
 * (and never resets an admin-tightened password policy back to defaults).
 * Behavior for keys absent from the DB is defined by the fallbacks in
 * SettingsDefaultsQuery::auth() and the consumer's SettingsServiceProvider.
 */
readonly class AuthSettingsDTO extends BaseDTO
{
    public function __construct(
        public string $registration,
        public string $emailVerification,
        public string $twoFactor,
        public string $passwordReset,
        public ?string $loginThrottle = null,
        public ?string $passwordMinLength = null,
        public ?string $passwordExpiryDays = null,
        public ?string $passwordRequireMixedCase = null,
        public ?string $passwordRequireNumbers = null,
        public ?string $passwordRequireSymbols = null,
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
            loginThrottle: self::optionalBool($data, 'login_throttle'),
            passwordMinLength: self::optionalInt($data, 'password_min_length'),
            passwordExpiryDays: self::optionalInt($data, 'password_expiry_days'),
            passwordRequireMixedCase: self::optionalBool($data, 'password_require_mixed_case'),
            passwordRequireNumbers: self::optionalBool($data, 'password_require_numbers'),
            passwordRequireSymbols: self::optionalBool($data, 'password_require_symbols'),
        );
    }

    /**
     * Settings rows are persisted as strings (existing Setting pattern);
     * null means "not submitted" and is filtered out of the payload.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'registration' => $this->registration,
            'email_verification' => $this->emailVerification,
            'two_factor' => $this->twoFactor,
            'password_reset' => $this->passwordReset,
            'login_throttle' => $this->loginThrottle,
            'password_min_length' => $this->passwordMinLength,
            'password_expiry_days' => $this->passwordExpiryDays,
            'password_require_mixed_case' => $this->passwordRequireMixedCase,
            'password_require_numbers' => $this->passwordRequireNumbers,
            'password_require_symbols' => $this->passwordRequireSymbols,
        ], static fn (?string $value): bool => $value !== null);
    }

    /**
     * '1'/'0' when the key was submitted, null when absent.
     *
     * @param  array<string, mixed>  $data
     */
    private static function optionalBool(array $data, string $key): ?string
    {
        if (! array_key_exists($key, $data)) {
            return null;
        }

        return $data[$key] ? '1' : '0';
    }

    /**
     * Stringified integer when the key was submitted, null when absent.
     *
     * @param  array<string, mixed>  $data
     */
    private static function optionalInt(array $data, string $key): ?string
    {
        if (! array_key_exists($key, $data)) {
            return null;
        }

        return (string) (int) $data[$key];
    }
}
