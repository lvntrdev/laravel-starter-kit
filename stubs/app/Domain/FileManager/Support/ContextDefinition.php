<?php

namespace App\Domain\FileManager\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * One registered FileManager context (e.g. `user`, `global`, `vehicle`).
 *
 * `path` supports the `{id}` placeholder, resolved against the owner's primary key
 * at upload time. Contexts without `{id}` (e.g. the singleton `global/files`) do
 * not require a `context_id` from the frontend.
 */
readonly class ContextDefinition
{
    public function __construct(
        public string $key,
        public string $model,
        public string $path,
        public Closure $resolve,
        public Closure $authorize,
    ) {}

    public function requiresId(): bool
    {
        return str_contains($this->path, '{id}');
    }

    public function resolveOwner(?string $id): Model
    {
        return ($this->resolve)($id);
    }

    public function authorize(Model $user, string $ability, Model $owner): bool
    {
        return (bool) ($this->authorize)($user, $ability, $owner);
    }
}
