<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Laravel\Fortify\Features;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        if (($input['timezone'] ?? null) === '') {
            $input['timezone'] = null;
        }

        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'timezone' => ['nullable', 'string', 'timezone'],
        ])->validateWithBag('updateProfileInformation');

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail &&
            Features::enabled(Features::emailVerification())) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $user->forceFill([
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                ...$this->timezonePatch($input),
            ])->save();
        }
    }

    /**
     * The timezone attribute, but ONLY when the caller actually submitted it.
     *
     * `$input['timezone'] ?? null` would look equivalent and is not: a form
     * that omits the field entirely — every profile form until the timezone
     * selector ships, and any API client that posts only name and email —
     * would silently wipe the user's stored preference. An omitted field
     * means "leave it alone"; a submitted empty one means "follow the site
     * default" and is normalized to null before validation.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string|null>
     */
    private function timezonePatch(array $input): array
    {
        return array_key_exists('timezone', $input)
            ? ['timezone' => $input['timezone']]
            : [];
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'first_name' => $input['first_name'],
            'last_name' => $input['last_name'],
            'email' => $input['email'],
            ...$this->timezonePatch($input),
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
