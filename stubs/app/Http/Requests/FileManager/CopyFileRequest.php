<?php

namespace App\Http\Requests\FileManager;

class CopyFileRequest extends FileManagerRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->contextRules(),
            'target_folder_id' => [
                'nullable',
                'string',
                'uuid',
            ],
        ];
    }
}
