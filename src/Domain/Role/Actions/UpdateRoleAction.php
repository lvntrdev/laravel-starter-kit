<?php

namespace Lvntr\StarterKit\Domain\Role\Actions;

use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Domain\Role\DTOs\RoleDTO;
use Lvntr\StarterKit\Domain\Role\Events\RoleUpdated;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;

/**
 * Action: Update an existing role.
 * Handles role name, display_name and permission sync.
 * Dispatches RoleUpdated event with changed fields.
 */
class UpdateRoleAction extends BaseAction
{
    /**
     * Execute the action.
     */
    public function execute(Role $role, RoleDTO $dto): Role
    {
        $data = $dto->toArray();

        $changedFields = array_keys(array_filter(
            $data,
            fn ($value, $key) => $role->getAttribute($key) !== $value,
            ARRAY_FILTER_USE_BOTH,
        ));

        $oldPermissions = $role->permissions->pluck('name')->sort()->values()->all();

        $role = DB::transaction(function () use ($role, $data, $dto): Role {
            $role->update($data);
            $role->refresh();
            $role->syncPermissions($dto->permissions);

            return $role;
        });

        $newPermissions = collect($dto->permissions)->sort()->values()->all();
        if ($oldPermissions !== $newPermissions) {
            $changedFields[] = 'permissions';
        }

        if (! empty($changedFields)) {
            // Snapshot the persisted permission set HERE (synchronous, correct)
            // so a queued audit listener records THIS update, not a later pivot.
            $permissions = $role->permissions()->pluck('name')->sort()->values()->all();

            RoleUpdated::dispatch($role, $changedFields, $permissions, Auth::id());
        }

        return $role;
    }
}
