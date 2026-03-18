<?php

namespace App\Domain\Role\Queries;

use App\Enums\RoleEnum;
use App\Models\Role;
use App\Models\User;

/**
 * Query: Return available roles as select options, filtered by hierarchy.
 *
 * system_admin sees all roles.
 * Other users see only roles at their level or below (sort_order >= theirs).
 */
class RoleSelectOptionsQuery
{
    /**
     * @return list<array{label: string, value: string}>
     */
    public function get(User $user): array
    {
        $query = Role::query()->orderBy('sort_order');

        if (! $user->hasRole(RoleEnum::SystemAdmin)) {
            $userMinSortOrder = (int) $user->roles->min('sort_order');
            $query->where('sort_order', '>=', $userMinSortOrder);
        }

        return $query->get()->map(fn (Role $role) => [
            'label' => ucfirst(str_replace('_', ' ', $role->name)),
            'value' => $role->name,
        ])->values()->all();
    }
}
