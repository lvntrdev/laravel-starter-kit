<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTurnstileSettingsRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'site_key' => ['required_if:enabled,true', 'nullable', 'string', 'max:255'],
            'secret_key' => ['required_if:enabled,true', 'nullable', 'string', 'max:255'],
        ];
    }
}
