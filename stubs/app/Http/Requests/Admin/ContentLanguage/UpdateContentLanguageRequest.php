<?php

namespace App\Http\Requests\Admin\ContentLanguage;

use App\Models\ContentLanguage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation rules for updating an existing content language.
 *
 * Same shape as StoreContentLanguageRequest, with two differences:
 *   - the code uniqueness rule ignores the record being updated, so re-saving
 *     a row without changing its code does not collide with itself;
 *   - fallback_code may not point at the record itself (a language cannot fall
 *     back to itself).
 *
 * Defense in depth: the route already carries check.permission:settings.update
 * (content languages are a setting — no dedicated permission resource); the
 * FormRequest enforces the permission directly as a second line.
 */
class UpdateContentLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /**
     * Normalise the locale code before validation (see StoreContentLanguageRequest).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('code') && is_string($this->input('code'))) {
            $this->merge([
                'code' => str_replace('_', '-', strtolower(trim($this->input('code')))),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ContentLanguage $contentLanguage */
        $contentLanguage = $this->route('contentLanguage');

        return [
            'code' => [
                'required',
                'string',
                'max:35',
                'regex:/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/',
                Rule::unique('content_languages', 'code')->ignore($contentLanguage),
            ],
            'name' => ['required', 'string', 'max:255'],
            'native_name' => ['required', 'string', 'max:255'],
            'direction' => ['required', 'string', Rule::in(['ltr', 'rtl'])],
            'flag' => ['nullable', 'string', 'max:255'],
            // Full-replace (PUT) semantics: these must be present so a partial
            // payload can't let the DTO defaults (is_active=true, is_default=false,
            // sort_order=0) silently overwrite the stored values.
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'fallback_code' => [
                'nullable',
                'string',
                'exists:content_languages,code',
                // A language must not fall back to itself — that would create a
                // one-node cycle in the (data-only) fallback chain.
                Rule::notIn([$contentLanguage?->code]),
            ],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('sk-content-languages.fields.code'),
            'name' => __('sk-content-languages.fields.name'),
            'native_name' => __('sk-content-languages.fields.native_name'),
            'direction' => __('sk-content-languages.fields.direction'),
            'flag' => __('sk-content-languages.fields.flag'),
            'is_active' => __('sk-content-languages.fields.is_active'),
            'is_default' => __('sk-content-languages.fields.is_default'),
            'fallback_code' => __('sk-content-languages.fields.fallback_code'),
            'sort_order' => __('sk-content-languages.fields.sort_order'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fallback_code.not_in' => __('sk-content-languages.errors.fallback_self'),
        ];
    }
}
