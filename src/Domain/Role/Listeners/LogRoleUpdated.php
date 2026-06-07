<?php

namespace Lvntr\StarterKit\Domain\Role\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Lvntr\StarterKit\Domain\Role\Events\RoleUpdated;

/**
 * Log role updates for auditing.
 * Runs on queue to avoid blocking the request.
 */
class LogRoleUpdated implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(RoleUpdated $event): void
    {
        Log::channel('stack')->info('Role updated', [
            'role_id' => $event->role->id,
            'name' => $event->role->name,
            'changed_fields' => $event->changedFields,
            'updated_by' => $event->performedBy,
        ]);
    }
}
