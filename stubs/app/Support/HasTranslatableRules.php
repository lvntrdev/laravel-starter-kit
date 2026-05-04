<?php

declare(strict_types=1);

namespace App\Support;

/*
 * Kullanım:
 * class StoreProductRequest extends FormRequest
 * {
 *     use HasTranslatableRules;
 *
 *     public function rules(): array
 *     {
 *         return [
 *             ...$this->translatableRules('name', ['required', 'string', 'max:255']),
 *             ...$this->translatableRules('description', ['nullable', 'string']),
 *             'price' => ['required', 'numeric'],
 *         ];
 *     }
 *
 *     public function attributes(): array
 *     {
 *         return $this->translatableAttributes([
 *             'name' => __('product.name'),
 *             'description' => __('product.description'),
 *         ]);
 *     }
 * }
 */
trait HasTranslatableRules
{
    /**
     * Translatable bir alan için locale bazında dinamik validation kuralları üret.
     *
     * @param  array<int, string|object>  $rules  Tüm dillere uygulanacak temel kurallar.
     * @param  array{primary?: array<int, string|object>, optional?: array<int, string|object>, only?: list<string>, except?: list<string>}  $options
     * @return array<string, array<int, string|object>>
     */
    protected function translatableRules(string $attribute, array $rules, array $options = []): array
    {
        $locales = sk_locale_keys();
        $only = $options['only'] ?? null;
        $except = $options['except'] ?? [];

        if ($only !== null) {
            $locales = array_values(array_intersect($locales, $only));
        }
        $locales = array_values(array_diff($locales, $except));

        $primaryRules = $options['primary'] ?? $rules;
        $optionalRules = $options['optional'] ?? array_map(
            fn ($r) => is_string($r) && $r === 'required' ? 'nullable' : $r,
            $rules,
        );

        $primaryLocale = sk_default_locale();
        $output = [];

        foreach ($locales as $locale) {
            $output["{$attribute}.{$locale}"] = $locale === $primaryLocale ? $primaryRules : $optionalRules;
        }

        return $output;
    }

    /**
     * Translatable attribute label'larını locale başına generate et.
     *
     * @param  array<string, string>  $attributes  ['name' => 'İsim', 'description' => 'Açıklama']
     * @return array<string, string>
     */
    protected function translatableAttributes(array $attributes): array
    {
        $output = [];
        foreach ($attributes as $field => $label) {
            foreach (sk_locale_keys() as $locale) {
                $localeName = config("app.available_languages.{$locale}", strtoupper($locale));
                $output["{$field}.{$locale}"] = "{$label} ({$localeName})";
            }
        }

        return $output;
    }
}
