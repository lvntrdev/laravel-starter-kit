<?php

namespace App\Domain\Role\BulkActions;

use App\Domain\Role\Queries\CanManageRoleQuery;
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
 *   - The rank hierarchy is NOT re-implemented here: it is delegated to
 *     CanManageRoleQuery, the same query destroy()/edit()/data() run. So the
 *     bulk endpoint can never be a wider door than the singular one —
 *     system_admin bypasses, a role-less actor may delete nothing, and every
 *     other actor may only delete roles ranked strictly BELOW their own
 *     (sort_order greater than their minimum; an equal-rank peer role is
 *     denied).
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

    private CanManageRoleQuery $canManageQuery;

    public function __construct(?CanManageRoleQuery $canManageQuery = null)
    {
        $this->protectedRoles = array_map(fn (RoleEnum $r) => $r->value, RoleEnum::cases());
        $this->canManageQuery = $canManageQuery ?? new CanManageRoleQuery;
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

        return $items->filter(function (Role $role) use ($user): bool {
            // System roles are always protected — even from system_admin.
            if (in_array($role->name, $this->protectedRoles, true)) {
                return false;
            }

            // Rank hierarchy — SINGLE source of truth. Do not inline a
            // sort_order comparison here: an earlier copy used `>=` while the
            // query uses `>`, which let a roles.delete actor bulk-delete an
            // equal-rank role that destroy() refused. Delegating keeps the two
            // paths from ever diverging again. The query also covers the
            // system_admin bypass and the role-less actor (null minimum
            // sort_order → may manage no role at all).
            return $this->canManageQuery->check($user, $role);
        })->values();
    }
}
