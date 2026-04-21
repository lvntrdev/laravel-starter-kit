<?php

namespace App\Domain\Role\Actions;

use App\Domain\Role\DTOs\RoleDTO;
use App\Domain\Role\Events\RoleUpdated;
use App\Domain\Shared\Actions\BaseAction;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            RoleUpdated::dispatch($role, $changedFields, Auth::id());
        }

        return $role;
    }
}
