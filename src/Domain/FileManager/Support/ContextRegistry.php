<?php

namespace Lvntr\StarterKit\Domain\FileManager\Support;

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
 * (`can('view', $owner)` for reads, `can('update', $owner)` for every mutation).
 * Call `register()` only when you need to deviate from those defaults (custom
 * path, permission-only auth, singleton resolvers, …).
 *
 * ## Ability contract for `authorize` closures
 *
 * A closure registered here receives ONE of four ability names — `read`,
 * `create`, `update`, `delete` — matching the operation the request performs
 * (see `Domain\FileManager\Services\FileManagerAuthorizer`).
 * The single `write` ability that used to stand for every mutation is
 * deprecated: it is no longer passed by the kit, and a closure that branches
 * on `$ability === 'write'` will never see that branch again. Branch on the
 * four names (or on `$ability === 'read'` for a read/mutate split), e.g.:
 *
 *     'authorize' => fn (Model $actor, string $ability, Model $owner): bool
 *         => $actor->can("vehicles.{$ability}"),
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
        /** @var class-string<Model> $globalBucketClass */
        $globalBucketClass = config('file-manager.models.global_bucket', 'App\\Models\\GlobalFileBucket');

        $this->register('global', [
            'model' => $globalBucketClass,
            'path' => 'global/files',
            'resolve' => fn (?string $id) => $globalBucketClass::singleton(),
            'authorize' => function (Model $actor, string $ability, Model $owner): bool {
                // One permission per ability. The previous OR-collapse meant a
                // role holding only `files.create` could also delete files and
                // empty the trash — FileManager routes are excluded from
                // CheckResourcePermission, so this closure is the only gate.
                return match ($ability) {
                    'read' => $actor->can('files.read'),
                    'create' => $actor->can('files.create'),
                    'update' => $actor->can('files.update'),
                    'delete' => $actor->can('files.delete'),
                    // Deprecated ability, kept only for callers that still
                    // invoke ContextDefinition::authorize() with the old
                    // `write` name. Resolved as the narrowest mutating
                    // permission — never create or delete.
                    'write' => $actor->can('files.update'),
                    // Unknown ability → fail closed.
                    default => false,
                };
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
     *      for every mutation.
     *
     * The four file abilities deliberately collapse into the owner's `update`
     * policy ability here: an auto-resolved context has no per-file permission
     * set, and the owner policy speaks about the OWNER record — mapping a file
     * delete onto `delete($vehicle)` would demand the right to destroy the
     * vehicle itself. Register the context explicitly when you need the file
     * abilities gated one by one.
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
