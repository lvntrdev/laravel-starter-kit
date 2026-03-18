<?php

namespace Lvntr\StarterKit\Domain\Shared\Pipelines;

use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;

/**
 * Action Pipeline — chains multiple actions into a single transactional flow.
 *
 * Benefits:
 *   - Complex workflows composed from small, reusable action pipes
 *   - Automatic database transaction wrapping
 *   - Easy to add/remove/reorder steps
 *
 * Usage:
 *   $result = ActionPipeline::make()
 *       ->send($payload)
 *       ->through([
 *           ValidateInventoryAction::class,
 *           CreateOrderAction::class,
 *           SendOrderNotificationAction::class,
 *       ])
 *       ->run();
 *
 * Each action must implement PipeableAction interface:
 *   public function handle(mixed $payload, Closure $next): mixed
 */
class ActionPipeline
{
    /** @var array<int, class-string|object> */
    private array $pipes = [];

    private mixed $passable = null;

    private bool $useTransaction = true;

    /**
     * Create a new pipeline instance.
     */
    public static function make(): static
    {
        return new static;
    }

    /**
     * Set the payload to send through the pipeline.
     */
    public function send(mixed $passable): static
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * Set the array of pipes (action classes) to process.
     *
     * @param  array<int, class-string|object>  $pipes
     */
    public function through(array $pipes): static
    {
        $this->pipes = $pipes;

        return $this;
    }

    /**
     * Disable database transaction wrapping.
     */
    public function withoutTransaction(): static
    {
        $this->useTransaction = false;

        return $this;
    }

    /**
     * Execute the pipeline and return the final result.
     *
     * @throws \Throwable Re-thrown from within the transaction on failure.
     */
    public function run(): mixed
    {
        $execute = fn () => app(Pipeline::class)
            ->send($this->passable)
            ->through($this->pipes)
            ->thenReturn();

        if ($this->useTransaction) {
            return DB::transaction($execute);
        }

        return $execute();
    }
}
