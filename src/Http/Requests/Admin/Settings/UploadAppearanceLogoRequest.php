<?php

namespace Lvntr\StarterKit\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an appearance logo upload (`logo_light` | `logo_dark`).
 *
 * SVG is intentionally excluded from the mime allowlist: it can embed
 * <script>/onload and execute in the app origin when served from the public
 * disk. Keeping the rule in a FormRequest (instead of inline in the controller)
 * makes the SVG-rejection contract unit-testable and adds an `authorize()`
 * permission check that travels with the request wherever it is dispatched.
 */
class UploadAppearanceLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'logo' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:2048',
                'dimensions:max_width=4096,max_height=4096',
            ],
        ];
    }
}
