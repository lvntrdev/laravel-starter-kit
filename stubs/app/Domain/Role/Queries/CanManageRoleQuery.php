<?php

namespace App\Domain\Role\Queries;

use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;

/**
 * Query: Check if a user can manage (edit/delete) the given role.
 *
 * system_admin bypasses all checks.
 * Other users can only manage roles below their own hierarchy level (higher sort_order).
 */
class CanManageRoleQuery
{
    public function check(User $user, Role $role): bool
    {
        if ($user->hasRole(RoleEnum::SystemAdmin)) {
            return true;
        }

        $userMinSortOrder = (int) $user->roles->min('sort_order');

        return $role->sort_order > $userMinSortOrder;
    }
}
