<?php

namespace App\Http\Requests\FileManager;

class StoreFolderRequest extends FileManagerRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->contextRules(),
            'parent_id' => ['nullable', 'uuid'],
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^\/\\\\<>:"|?*\x00-\x1f]+$/',
            ],
        ];
    }
}
