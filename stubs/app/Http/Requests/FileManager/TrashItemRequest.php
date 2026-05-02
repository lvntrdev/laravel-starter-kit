<?php

namespace App\Http\Requests\FileManager;

/**
 * Shared request for restoring a single trash item and for permanently
 * deleting one. Validates the context plus the target item's type and id.
 */
class TrashItemRequest extends FileManagerRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->contextRules(),
            'item_type' => ['required', 'string', 'in:folder,file'],
            'item_id' => ['required', 'string', 'max:64'],
        ];
    }
}
