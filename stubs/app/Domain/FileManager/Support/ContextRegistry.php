<?php

namespace App\Domain\FileManager\Support;

use App\Models\GlobalFileBucket;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Runtime registry for FileManager contexts.
 *
 * Resolution order for `get($key)`:
 *   1. Explicit registration via {@see self::register()}.
 *   2. Laravel morph map (`Relation::morphMap()`) — if the key is a morph
 *      alias the matching model class is used.
 *   3. `App\Models\{Studly($key)}` convention fallback.
 *
 * Auto-resolved contexts get a sensible default: path `{key}/{id}/files`, owner
 * resolved via `findOrFail`, and authorization delegated to Laravel policies
 * (`can('view', $owner)` for reads, `can('update', $owner)` for writes). Call
 * `register()` only when you need to deviate from those defaults (custom path,
 * permission-only auth, singleton resolvers, …).
 */
class ContextRegistry
{
    /** @var array<string, ContextDefinition> */
    private array $contexts = [];

    public function __construct()
    {
        $this->registerBuiltIns();
    }

    /**
     * Bake the `global` singleton context into the registry so applications
     * never need to wire it up from a service provider. `user` intentionally
     * stays auto-resolved: it matches the `App\Models\User` convention and
     * the default authorizer's self-match + UserPolicy cover its semantics.
     */
    private function registerBuiltIns(): void
    {
        $this->register('global', [
            'model' => GlobalFileBucket::class,
            'path' => 'global/files',
            'resolve' => fn (?string $id) => GlobalFileBucket::singleton(),
            'authorize' => function (Model $actor, string $ability, Model $owner): bool {
                return $ability === 'read'
                    ? $actor->can('files.read') || $actor->can('files.update')
                    : $actor->can('files.update') || $actor->can('files.create') || $actor->can('files.delete');
            },
        ]);
    }

    /**
     * @param  array{model: class-string<Model>, path: string, resolve: Closure, authorize: Closure}  $config
     */
    public function register(string $key, array $config): void
    {
        $this->contexts[$key] = new ContextDefinition(
            key: $key,
            model: $config['model'],
            path: $config['path'],
            resolve: $config['resolve'],
            authorize: $config['authorize'],
        );
    }

    public function has(string $key): bool
    {
        return isset($this->contexts[$key]) || $this->autoResolve($key) !== null;
    }

    public function get(string $key): ContextDefinition
    {
        if (isset($this->contexts[$key])) {
            return $this->contexts[$key];
        }

        $definition = $this->autoResolve($key);

        if ($definition === null) {
            throw new InvalidArgumentException("Unsupported FileManager context: {$key}");
        }

        // Memoize for the remainder of the request.
        $this->contexts[$key] = $definition;

        return $definition;
    }

    /**
     * @return array<int, string>
     */
    public function registeredKeys(): array
    {
        return array_keys($this->contexts);
    }

    /**
     * @return array<int, string>
     */
    public function keysRequiringId(): array
    {
        return array_keys(array_filter($this->contexts, fn (ContextDefinition $d) => $d->requiresId()));
    }

    /**
     * Look up the context key registered for an owner model. Accepts either a
     * fully-qualified class name or a morph-map alias (whatever Spatie stored
     * in `media.model_type`) and normalizes to the backing class before match.
     */
    public function keyForModel(string $modelClassOrAlias): ?string
    {
        $morphMap = Relation::morphMap();
        $class = $morphMap[$modelClassOrAlias] ?? $modelClassOrAlias;

        foreach ($this->contexts as $key => $definition) {
            if ($definition->model === $class) {
                return $key;
            }
        }

        // Morph alias used as a direct context key (auto-resolve scenario).
        if (isset($morphMap[$modelClassOrAlias]) && $this->has($modelClassOrAlias)) {
            return $modelClassOrAlias;
        }

        return null;
    }

    public function pathFor(string $key, string $ownerId): string
    {
        return str_replace('{id}', $ownerId, $this->get($key)->path);
    }

    private function autoResolve(string $key): ?ContextDefinition
    {
        $modelClass = $this->resolveModelClass($key);

        if ($modelClass === null) {
            return null;
        }

        return new ContextDefinition(
            key: $key,
            model: $modelClass,
            path: "{$key}/{id}/files",
            resolve: fn (?string $id) => $modelClass::query()->findOrFail($id),
            authorize: fn (Model $actor, string $ability, Model $owner): bool => $this->defaultAuthorize($actor, $ability, $owner),
        );
    }

    /**
     * Universal default authorization used by auto-resolved contexts:
     *   1. Self-match — an actor managing their own record is always allowed
     *      (covers the built-in `user` context without any policy).
     *   2. Otherwise delegate to Laravel policies: `view` for reads, `update`
     *      for writes.
     */
    private function defaultAuthorize(Model $actor, string $ability, Model $owner): bool
    {
        if (
            $actor->getMorphClass() === $owner->getMorphClass()
            && (string) $actor->getKey() === (string) $owner->getKey()
        ) {
            return true;
        }

        return $actor->can($ability === 'read' ? 'view' : 'update', $owner);
    }

    private function resolveModelClass(string $key): ?string
    {
        // Prefer explicit Laravel morph-map aliases so conventions across the
        // app stay aligned with polymorphic relations.
        $morphMap = Relation::morphMap();
        if (isset($morphMap[$key]) && is_string($morphMap[$key]) && class_exists($morphMap[$key])) {
            return $morphMap[$key];
        }

        // Fallback: App\Models\{Studly(key)} — e.g. "vehicle" → App\Models\Vehicle.
        $candidate = 'App\\Models\\'.Str::studly($key);
        if (class_exists($candidate) && is_subclass_of($candidate, Model::class)) {
            return $candidate;
        }

        return null;
    }
}
