<?php

namespace Lvntr\StarterKit\Domain\FileManager\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Lvntr\StarterKit\Domain\FileManager\Support\ContextRegistry;

/**
 * Authorizes FileManager operations by delegating to the per-context
 * `authorize` closure registered with {@see ContextRegistry}.
 *
 * Abilities used:
 *   - `read`  — list/download
 *   - `write` — upload/rename/move/delete
 */
class FileManagerAuthorizer
{
    public function __construct(private readonly ContextRegistry $registry) {}

    public function authorizeRead(FileManagerContextDTO $context): void
    {
        $this->assert($context, 'read');
    }

    public function authorizeWrite(FileManagerContextDTO $context): void
    {
        $this->assert($context, 'write');
    }

    private function assert(FileManagerContextDTO $context, string $ability): void
    {
        /** @var Authenticatable|null $user */
        $user = Auth::user();

        if ($user === null) {
            throw new AuthorizationException;
        }

        $definition = $this->registry->get($context->context);

        if (! $definition->authorize($user, $ability, $context->owner)) {
            throw new AuthorizationException;
        }
    }
}
