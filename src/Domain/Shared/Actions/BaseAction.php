<?php

namespace Lvntr\StarterKit\Domain\Shared\Actions;

use Lvntr\StarterKit\Domain\Shared\Contracts\PipeableAction;

/**
 * Base Action class.
 * Actions encapsulate a single business operation.
 *
 * Convention:
 *   - One public method: execute()
 *   - Inject dependencies via constructor
 *   - Keep controllers thin by moving logic here
 *   - Dispatch domain events after successful operations
 *
 * For pipeline usage, implement PipeableAction interface
 * and add a handle(mixed $payload, Closure $next) method.
 *
 * Flow: Controller → Action → Repository → Model
 *       Action dispatches Events → Listeners handle side effects
 *
 * @see PipeableAction
 * @see \Lvntr\StarterKit\Domain\Shared\Pipelines\ActionPipeline
 */
abstract class BaseAction
{
    //
}
