<?php

namespace App\Actions\Fortify;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * The `auth.password_reset` setting is re-checked here, not only through
     * config('fortify.features'). SettingsServiceProvider gates that config in
     * a booting() callback so Fortify never registers POST /reset-password
     * while the feature is off — but that config is computed once per process
     * and can drift from the DB (cached config, a settings flip after boot, a
     * consumer app that dropped SettingsServiceProvider). This action is the
     * last gate before the password column is overwritten, so it fails closed
     * on its own. Fortify's resetPasswordView() only guards the GET page; the
     * POST endpoint has no view hook, which is why the check belongs here.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        // Absent key → enabled, matching SettingsServiceProvider and
        // SettingsDefaultsQuery::auth(); only an explicit '0' closes the door.
        abort_unless((string) Setting::getValue('auth.password_reset', '1') === '1', 403);

        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
