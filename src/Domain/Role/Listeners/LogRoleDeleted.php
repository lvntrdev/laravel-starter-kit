<?php

namespace Lvntr\StarterKit\Domain\Role\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lvntr\StarterKit\Domain\Role\Events\RoleDeleted;

/**
 * Record a role deletion in the activity log.
 *
 * Deleting a role revokes every permission bound to it, so the deletion is a
 * permission-relevant security event. It is recorded on the dedicated `audit`
 * channel with the acting user, keeping the domain/security trail complete
 * even if a consumer removes the HasActivityLogging trait from the model.
 * The event carries only scalars (the model row is already gone), so the
 * entry is logged without a subject association.
 *
 * `tries = 1`: see LogRoleUpdated — avoid duplicate audit rows on queue retry.
 */
class LogRoleDeleted implements ShouldQueue
{
    public int $tries = 1;

    /**
     * Handle the event.
     */
    public function handle(RoleDeleted $event): void
    {
        activity('audit')
            ->causedBy($event->performedBy)
            ->event('deleted')
            ->withProperties([
                'role_id' => $event->roleId,
                'name' => $event->roleName,
            ])
            ->log('Role deleted');
    }
}
