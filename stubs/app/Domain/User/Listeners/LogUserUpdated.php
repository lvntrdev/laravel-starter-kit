<?php

namespace App\Domain\User\Listeners;

use App\Domain\User\Events\UserUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

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
