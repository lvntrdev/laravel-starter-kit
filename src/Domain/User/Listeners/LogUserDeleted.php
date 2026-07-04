<?php

namespace Lvntr\StarterKit\Domain\User\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lvntr\StarterKit\Domain\User\Events\UserDeleted;

/**
 * Record a user deletion in the activity log.
 *
 * Deleting a user revokes their role/permission assignments, so the deletion
 * is a security-relevant event. It is recorded on the dedicated `audit`
 * channel with the acting user, keeping the domain/security trail complete
 * even if a consumer removes the HasActivityLogging trait from the model.
 * The event carries only scalars (the model row is already gone), so the
 * entry is logged without a subject association.
 *
 * `tries = 1`: see the Role listeners — avoid duplicate audit rows on retry.
 */
class LogUserDeleted implements ShouldQueue
{
    public int $tries = 1;

    /**
     * Handle the event.
     */
    public function handle(UserDeleted $event): void
    {
        activity('audit')
            ->causedBy($event->performedBy)
            ->event('deleted')
            ->withProperties([
                'user_id' => $event->userId,
                'email' => $event->userEmail,
            ])
            ->log('User deleted');
    }
}
