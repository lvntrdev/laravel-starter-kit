<?php

namespace Lvntr\StarterKit\Http\Requests\Admin\ContentLanguage;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation rules for creating a new content language.
 *
 * Content languages drive the locale tabs of translatable content fields — a
 * separate concept from the admin UI locale (config('app.languages')). The
 * record itself carries no translatable fields, so name/native_name are plain
 * strings.
 *
 * Defense in depth: the route already carries check.permission:settings.update
 * (content languages are a setting — no dedicated permission resource), but the
 * FormRequest enforces the permission directly so the action stays protected
 * even if the route were ever misconfigured.
 */
class StoreContentLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /**
     * Normalise the locale code before validation so the unique/format rules
     * compare against a canonical value and the stored code is consistent
     * (lowercase, region separator normalised to a hyphen — e.g. "EN_US" →
     * "en-us").
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
        return [
            'code' => [
                'required',
                'string',
                'max:35',
                // Lowercase locale format: a primary subtag, optionally followed
                // by hyphen-separated subtags (e.g. "en", "en-us", "zh-hant").
                'regex:/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/',
                'unique:content_languages,code',
            ],
            'name' => ['required', 'string', 'max:255'],
            'native_name' => ['required', 'string', 'max:255'],
            'direction' => ['required', 'string', Rule::in(['ltr', 'rtl'])],
            'flag' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'fallback_code' => ['nullable', 'string', 'exists:content_languages,code'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
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
}
