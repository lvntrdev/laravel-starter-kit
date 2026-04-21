<?php

namespace App\Http\Requests\FileManager;

/**
 * Validation for the folder delete endpoint — context resolution only.
 *
 * Mirrors {@see UpdateFolderRequest} so the controller can surface the
 * shared contextRules() via a typed FormRequest instead of a raw Request,
 * keeping delete in line with the other write-path endpoints.
 */
class DeleteFolderRequest extends FileManagerRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->contextRules();
    }
}
