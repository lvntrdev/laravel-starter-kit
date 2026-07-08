<?php

namespace Lvntr\StarterKit\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dynamically check resource permissions based on route name.
 *
 * Maps route names like "admin.users.index" to permission "users.read"
 * using the last two segments as resource and action.
 *
 * Behavior when the resolved permission is NOT seeded in the database is
 * FAIL-CLOSED by default: a forgotten permission row must never silently
 * expose an endpoint on a public host (staging / uat / demo included).
 *   - local → allow + warn (developer DX: seed it and move on).
 *   - any other environment (production, staging, uat, demo, testing, …)
 *     → deny (AuthorizationException).
 *   - Opt-out: set `starter-kit.permissions.allow_unmapped` to true to
 *     restore the legacy "allow on any non-production env" behavior
 *     (production still denies regardless of the flag).
 * Super admin bypass is handled by Gate::before in AppServiceProvider.
 */
class CheckResourcePermission
{
    /**
     * Cache key for the seeded permission-name set.
     *
     * Public so post-seed hooks (sk:seed-permissions) can invalidate it.
     */
    public const CACHE_KEY = 'starter-kit:check-permission:names';

    /**
     * Short TTL (seconds) for the permission-name cache.
     *
     * Kept small on purpose: under Octane the worker process is long-lived,
     * so an in-process cache could otherwise serve a stale name set for the
     * whole worker lifetime. A short TTL bounds staleness even if the
     * post-seed flush is missed, while still absorbing repeat lookups within
     * a single request/burst.
     */
    private const CACHE_TTL_SECONDS = 60;

    /**
     * Map route actions to permission abilities.
     *
     * @var array<string, string>
     */
    private const ACTION_ABILITY_MAP = [
        'index' => 'read',
        'show' => 'read',
        'dtApi' => 'read',
        'data' => 'read',
        'options' => 'read',
        'create' => 'create',
        'store' => 'create',
        'edit' => 'update',
        'update' => 'update',
        'uploadAvatar' => 'update',
        'deleteAvatar' => 'update',
        'regenerateDocs' => 'update',
        'syncPostman' => 'update',
        'syncApidog' => 'update',
        'destroy' => 'delete',
        'import' => 'import',
        'export' => 'export',
    ];

    /**
     * Handle an incoming request.
     *
     * When $permission is provided explicitly (e.g. "reports.read"),
     * it is used directly instead of resolving from the route name.
     *
     * Usage in routes:
     *   ->middleware('check.resource.permission')           // auto-resolve from route name
     *   ->middleware('check.resource.permission:reports.read') // explicit permission
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthorizationException
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        if (! $permission) {
            $routeName = $request->route()?->getName();

            if (! $routeName) {
                return $next($request);
            }

            $permission = $this->resolvePermission($routeName);

            // Check for sub-resource via ?type= query parameter
            // e.g. /admin/users?type=student → "users:student.read"
            if ($permission) {
                $type = $request->query('type');
                if ($type && is_string($type) && preg_match('/^[a-z0-9_]+$/i', $type)) {
                    $parts = explode('.', $permission, 2);
                    $subPermission = "{$parts[0]}:{$type}.{$parts[1]}";

                    if ($this->permissionExists($subPermission)) {
                        $permission = $subPermission;
                    }
                }
            }
        }

        if (! $permission) {
            return $next($request);
        }

        if (! $this->permissionExists($permission)) {
            if (! $this->allowsUnmappedPermission()) {
                throw new AuthorizationException('You are not authorized for this action.');
            }

            Log::warning('check.resource.permission: resolved permission is not seeded; allowing (unmapped permissions are permitted in this environment).', [
                'permission' => $permission,
                'route' => $request->route()?->getName(),
                'path' => $request->path(),
                'environment' => app()->environment(),
            ]);

            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->can($permission)) {
            throw new AuthorizationException('You are not authorized for this action.');
        }

        return $next($request);
    }

    /**
     * Resolve the permission string from a route name.
     *
     * "admin.users.index" → "users.read"
     * "users.store"       → "users.create"
     */
    private function resolvePermission(string $routeName): ?string
    {
        $segments = explode('.', $routeName);

        if (count($segments) < 2) {
            return null;
        }

        $action = array_pop($segments);
        $resource = array_pop($segments);

        $ability = self::ACTION_ABILITY_MAP[$action] ?? null;

        if (! $ability) {
            return null;
        }

        return "{$resource}.{$ability}";
    }

    /**
     * Whether an unseeded (unmapped) permission should fail OPEN rather than
     * fail CLOSED.
     *
     * Default posture is fail-closed everywhere except `local`: a forgotten
     * permission row can never silently expose an endpoint on a public
     * staging / uat / demo / production host. Local development stays
     * permissive so a not-yet-seeded permission does not block iteration.
     *
     * Consumers that relied on the previous "allow on any non-production
     * environment" behavior can opt back in via
     * `config('starter-kit.permissions.allow_unmapped') === true`, which
     * restores allow-in-non-production. Production always denies regardless.
     */
    private function allowsUnmappedPermission(): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        if (config('starter-kit.permissions.allow_unmapped', false)) {
            return ! app()->environment('production');
        }

        return false;
    }

    /**
     * Check if the given permission exists in the database.
     *
     * The seeded permission-name set is cached with a short TTL so repeat
     * lookups stay cheap without pinning a stale set for a long-lived Octane
     * worker's lifetime. The cache is invalidated by sk:seed-permissions
     * (see self::flushCache) so newly seeded permissions are visible at once.
     */
    private function permissionExists(string $permissionName): bool
    {
        /** @var array<int, string> $names */
        $names = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            static fn (): array => Permission::pluck('name')->all(),
        );

        return in_array($permissionName, $names, true);
    }

    /**
     * Forget the cached permission-name set.
     *
     * Called after sk:seed-permissions so a freshly seeded permission is
     * honored immediately instead of waiting out the short TTL — important
     * under Octane where the process (and its cache) is long-lived.
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
