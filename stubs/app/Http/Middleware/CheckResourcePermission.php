<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dynamically check resource permissions based on route name.
 *
 * Maps route names like "admin.users.index" to permission "users.read"
 * using the last two segments as resource and action.
 * If the resolved permission does not exist in the database, access is allowed.
 * Super admin bypass is handled by Gate::before in AppServiceProvider.
 */
class CheckResourcePermission
{
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
     *   ->middleware('check.permission')           // auto-resolve from route name
     *   ->middleware('check.permission:reports.read') // explicit permission
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
                if ($type && is_string($type)) {
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
     * Check if the given permission exists in the database (cached).
     */
    private function permissionExists(string $permissionName): bool
    {
        /** @var Collection<int, string> $cached */
        static $cached = null;

        if ($cached === null) {
            $cached = Permission::pluck('name');
        }

        return $cached->contains($permissionName);
    }
}
