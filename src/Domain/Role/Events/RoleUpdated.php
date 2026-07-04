<?php

namespace Lvntr\StarterKit\Domain\Role\Events;

use App\Models\Role;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when an existing role is updated.
 * Carries both the updated model and changed fields.
 */
class RoleUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Role $role,
        /** @var array<string> */
        public readonly array $changedFields = [],
        /**
         * Event-time permission snapshot (sorted names). Captured at dispatch
         * so a queued audit listener records THIS update's permission set, not
         * whatever the role holds when the job later runs.
         *
         * @var array<string>
         */
        public readonly array $permissions = [],
        public readonly int|string|null $performedBy = null,
    ) {}
}
