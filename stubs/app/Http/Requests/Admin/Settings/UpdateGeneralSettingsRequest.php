<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Lvntr\StarterKit\Domain\Shared\Services\DefinitionService;
use Lvntr\StarterKit\Support\HtmlSanitizer;

class UpdateGeneralSettingsRequest extends FormRequest
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
        $definitions = app(DefinitionService::class);
        // Allowed currency/date-format values are the single source of truth in
        // the `currency` / `dateFormat` definitions — not hardcoded here.
        $currencyValues = array_column($definitions->get('currency'), 'value');
        $dateFormatValues = array_column($definitions->get('dateFormat'), 'value');

        return [
            'app_name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'timezone' => ['required', 'string', 'max:100'],
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['required', 'string', 'max:10'],
            // The default language must be one of the languages enabled above.
            'default_language' => ['required', 'string', 'max:10', Rule::in($this->input('languages', []))],
            'currency' => array_filter(['required', 'string', $currencyValues ? Rule::in($currencyValues) : null]),
            'date_format' => array_filter(['required', 'string', $dateFormatValues ? Rule::in($dateFormatValues) : null]),
            'welcome_message' => ['nullable', 'string', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('welcome_message')) {
            return;
        }

        $cleaned = HtmlSanitizer::clean((string) $this->input('welcome_message'));

        $this->merge([
            'welcome_message' => $cleaned === '' ? null : $cleaned,
        ]);
    }
}
