<?php

namespace App\Domain\User\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched when a user is deleted.
 * Carries the user ID and email for logging/auditing
 * (the model may already be deleted from DB).
 */
class UserDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int|string $userId,
        public readonly string $userEmail,
        public readonly int|string|null $performedBy = null,
    ) {}
}
