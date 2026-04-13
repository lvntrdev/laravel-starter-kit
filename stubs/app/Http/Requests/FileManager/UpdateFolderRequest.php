<?php

namespace App\Http\Requests\FileManager;

class UpdateFolderRequest extends FileManagerRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->contextRules(),
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^\/\\\\<>:"|?*\x00-\x1f]+$/',
            ],
        ];
    }
}
