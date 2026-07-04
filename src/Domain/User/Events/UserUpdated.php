<?php

namespace Lvntr\StarterKit\Domain\User\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when an existing user is updated.
 * Carries both the updated model and changed fields.
 */
class UserUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User $user,
        /** @var array<string> */
        public readonly array $changedFields = [],
        /**
         * Event-time role snapshot (sorted names). Captured at dispatch so a
         * queued audit listener records THIS update's role set, not whatever
         * the user holds when the job later runs.
         *
         * @var array<string>
         */
        public readonly array $roles = [],
        public readonly int|string|null $performedBy = null,
    ) {}
}
