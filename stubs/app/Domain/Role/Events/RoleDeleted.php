<?php

namespace App\Domain\Role\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a role is deleted.
 * Carries the role ID and name for logging/auditing
 * (the model may already be deleted from DB).
 */
class RoleDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int|string $roleId,
        public readonly string $roleName,
        public readonly int|string|null $performedBy = null,
    ) {}
}
