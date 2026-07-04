<?php

namespace Lvntr\StarterKit\Domain\Role\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lvntr\StarterKit\Domain\Role\Events\RoleCreated;

/**
 * Record the initial permission grant of a new role in the activity log.
 *
 * Audit-sink split: the HasActivityLogging trait records the role's attribute
 * creation on the Spatie default channel; this listener records the initial
 * permission pivot (which the trait cannot observe) on the `audit` channel.
 * It stays silent when the role was created without permissions, so a
 * permission-less create is never double-logged.
 *
 * `tries = 1`: see LogRoleUpdated — avoid duplicate audit rows on queue retry.
 */
class LogRoleCreated implements ShouldQueue
{
    public int $tries = 1;

    /**
     * Handle the event.
     */
    public function handle(RoleCreated $event): void
    {
        // Event-time snapshot (see event) — never re-read the live pivot here:
        // a queued run would record a later permission set.
        $permissions = $event->permissions;

        if (empty($permissions)) {
            // No pivot to record — attribute creation is logged by the trait.
            return;
        }

        activity('audit')
            ->performedOn($event->role)
            ->causedBy($event->performedBy)
            ->event('created')
            ->withProperties([
                'name' => $event->role->name,
                'permissions' => $permissions,
            ])
            ->log('Role created with permissions');
    }
}
