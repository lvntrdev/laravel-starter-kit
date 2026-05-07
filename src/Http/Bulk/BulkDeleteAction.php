<?php

namespace Lvntr\StarterKit\Http\Bulk;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic bulk delete action (base).
 *
 * Domain-specific bulk delete actions in the consumer application should
 * extend this class and override authorize() to apply per-domain rules
 * (rank hierarchy, ownership, etc.) and optionally override handle() for
 * soft-delete vs hard-delete behaviour.
 *
 * By default:
 *   - authorize() allows all items (domain subclasses narrow this).
 *   - handle() calls $model->delete() which follows the model's own
 *     SoftDelete trait if present — hard delete otherwise.
 *
 * @implements BulkAction<Model>
 */
class BulkDeleteAction implements BulkAction
{
    public function getKey(): string
    {
        return 'delete';
    }

    /**
     * Default: authorize all items (domain subclasses should override).
     *
     * {@inheritDoc}
     */
    public function authorize(Authenticatable $user, Collection $items): Collection
    {
        return $items;
    }

    /**
     * Execute the delete operation per item, catching individual failures.
     *
     * Per-item try/catch keeps a single stubborn model from rolling back
     * the whole batch. Failures are collected and returned for reporting.
     *
     * {@inheritDoc}
     */
    public function handle(Collection $items): array
    {
        $processed = 0;
        $failed = [];

        foreach ($items as $model) {
            try {
                $model->delete();
                $processed++;
            } catch (\Throwable $e) {
                $failed[] = [
                    'id' => $model->getKey(),
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
        ];
    }
}
