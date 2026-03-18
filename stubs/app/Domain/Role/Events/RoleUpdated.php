<?php

namespace App\Domain\Role\Events;

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
        public readonly int|string|null $performedBy = null,
    ) {}
}
