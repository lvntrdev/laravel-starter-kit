<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThemeSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Whitelist source of truth: the named preset registry in
        // config('starter-kit.themes'). Any value outside this list is
        // rejected with 422 so an arbitrary string can never be persisted
        // and later applied as a runtime preset.
        $allowed = array_keys(config('starter-kit.themes', ['default' => null]));

        return [
            'theme' => ['required', 'string', Rule::in($allowed)],
        ];
    }
}
