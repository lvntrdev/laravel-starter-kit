<?php

namespace App\Domain\Role\Queries;

use App\Enums\RoleEnum;
use App\Models\User;

/**
 * Query: Get the grantable permissions for a given user.
 *
 * system_admin returns null (meaning all permissions are grantable).
 */
class UserGrantablePermissionsQuery
{
    /**
     * @return string[]|null
     */
    public function get(User $user): ?array
    {
        if ($user->hasRole(RoleEnum::SystemAdmin)) {
            return null;
        }

        return $user->getAllPermissions()->pluck('name')->values()->all();
    }
}
