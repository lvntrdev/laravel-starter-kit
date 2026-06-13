<?php

namespace Lvntr\StarterKit\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a favicon upload.
 *
 * Accepts png/ico only — no svg (script-execution risk on the public disk),
 * no jpeg/webp (favicons are small raster or .ico). `.ico` is not an `image`
 * per the validator's GD check, so the rule is mimes-only, not `image`.
 */
class UploadFaviconRequest extends FormRequest
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
            'favicon' => [
                'required',
                'file',
                'mimes:png,ico',
                'max:512',
            ],
        ];
    }
}
