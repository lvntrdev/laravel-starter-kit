<?php

namespace Lvntr\StarterKit\Domain\Role\Queries;

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

        $userMinSortOrder = $user->roles->min('sort_order');

        // Actor has no role at all (e.g. direct-permission user) — they must
        // not be able to manage any role. Casting null → 0 would let them
        // manage every role including system_admin.
        if ($userMinSortOrder === null) {
            return false;
        }

        return $role->sort_order > (int) $userMinSortOrder;
    }
}
