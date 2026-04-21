<?php

namespace App\Domain\Role\Actions;

use App\Domain\Role\DTOs\RoleDTO;
use App\Domain\Role\Events\RoleCreated;
use App\Domain\Shared\Actions\BaseAction;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        RoleCreated::dispatch($role, Auth::id());

        return $role;
    }
}
