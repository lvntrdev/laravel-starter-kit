<?php

namespace Lvntr\StarterKit\Domain\User\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lvntr\StarterKit\Domain\User\Events\UserCreated;

/**
 * Record the initial role assignment of a new user in the activity log.
 *
 * Audit-sink split: the HasActivityLogging trait records the user's attribute
 * creation on the Spatie default channel; this listener records the initial
 * role pivot (which the trait cannot observe) on the `audit` channel. It stays
 * silent when the user was created without a role, so a role-less create is
 * never double-logged.
 *
 * `tries = 1`: see the Role listeners — avoid duplicate audit rows on retry.
 */
class LogUserCreated implements ShouldQueue
{
    public int $tries = 1;

    /**
     * Handle the event.
     */
    public function handle(UserCreated $event): void
    {
        // Event-time snapshot (see event) — never re-read the live pivot here:
        // a queued run would record a later role set.
        $roles = $event->roles;

        if (empty($roles)) {
            // No pivot to record — attribute creation is logged by the trait.
            return;
        }

        activity('audit')
            ->performedOn($event->user)
            ->causedBy($event->performedBy)
            ->event('created')
            ->withProperties([
                'email' => $event->user->email,
                'roles' => $roles,
            ])
            ->log('User created with roles');
    }
}
