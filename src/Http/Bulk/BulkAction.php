<?php

namespace Lvntr\StarterKit\Http\Bulk;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contract for bulk action handlers used by bulk datatable endpoints.
 *
 * Implementors declare what key they respond to ('delete', 'archive', etc.),
 * how they filter items the current user may operate on, and how they
 * execute the operation with a full structured result.
 *
 * Authorization is per-item (open loop) to prevent mass-authorization bypass.
 * Transaction wrapping is the responsibility of the caller (BulkActionDispatcher).
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
interface BulkAction
{
    /**
     * The unique key that identifies this action in bulk POST requests.
     *
     * Example: 'delete', 'archive', 'restore'
     */
    public function getKey(): string;

    /**
     * Filter the given collection to items the user is authorized to act on.
     *
     * Returns only the subset of items the actor may touch. Items excluded
     * here are reported as "skipped" in the final response — they are NOT
     * an error; they are silently bypassed so authorized items still process.
     *
     * @param  Collection<int, TModel>  $items
     * @return Collection<int, TModel>
     */
    public function authorize(Authenticatable $user, Collection $items): Collection;

    /**
     * Execute the bulk operation on the already-authorized collection.
     *
     * Returns a structured result with counts and optional per-id details.
     *
     * @param  Collection<int, TModel>  $items
     * @return array{
     *     processed: int,
     *     failed: array<int, array{id: int|string, reason: string}>,
     * }
     */
    public function handle(Collection $items): array;
}
