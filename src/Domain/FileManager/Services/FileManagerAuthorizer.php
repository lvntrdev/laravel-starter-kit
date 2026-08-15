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
 * One ability per CRUD verb — they are never collapsed into each other:
 *   - `read`   — tree/contents/favorites/trash listing, download
 *   - `create` — upload, create folder, copy file
 *   - `update` — rename, move, favorite toggle, restore from trash
 *   - `delete` — delete file/folder, bulk delete, empty trash, permanent delete
 *
 * Historically only `read` and `write` existed and every mutation resolved
 * through `write`, so a role holding a single mutating permission could
 * perform every other mutation. The four methods below pass the real ability
 * name to the context closure, which is what makes the built-in `global`
 * context map one permission per ability ({@see ContextRegistry}).
 *
 * Every context closure — built-in, auto-resolved or consumer-registered —
 * now receives one of the four names above instead of `write`.
 */
class FileManagerAuthorizer
{
    public function __construct(private readonly ContextRegistry $registry) {}

    public function authorizeRead(FileManagerContextDTO $context): void
    {
        $this->assert($context, 'read');
    }

    public function authorizeCreate(FileManagerContextDTO $context): void
    {
        $this->assert($context, 'create');
    }

    public function authorizeUpdate(FileManagerContextDTO $context): void
    {
        $this->assert($context, 'update');
    }

    public function authorizeDelete(FileManagerContextDTO $context): void
    {
        $this->assert($context, 'delete');
    }

    /**
     * @deprecated Use the explicit ability method that matches the operation
     *             (`authorizeCreate`, `authorizeUpdate`, `authorizeDelete`).
     *             Kept so consumer code written against the old two-ability
     *             surface keeps resolving; it asserts the narrowest mutating
     *             ability (`update`), never create or delete.
     */
    public function authorizeWrite(FileManagerContextDTO $context): void
    {
        $this->authorizeUpdate($context);
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
