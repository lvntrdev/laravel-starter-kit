<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateAuthSettingsRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registration' => ['required', 'boolean'],
            'email_verification' => ['required', 'boolean'],
            'two_factor' => ['required', 'boolean'],
            'password_reset' => ['required', 'boolean'],
        ];
    }
}
