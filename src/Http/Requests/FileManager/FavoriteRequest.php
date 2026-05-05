<?php

namespace Lvntr\StarterKit\Http\Requests\FileManager;

/**
 * Shared request for adding and removing favorites.
 * Validates the context + the favoritable item type and id.
 */
class FavoriteRequest extends FileManagerRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->contextRules(),
            'favoritable_type' => ['required', 'string', 'in:folder,file'],
            'favoritable_id' => ['required', 'string', 'max:64'],
        ];
    }
}
