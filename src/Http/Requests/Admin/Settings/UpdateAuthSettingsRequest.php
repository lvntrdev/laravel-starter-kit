<?php

namespace Lvntr\StarterKit\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAuthSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /**
     * The newer keys (login throttle + password policy) are `sometimes`,
     * NOT `required`: a consumer with a customized SecurityTab may still
     * POST only the four legacy toggles and must not get a 422. Absent
     * keys are omitted from persistence (see AuthSettingsDTO) and fall
     * back to behavior-preserving defaults at read time.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registration' => ['required', 'boolean'],
            'email_verification' => ['required', 'boolean'],
            'two_factor' => ['required', 'boolean'],
            'password_reset' => ['required', 'boolean'],
            'login_throttle' => ['sometimes', 'boolean'],
            'password_min_length' => ['sometimes', 'integer', 'min:6', 'max:128'],
            'password_expiry_days' => ['sometimes', 'integer', 'min:0', 'max:3650'],
            'password_require_mixed_case' => ['sometimes', 'boolean'],
            'password_require_numbers' => ['sometimes', 'boolean'],
            'password_require_symbols' => ['sometimes', 'boolean'],
        ];
    }
}
