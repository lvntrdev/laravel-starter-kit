<?php

namespace App\Http\Requests\FileManager;

class MoveItemRequest extends FileManagerRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->contextRules(),
            'item_type' => ['required', 'string', 'in:folder,file'],
            'item_id' => ['required'],
            'target_folder_id' => ['nullable', 'uuid'],
        ];
    }
}
