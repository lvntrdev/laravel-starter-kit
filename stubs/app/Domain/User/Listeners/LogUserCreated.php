<?php

namespace App\Domain\User\Listeners;

use App\Domain\User\Events\UserCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Log user creation for auditing.
 * Runs on queue to avoid blocking the request.
 */
class LogUserCreated implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(UserCreated $event): void
    {
        Log::channel('stack')->info('User created', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'created_by' => $event->performedBy,
        ]);
    }
}
