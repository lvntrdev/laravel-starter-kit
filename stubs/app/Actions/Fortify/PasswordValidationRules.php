<?php

namespace App\Actions\Fortify;

use App\Support\PasswordPolicy;
use Illuminate\Contracts\Validation\Rule;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * Strength constraints come from the admin-configured password policy
     * (Settings → Security) via PasswordPolicy::rule() — the same dynamic
     * rule registered as Password::defaults() in AppServiceProvider, so the
     * Fortify flows (register, reset, update) and the admin/API user CRUD
     * requests enforce one and the same policy.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', PasswordPolicy::rule(), 'confirmed'];
    }
}
