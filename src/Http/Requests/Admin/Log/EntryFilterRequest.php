<?php

namespace Lvntr\StarterKit\Http\Requests\Admin\Log;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the filter / pagination payload for the Show page entry stream.
 * All fields are optional — empty payload returns the first page of every entry.
 */
class EntryFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('system_admin') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'levels' => ['nullable', 'array'],
            'levels.*' => ['string', 'in:emergency,alert,critical,error,warning,notice,info,debug'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'keyword' => ['nullable', 'string', 'max:200'],
            'cursor' => ['nullable', 'integer', 'min:0'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:500'],
        ];
    }
}
