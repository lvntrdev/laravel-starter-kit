<?php

namespace App\Domain\Role\Actions;

use App\Domain\Role\Events\RoleDeleted;
use App\Domain\Shared\Actions\BaseAction;
use App\Enums\RoleEnum;
use App\Models\Role;

/**
 * Action: Delete a role.
 * Dispatches RoleDeleted event on success.
 *
 * performedById is passed explicitly so this action stays HTTP-context free
 * and can be called from console commands, queue jobs, or tests.
 */
class DeleteRoleAction extends BaseAction
{
    /**
     * Execute the action.
     */
    /**
     * @throws \LogicException If attempting to delete a protected system role.
     */
    public function execute(Role $role, int|string|null $performedById = null): bool
    {
        $protectedRoles = array_map(fn (RoleEnum $r) => $r->value, RoleEnum::cases());

        if (in_array($role->name, $protectedRoles, true)) {
            throw new \LogicException("System role '{$role->name}' cannot be deleted.");
        }

        $roleId = $role->id;
        $roleName = $role->name;

        $result = (bool) $role->delete();

        if ($result) {
            RoleDeleted::dispatch($roleId, $roleName, $performedById);
        }

        return $result;
    }
}
