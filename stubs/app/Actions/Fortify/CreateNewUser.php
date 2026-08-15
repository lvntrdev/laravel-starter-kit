<?php

namespace App\Actions\Fortify;

use App\Enums\RoleEnum;
use App\Models\Setting;
use App\Models\User;
use App\Rules\TurnstileRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Features;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * The `auth.registration` setting is re-checked here, not only through
     * config('fortify.features'). SettingsServiceProvider gates that config in
     * a booting() callback so Fortify never registers POST /register while
     * registration is off — but that config is computed once per process and
     * can drift from the DB (cached config, a settings flip after boot, a
     * consumer app that dropped SettingsServiceProvider). This action is the
     * last gate before a row is written, so it fails closed on its own.
     * Fortify's registerView() only guards the GET page; the POST endpoint has
     * no view hook, which is why the check belongs here.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        // Absent key → enabled, matching SettingsServiceProvider and
        // SettingsDefaultsQuery::auth(); only an explicit '0' closes the door.
        abort_unless((string) Setting::getValue('auth.registration', '1') === '1', 403);

        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
            'cf_turnstile_response' => [new TurnstileRule],
        ])->validate();

        $user = User::create([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // With email verification disabled there is no verification.notice /
        // verification.verify route to send the user to (Fortify only binds
        // them while the feature is on), so nothing in the app can ever fill
        // this column afterwards. Stamp it at creation so the row is coherent
        // on its own instead of leaning on User::hasVerifiedEmail()'s
        // feature-flag short-circuit — and so re-enabling verification later
        // does not retroactively lock out everyone who signed up while it was
        // off. `email_verified_at` is deliberately not mass assignable
        // (see App\Models\User::$fillable), hence forceFill.
        //
        // Only new rows: existing users are never touched here.
        if (! Features::enabled(Features::emailVerification())) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->assignRole(RoleEnum::User->value);

        return $user;

    }
}
