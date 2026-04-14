<?php

namespace App\Domain\FileManager\Services;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Domain\FileManager\Support\ContextRegistry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

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
        /** @var User|null $user */
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
