<?php

namespace App\Domain\Role\Listeners;

use App\Domain\Role\Events\RoleCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

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
