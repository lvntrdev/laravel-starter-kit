<?php

namespace App\Http\Requests\Admin\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFileManagerSettingsRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|int>>
     */
    public function rules(): array
    {
        return [
            'max_size_kb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'accepted_mimes' => ['required', 'array', 'min:1'],
            'accepted_mimes.*' => ['string', 'max:255'],
            'allow_video' => ['required', 'boolean'],
            'allow_audio' => ['required', 'boolean'],
        ];
    }
}
