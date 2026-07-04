<?php

namespace Lvntr\StarterKit\Domain\Role\Actions;

use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Domain\Role\DTOs\RoleDTO;
use Lvntr\StarterKit\Domain\Role\Events\RoleCreated;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;

/**
 * Action: Create a new role with permissions.
 * Single-purpose action — receives a DTO, persists via Eloquent.
 * Dispatches RoleCreated event on success.
 */
class CreateRoleAction extends BaseAction
{
    /**
     * Execute the action.
     */
    public function execute(RoleDTO $dto): Role
    {
        $role = DB::transaction(function () use ($dto): Role {
            $role = Role::create($dto->toArray());
            $role->syncPermissions($dto->permissions);

            return $role;
        });

        // Snapshot the granted permissions HERE (synchronous, correct) so a
        // queued audit listener does not re-read a later pivot state.
        $permissions = $role->permissions()->pluck('name')->sort()->values()->all();

        RoleCreated::dispatch($role, $permissions, Auth::id());

        return $role;
    }
}
