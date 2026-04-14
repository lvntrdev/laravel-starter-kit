<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization rules for actions on User records.
 *
 * Used both by explicit policy calls (`$authUser->can('view', $user)`) and by
 * FileManager's auto-resolved `user` context, where the default authorizer
 * delegates to `view` (read) and `update` (write).
 */
class UserPolicy
{
    /**
     * Viewing a user's profile / files.
     * Allowed for the user themselves OR any principal with the relevant permission.
     */
    public function view(User $actor, User $user): bool
    {
        if ($actor->is($user)) {
            return true;
        }

        return $actor->can('users.read') || $actor->can('users.update');
    }

    /**
     * Mutating a user's profile / files.
     * Self is always allowed; otherwise requires `users.update`.
     */
    public function update(User $actor, User $user): bool
    {
        if ($actor->is($user)) {
            return true;
        }

        return $actor->can('users.update');
    }
}
