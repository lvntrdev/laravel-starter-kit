<?php

namespace App\Http\Requests\Admin\SampleContent;

use App\Support\HasTranslatableRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreSampleContentRequest extends FormRequest
{
    use HasTranslatableRules;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...$this->translatableRules('title', ['required', 'string', 'max:255']),
            ...$this->translatableRules('description', ['nullable', 'string', 'max:5000']),
            ...$this->translatableRules('content', ['nullable', 'string']),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return $this->translatableAttributes([
            'title' => __('sample-contents.fields.title'),
            'description' => __('sample-contents.fields.description'),
            'content' => __('sample-contents.fields.content'),
        ]);
    }
}
