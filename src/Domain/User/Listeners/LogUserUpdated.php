<?php

namespace Lvntr\StarterKit\Domain\User\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Lvntr\StarterKit\Domain\User\Events\UserUpdated;

/**
 * Log user updates for auditing.
 * Runs on queue to avoid blocking the request.
 */
class LogUserUpdated implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(UserUpdated $event): void
    {
        Log::channel('stack')->info('User updated', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'changed_fields' => $event->changedFields,
            'updated_by' => $event->performedBy,
        ]);
    }
}
