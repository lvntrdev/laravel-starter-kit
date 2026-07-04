<?php

namespace Lvntr\StarterKit\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an application logo upload.
 *
 * SVG is intentionally excluded from the mime allowlist: it can embed
 * <script>/onload and execute in the app origin when served from the public
 * disk.
 */
class UploadLogoRequest extends FormRequest
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
