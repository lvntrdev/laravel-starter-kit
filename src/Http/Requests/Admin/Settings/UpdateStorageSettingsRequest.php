<?php

namespace Lvntr\StarterKit\Http\Requests\Admin\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStorageSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.update') ?? false;
    }

    /**
     * The form shows endpoint/URL inputs behind a fixed `https://` addon, so
     * a scheme-less host arrives here; add the scheme before `url` validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['spaces', 'aws', 'hetzner'] as $provider) {
            foreach (['endpoint', 'url'] as $field) {
                $key = "{$provider}_{$field}";
                $value = $this->input($key);

                if (! is_string($value)) {
                    continue;
                }

                $value = rtrim(trim($value), '/');

                if ($value !== '' && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
                    $value = 'https://'.$value;
                }

                $normalized[$key] = $value === '' ? null : $value;
            }
        }

        $this->merge($normalized);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'media_disk' => ['required', 'string', 'in:local,do,s3,hetzner'],
            'spaces_key' => ['nullable', 'string', 'max:255'],
            'spaces_secret' => ['nullable', 'string', 'max:255'],
            'spaces_region' => ['nullable', 'string', 'max:50'],
            'spaces_bucket' => ['nullable', 'string', 'max:255'],
            'spaces_endpoint' => ['nullable', 'url', 'max:255'],
            'spaces_url' => ['nullable', 'url', 'max:255'],
            'aws_key' => ['nullable', 'string', 'max:255'],
            'aws_secret' => ['nullable', 'string', 'max:255'],
            'aws_region' => ['nullable', 'string', 'max:50'],
            'aws_bucket' => ['nullable', 'string', 'max:255'],
            'aws_url' => ['nullable', 'url', 'max:255'],
            'aws_endpoint' => ['nullable', 'url', 'max:255'],
            'hetzner_key' => ['nullable', 'string', 'max:255'],
            'hetzner_secret' => ['nullable', 'string', 'max:255'],
            'hetzner_region' => ['nullable', 'string', 'max:50'],
            'hetzner_bucket' => ['nullable', 'string', 'max:255'],
            'hetzner_endpoint' => ['nullable', 'url', 'max:255'],
            'hetzner_url' => ['nullable', 'url', 'max:255'],
        ];
    }
}
