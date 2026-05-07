<?php

namespace App\Domain\User\BulkActions;

use App\Enums\RoleEnum;
use App\Http\BulkActions\BulkDeleteAction;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

/**
 * User domain bulk delete.
 *
 * Applies the same per-item authorization rules as UserPolicy@delete:
 *   - A user cannot delete themselves.
 *   - Non system_admin actors can only delete users whose highest role is at
 *     an equal or lower rank (sort_order >= actor's minimum sort_order).
 *   - The actor must have the 'users.delete' permission.
 *
 * Soft-delete: User model uses SoftDeletes — $user->delete() soft-deletes.
 * Hard-delete: Not performed here. If hard-delete is needed, a separate
 * BulkForceDeleteUserAction should be created.
 *
 * @extends BulkDeleteAction<User>
 */
class BulkDeleteUserAction extends BulkDeleteAction
{
    /**
     * Filter to items the actor may delete.
     *
     * Items excluded here are silently skipped and counted as "skipped"
     * in the response — never treated as errors.
     *
     * @param  Collection<int, User>  $items
     * @return Collection<int, User>
     */
    public function authorize(Authenticatable $user, Collection $items): Collection
    {
        /** @var User $user */
        // Actor must have bulk delete permission
        if (! $user->can('users.delete')) {
            return new Collection;
        }

        $isSystemAdmin = $user->hasRole(RoleEnum::SystemAdmin);
        $actorMinSortOrder = $isSystemAdmin ? null : (int) $user->roles->min('sort_order');

        return $items->filter(function (User $target) use ($user, $isSystemAdmin, $actorMinSortOrder): bool {
            // Self-deletion guard — mirrors UserPolicy@delete
            if ($user->is($target)) {
                return false;
            }

            if ($isSystemAdmin) {
                return true;
            }

            // Rank hierarchy: actor must outrank or equal target
            $targetMinSortOrder = $target->roles->min('sort_order');

            if ($targetMinSortOrder === null) {
                // Target has no role → actor outranks
                return true;
            }

            return $actorMinSortOrder <= (int) $targetMinSortOrder;
        })->values();
    }
}
