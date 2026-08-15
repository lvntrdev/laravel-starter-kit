<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\RegisterDTO;
use App\Enums\RoleEnum;
use App\Exceptions\ApiException;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Laravel\Fortify\Features;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;

/**
 * Action: Register a new user via API.
 *
 * When email verification is enabled, no access token is issued on
 * registration — the caller must verify the email first and then log in.
 * Otherwise an access token is returned immediately (current behavior).
 *
 * The `auth.registration` setting is enforced HERE, not at the route: the
 * route stays registered so a disabled kit answers 403 instead of 404 (a
 * missing route would break the client's error-handling contract), and the
 * check reads the setting directly instead of trusting
 * config('fortify.features') — that config governs Fortify's WEB routes and
 * is only rebuilt once per process, so it can drift from the DB (cached
 * config, a settings flip after boot, a consumer that never registered
 * SettingsServiceProvider). Config gating is the first gate; this is the one
 * that actually holds.
 *
 * @return array{user: User, token?: string, requires_verification: bool}
 */
class RegisterUserAction extends BaseAction
{
    /**
     * @return array{user: User, token?: string, requires_verification: bool}
     *
     * @throws ApiException when registration is disabled in settings
     */
    public function execute(RegisterDTO $dto): array
    {
        // Absent key → enabled, matching SettingsServiceProvider and
        // SettingsDefaultsQuery::auth(); only an explicit '0' closes the door.
        if ((string) Setting::getValue('auth.registration', '1') !== '1') {
            throw ApiException::forbidden('Registration is currently disabled.');
        }

        $user = User::create([
            ...$dto->toArray(),
            'status' => 'active',
        ]);

        $user->assignRole(RoleEnum::User->value);

        if (Features::enabled(Features::emailVerification())) {
            // Trigger Fortify's Registered listener so the verification
            // notification is dispatched. No token until the user verifies.
            event(new Registered($user));

            return [
                'user' => $user,
                'requires_verification' => true,
            ];
        }

        // Verification is off: Fortify never bound verification.notice /
        // verification.verify, so no flow in the app can ever fill this column
        // later. Stamp it now so the row is coherent on its own instead of
        // leaning on User::hasVerifiedEmail()'s feature-flag short-circuit —
        // and so turning verification back on does not retroactively lock out
        // everyone who registered while it was off. `email_verified_at` is
        // deliberately not mass assignable (App\Models\User::$fillable), hence
        // forceFill. Only this new row is touched; existing users keep
        // whatever they have.
        $user->forceFill(['email_verified_at' => now()])->save();

        $token = $user->createToken('auth-token')->accessToken;

        return [
            'user' => $user,
            'token' => $token,
            'requires_verification' => false,
        ];
    }
}
