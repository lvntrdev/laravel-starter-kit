<?php

namespace Lvntr\StarterKit\Domain\Role\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Lvntr\StarterKit\Domain\Role\Events\RoleCreated;

/**
 * Log role creation for auditing.
 * Runs on queue to avoid blocking the request.
 */
class LogRoleCreated implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(RoleCreated $event): void
    {
        Log::channel('stack')->info('Role created', [
            'role_id' => $event->role->id,
            'name' => $event->role->name,
            'created_by' => $event->performedBy,
        ]);
    }
}
