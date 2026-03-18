<?php

namespace Lvntr\StarterKit\Domain\Shared\Contracts;

/**
 * Contract for pipe-compatible actions.
 * Actions implementing this interface can be used in ActionPipeline.
 *
 * Each pipe receives a payload and a closure to pass the result to the next pipe.
 */
interface PipeableAction
{
    /**
     * Handle the payload and pass the result to the next pipe.
     *
     * @param  mixed  $payload  The data flowing through the pipeline
     * @param  \Closure  $next  Passes the result to the next action in the chain
     */
    public function handle(mixed $payload, \Closure $next): mixed;
}
