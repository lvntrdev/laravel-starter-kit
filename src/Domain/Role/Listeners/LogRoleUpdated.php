<?php

namespace Lvntr\StarterKit\Domain\Role\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lvntr\StarterKit\Domain\Role\Events\RoleUpdated;

/**
 * Record a role's permission change in the activity log.
 *
 * Audit-sink split (single source of truth for auditing):
 *   - The HasActivityLogging trait on the Role model already records
 *     attribute-level changes (name / display_name / group / color) on the
 *     Spatie default log channel. It CANNOT observe the role↔permission
 *     pivot.
 *   - This listener records ONLY the permission pivot change, on the
 *     dedicated `audit` log channel. It stays silent when no permission
 *     change occurred, so an attribute-only update is never double-logged.
 *
 * Result: a permission-only update produces exactly one activity row (this
 * listener); an attribute-only update produces exactly one row (the trait).
 *
 * `tries = 1`: activity() has no dedup guard, so a queue retry would insert a
 * duplicate audit row. Losing the row on a transient failure is preferable to
 * silently duplicating an audit record.
 */
class LogRoleUpdated implements ShouldQueue
{
    public int $tries = 1;

    /**
     * Handle the event.
     */
    public function handle(RoleUpdated $event): void
    {
        if (! in_array('permissions', $event->changedFields, true)) {
            // Attribute-only change — already recorded by HasActivityLogging.
            return;
        }

        activity('audit')
            ->performedOn($event->role)
            ->causedBy($event->performedBy)
            ->event('updated')
            ->withProperties([
                'name' => $event->role->name,
                'changed_fields' => $event->changedFields,
                // Event-time snapshot (see event) — never re-read the live pivot
                // here: a queued run would record a later permission set.
                'permissions' => $event->permissions,
            ])
            ->log('Role permissions updated');
    }
}
