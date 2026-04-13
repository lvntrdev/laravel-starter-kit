<?php

namespace App\Http\Requests\FileManager;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared base: every FileManager request resolves its context from query/body.
 */
abstract class FileManagerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function context(): FileManagerContextDTO
    {
        return FileManagerContextDTO::fromArray([
            'context' => (string) $this->input('context', $this->query('context')),
            'context_id' => $this->input('context_id', $this->query('context_id')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function contextRules(): array
    {
        return [
            'context' => ['required', 'string', 'in:user,global'],
            'context_id' => ['nullable', 'uuid', 'required_if:context,user'],
        ];
    }
}
