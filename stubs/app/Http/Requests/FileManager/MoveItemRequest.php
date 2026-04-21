<?php

namespace App\Http\Requests\FileManager;

use Illuminate\Validation\Rule;

class MoveItemRequest extends FileManagerRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $itemType = $this->input('item_type');

        $itemIdRules = ['required'];
        if ($itemType === 'file') {
            // Media IDs are integer primary keys — reject strings / "0" /
            // negative values that would otherwise silently cast to 0.
            $itemIdRules = ['required', 'integer', 'min:1'];
        } elseif ($itemType === 'folder') {
            $itemIdRules = ['required', 'uuid'];
        }

        return [
            ...$this->contextRules(),
            'item_type' => ['required', 'string', Rule::in(['folder', 'file'])],
            'item_id' => $itemIdRules,
            'target_folder_id' => ['nullable', 'uuid'],
        ];
    }
}
