<?php

namespace App\Http\Requests\FileManager;

class BulkDeleteRequest extends FileManagerRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->contextRules(),
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', 'in:folder,file'],
            'items.*.id' => ['required'],
        ];
    }
}
