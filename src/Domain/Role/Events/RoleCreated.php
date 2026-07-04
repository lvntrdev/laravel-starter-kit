<?php

namespace Lvntr\StarterKit\Domain\Role\Events;

use App\Models\Role;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a new role is created.
 */
class RoleCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Role $role,
        /**
         * Event-time permission snapshot (sorted names). Captured at dispatch
         * so a queued audit listener records the permissions granted at create
         * time, not whatever the role holds when the job later runs.
         *
         * @var array<string>
         */
        public readonly array $permissions = [],
        public readonly int|string|null $performedBy = null,
    ) {}
}
