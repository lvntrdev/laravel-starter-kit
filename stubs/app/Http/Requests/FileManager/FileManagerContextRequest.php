<?php

namespace App\Http\Requests\FileManager;

use App\Domain\FileManager\Services\FileManagerAuthorizer;

/**
 * Read-only FileManager requests that only need context resolution.
 *
 * Authorization is handled by route-level permission middleware and
 * {@see FileManagerAuthorizer}. This request
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
