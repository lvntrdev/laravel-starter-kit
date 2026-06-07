<?php

namespace Lvntr\StarterKit\Domain\Role\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Lvntr\StarterKit\Domain\Role\Events\RoleDeleted;

/**
 * Log role deletion for auditing.
 * Runs on queue to avoid blocking the request.
 */
class LogRoleDeleted implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(RoleDeleted $event): void
    {
        Log::channel('stack')->info('Role deleted', [
            'role_id' => $event->roleId,
            'name' => $event->roleName,
            'deleted_by' => $event->performedBy,
        ]);
    }
}
