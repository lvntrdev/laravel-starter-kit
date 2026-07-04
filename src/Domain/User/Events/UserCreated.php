<?php

namespace Lvntr\StarterKit\Domain\User\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a new user is created.
 * Listeners can send welcome emails, assign default roles, etc.
 */
class UserCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly User $user,
        /**
         * Event-time role snapshot (sorted names). Captured at dispatch so a
         * queued audit listener records the roles assigned at create time, not
         * whatever the user holds when the job later runs.
         *
         * @var array<string>
         */
        public readonly array $roles = [],
        public readonly int|string|null $performedBy = null,
    ) {}
}
