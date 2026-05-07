<?php

namespace Lvntr\StarterKit\Http\Bulk;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Dispatches a registered BulkAction in a transaction.
 *
 * Responsibilities:
 *   1. Resolve the correct BulkAction handler by key.
 *   2. Per-item authorization (open loop — no mass-authorize bypass).
 *   3. Wrap execution in DB::transaction.
 *   4. Assemble the standard bulk response shape.
 *
 * Callers (controllers) resolve this via DI and call dispatch().
 *
 * Usage:
 *   $dispatcher = new BulkActionDispatcher;
 *   $dispatcher->register(new BulkDeleteUserAction);
 *   $result = $dispatcher->dispatch($user, 'delete', $items);
 *   // => ['processed' => 3, 'skipped' => 1, 'failed' => [], 'message' => '...']
 */
class BulkActionDispatcher
{
    /** @var array<string, BulkAction<Model>> */
    private array $handlers = [];

    /**
     * Register a bulk action handler.
     *
     * @param  BulkAction<Model>  $handler
     */
    public function register(BulkAction $handler): void
    {
        $this->handlers[$handler->getKey()] = $handler;
    }

    /**
     * Check whether a handler is registered for the given key.
     */
    public function has(string $key): bool
    {
        return isset($this->handlers[$key]);
    }

    /**
     * Resolve handler by key or throw if unsupported.
     *
     * @return BulkAction<Model>
     *
     * @throws \InvalidArgumentException
     */
    public function resolve(string $key): BulkAction
    {
        if (! $this->has($key)) {
            throw new \InvalidArgumentException("Unsupported bulk action: {$key}");
        }

        return $this->handlers[$key];
    }

    /**
     * Authorize, execute, and wrap in a transaction.
     *
     * The authorization step happens OUTSIDE the transaction — we want the
     * authorized vs. skipped split determined before writes begin.
     * Execution happens inside the transaction so any partial failure rolls back.
     *
     * @param  Collection<int, Model>  $items  All candidate items (already fetched)
     * @return array{
     *     processed: int,
     *     skipped: int,
     *     failed: array<int, array{id: int|string, reason: string}>,
     *     message: string,
     * }
     */
    public function dispatch(Authenticatable $user, string $actionKey, Collection $items): array
    {
        $handler = $this->resolve($actionKey);

        $totalRequested = $items->count();

        // Per-item authorization — skipped items are safe-guarded items the
        // actor cannot touch. We collect them so the caller can report them.
        $authorized = $handler->authorize($user, $items);
        $skippedCount = $totalRequested - $authorized->count();

        if ($authorized->isEmpty()) {
            return [
                'processed' => 0,
                'skipped' => $skippedCount,
                'failed' => [],
                'message' => __('sk-bulk.no_authorized_items'),
            ];
        }

        // Run the actual operation inside a transaction. If handle() throws,
        // the transaction rolls back automatically.
        $result = DB::transaction(function () use ($handler, $authorized): array {
            return $handler->handle($authorized);
        });

        $processed = $result['processed'] ?? 0;
        $failed = $result['failed'] ?? [];

        return [
            'processed' => $processed,
            'skipped' => $skippedCount,
            'failed' => $failed,
            'message' => __('sk-bulk.result', [
                'processed' => $processed,
                'skipped' => $skippedCount,
                'failed' => count($failed),
            ]),
        ];
    }
}
