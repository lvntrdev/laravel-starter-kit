<?php

namespace Lvntr\StarterKit\Http\Requests\FileManager;

use Lvntr\StarterKit\Domain\FileManager\Services\FileManagerAuthorizer;

/**
 * Read-only FileManager requests that only need context resolution.
 *
 * FileManager routes deliberately bypass route-level permission middleware;
 * {@see FileManagerAuthorizer} is the only authorization gate. This request
 * only ensures the context payload is structurally valid before it reaches the
 * controller, turning InvalidArgumentException (500) into a proper 422 envelope.
 */
class FileManagerContextRequest extends FileManagerRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->contextRules();
    }
}
