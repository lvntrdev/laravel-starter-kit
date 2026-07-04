<?php

namespace Lvntr\StarterKit\Domain\User\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lvntr\StarterKit\Domain\User\Events\UserUpdated;

/**
 * Record a user's role change in the activity log.
 *
 * Audit-sink split: the HasActivityLogging trait on the User model records
 * attribute-level changes (name / email / status) on the Spatie default
 * channel. It CANNOT observe the user↔role pivot. This listener records ONLY
 * the role pivot change, on the dedicated `audit` channel, and stays silent
 * when no role change occurred — so an attribute-only update is never
 * double-logged.
 *
 * `tries = 1`: see the Role listeners — avoid duplicate audit rows on retry.
 */
class LogUserUpdated implements ShouldQueue
{
    public int $tries = 1;

    /**
     * Handle the event.
     */
    public function handle(UserUpdated $event): void
    {
        if (! in_array('role', $event->changedFields, true)) {
            // Attribute-only change — already recorded by HasActivityLogging.
            return;
        }

        activity('audit')
            ->performedOn($event->user)
            ->causedBy($event->performedBy)
            ->event('updated')
            ->withProperties([
                'email' => $event->user->email,
                'changed_fields' => $event->changedFields,
                // Event-time snapshot (see event) — never re-read the live pivot
                // here: a queued run would record a later role set.
                'roles' => $event->roles,
            ])
            ->log('User roles updated');
    }
}
