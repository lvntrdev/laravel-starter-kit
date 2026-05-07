<?php

namespace App\Domain\Role\BulkActions;

use App\Enums\RoleEnum;
use App\Http\BulkActions\BulkDeleteAction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Role domain bulk delete.
 *
 * Hard-delete (Role has no SoftDeletes). System roles defined in RoleEnum
 * are protected and always excluded.
 *
 * Authorization rules:
 *   - Actor must have the 'roles.delete' permission.
 *   - System roles (RoleEnum cases) cannot be deleted.
 *   - Only system_admin can delete roles with sort_order lower than their own.
 *
 * @extends BulkDeleteAction<Role>
 */
class BulkDeleteRoleAction extends BulkDeleteAction
{
    /**
     * Protected system role names derived from RoleEnum.
     *
     * @var string[]
     */
    private array $protectedRoles;

    public function __construct()
    {
        $this->protectedRoles = array_map(fn (RoleEnum $r) => $r->value, RoleEnum::cases());
    }

    /**
     * Filter to roles the actor may delete.
     *
     * @param  Collection<int, Role>  $items
     * @return Collection<int, Role>
     */
    public function authorize(Authenticatable $user, Collection $items): Collection
    {
        /** @var User $user */
        if (! $user->can('roles.delete')) {
            return new Collection;
        }

        $isSystemAdmin = $user->hasRole(RoleEnum::SystemAdmin);
        $actorMinSortOrder = $isSystemAdmin ? null : (int) $user->roles->min('sort_order');

        return $items->filter(function (Role $role) use ($isSystemAdmin, $actorMinSortOrder): bool {
            // System roles are always protected
            if (in_array($role->name, $this->protectedRoles, true)) {
                return false;
            }

            if ($isSystemAdmin) {
                return true;
            }

            // Non-system_admin cannot delete roles that outrank them
            return (int) $role->sort_order >= $actorMinSortOrder;
        })->values();
    }
}
