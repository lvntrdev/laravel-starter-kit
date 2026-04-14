<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Authorization rules for Role records.
 *
 * This is an additive layer on top of the route-level `check.permission`
 * middleware: existing middleware-only flows keep working unchanged. Policies
 * are useful when a controller needs explicit row-level gating — invoke via
 * `$this->authorize('update', $role)` or `$user->can('update', $role)`.
 */
class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('roles.read');
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->can('roles.read');
    }

    public function create(User $actor): bool
    {
        return $actor->can('roles.create');
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->can('roles.update');
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->can('roles.delete');
    }
}
