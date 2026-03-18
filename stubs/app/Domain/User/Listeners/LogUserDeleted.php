<?php

namespace App\Domain\User\Listeners;

use App\Domain\User\Events\UserDeleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Log user deletion for auditing.
 * Runs on queue to avoid blocking the request.
 */
class LogUserDeleted implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(UserDeleted $event): void
    {
        Log::channel('stack')->info('User deleted', [
            'user_id' => $event->userId,
            'email' => $event->userEmail,
            'deleted_by' => $event->performedBy,
        ]);
    }
}
