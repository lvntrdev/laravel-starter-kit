<?php

namespace App\Domain\FileManager\Services;

use App\Domain\FileManager\DTOs\FileManagerContextDTO;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

/**
 * Centralized authorization rules for the FileManager module.
 *
 * Rules:
 *  - user context: the authenticated user accessing their own files,
 *    OR a user with the `users.update` permission (admin).
 *  - global context: requires the `files.read` / `files.update` permission.
 */
class FileManagerAuthorizer
{
    public function authorizeRead(FileManagerContextDTO $context): void
    {
        $this->assertRead($context);
    }

    public function authorizeWrite(FileManagerContextDTO $context): void
    {
        $this->assertWrite($context);
    }

    private function assertRead(FileManagerContextDTO $context): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            throw new AuthorizationException;
        }

        if ($context->context === 'user') {
            if ((string) $user->id === $context->ownerId) {
                return;
            }

            if ($user->can('users.read') || $user->can('users.update')) {
                return;
            }

            throw new AuthorizationException;
        }

        if ($context->context === 'global') {
            if ($user->can('files.read') || $user->can('files.update')) {
                return;
            }

            throw new AuthorizationException;
        }

        throw new AuthorizationException;
    }

    private function assertWrite(FileManagerContextDTO $context): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user === null) {
            throw new AuthorizationException;
        }

        if ($context->context === 'user') {
            if ((string) $user->id === $context->ownerId) {
                return;
            }

            if ($user->can('users.update')) {
                return;
            }

            throw new AuthorizationException;
        }

        if ($context->context === 'global') {
            if ($user->can('files.update') || $user->can('files.create') || $user->can('files.delete')) {
                return;
            }

            throw new AuthorizationException;
        }

        throw new AuthorizationException;
    }
}
