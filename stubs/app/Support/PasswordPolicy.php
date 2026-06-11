<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Single source of truth for password strength enforcement.
 *
 * Policy values are bridged from the admin Security settings (DB) into
 * `starter-kit.password_policy.*` runtime config by SettingsServiceProvider
 * (in an app `booting` callback, before any provider boots). This class is
 * consumed from two registration points so EVERY password write path shares
 * one policy:
 *
 *   - AppServiceProvider registers it as the Password::defaults() resolver,
 *     so every FormRequest using `Password::defaults()` (admin + API user
 *     CRUD, generated domains) picks it up lazily at validation time.
 *   - The PasswordValidationRules trait (Fortify register / reset / update
 *     flows) calls it directly.
 *
 * When the settings bridge has not run (no settings table yet — e.g. artisan
 * during install), it falls back to the kit's hardened static baseline (the
 * pre-policy behavior): min 10 chars + mixed case + letters + numbers +
 * symbols. Fail-closed: absent configuration can only yield the strict
 * baseline, never a weaker policy.
 */
class PasswordPolicy
{
    /**
     * Build the password strength rule from the bridged runtime config.
     */
    public static function rule(): Password
    {
        $min = config('starter-kit.password_policy.min_length');

        if ($min === null) {
            // Settings bridge absent — hardened static baseline.
            return Password::min(10)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();
        }

        // The bridge already clamps to >= 1; re-clamp here as defense in
        // depth so Password::min(0) can never be constructed.
        $rule = Password::min(max(1, (int) $min));

        if (config('starter-kit.password_policy.require_mixed_case')) {
            $rule->mixedCase();
        }

        if (config('starter-kit.password_policy.require_numbers')) {
            $rule->numbers();
        }

        if (config('starter-kit.password_policy.require_symbols')) {
            $rule->symbols();
        }

        return $rule;
    }
}
