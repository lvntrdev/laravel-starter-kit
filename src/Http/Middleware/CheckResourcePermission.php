<?php

namespace Lvntr\StarterKit\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dynamically check resource permissions based on route name.
 *
 * Maps route names like "admin.users.index" to permission "users.read"
 * using the last two segments as resource and action.
 *
 * There are TWO independent failure axes here; they are not the same thing
 * and they do not share a flag:
 *
 * 1. UNMAPPED — a permission WAS derived, but no row with that name is
 *    seeded in the database. FAIL-CLOSED by default: a forgotten permission
 *    row must never silently expose an endpoint on a public host (staging /
 *    uat / demo included).
 *      - local → allow + warn (developer DX: seed it and move on).
 *      - any other environment (production, staging, uat, demo, testing, …)
 *        → deny (AuthorizationException).
 *      - Opt-out: set `starter-kit.permissions.allow_unmapped` to true to
 *        restore the legacy "allow on any non-production env" behavior
 *        (production still denies regardless of the flag).
 *
 * 2. UNRESOLVED — NO permission could be derived at all: the route carries
 *    no name, its name has fewer than two segments, or its action segment is
 *    absent from ACTION_ABILITY_MAP. Historically all three passed through in
 *    total silence, which is precisely how an ungated endpoint hides.
 *      - This release: allow + a throttled warning naming the route, so the
 *        gap is visible in the log instead of invisible.
 *      - `starter-kit.permissions.allow_unresolved` (default true, see
 *        self::ALLOW_UNRESOLVED_DEFAULT) switches that to deny. The deny path
 *        is fully implemented and reachable by config today. A NEW project is
 *        seeded with false by sk:install; an existing one keeps the permissive
 *        default until its operator sets the key.
 *      - self::PACKAGE_UNRESTRICTED_ROUTES (exact names, shipped) and
 *        `starter-kit.permissions.unrestricted_routes` (Str::is patterns,
 *        consumer-owned) together list routes that are deliberately
 *        permission-free: they pass silently and are never denied. That union
 *        is consulted ONLY on this axis — it can never disable the check for a
 *        route whose permission DOES resolve, so a stray wildcard cannot open
 *        the panel.
 *      - The kit's own routes are kept OFF this axis entirely. Names the
 *        generic segment rule cannot resolve are pinned in
 *        self::PACKAGE_ROUTE_PERMISSIONS, so no route this package ships
 *        depends on allow_unresolved staying true.
 *
 *    DELIBERATE ASYMMETRY vs. allow_unmapped: once flipped, allow_unresolved
 *    still applies in production, where allow_unmapped is clamped off. The
 *    reason is what the operator can do about it. An unmapped permission is a
 *    DATA gap — seed the row on the host and the endpoint works, so leaving a
 *    config hatch open in production would only ever be used to skip that
 *    seed. An unresolved route is a STRUCTURAL mismatch between the route
 *    table and the ability map: it is fixed by renaming a route or shipping
 *    code, never from `.env` or a seeder. Denying it in production with no
 *    reachable hatch would brick the one host where it actually breaks, so
 *    the hatch has to exist exactly there.
 *
 * The explicit-argument form (`check.permission:reports.read`) never touches
 * either axis for resolution: the permission is given, so only the UNMAPPED
 * seeding check applies to it.
 *
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
     * Fallback for `starter-kit.permissions.allow_unresolved`.
     *
     * DO NOT CHANGE THIS LINE TO `false` AS PART OF A RELEASE.
     *
     * config/starter-kit.php references this constant as its env() fallback
     * rather than repeating a literal, which is what makes the value consistent
     * — and also what makes editing it dangerous. Both populations land here:
     * an app with no published config, and an app whose published copy predates
     * the key (mergeConfigFrom is shallow, so their `permissions` array hides
     * the package's entirely). Editing this constant would therefore turn
     * previously-passing requests into 403s on a plain `composer update`, in
     * apps that changed nothing of their own. There is no release note that
     * makes that acceptable inside a release line; if the default is ever
     * revisited it belongs in a major, with its own upgrade path.
     *
     * The asymmetry consumers actually want is delivered at install time
     * instead: InstallCommand seeds STARTER_KIT_ALLOW_UNRESOLVED_ROUTES=false
     * into a NEW project's .env (see its FIRST_INSTALL_ONLY_ENV_KEYS, which
     * keeps the re-install merge path from leaking the key into an existing
     * app). A fresh install is strict; a live app opts in when its operator
     * decides. Locked by tests/Feature/BackwardCompat/LegacyPublishedConfigTest
     * and tests/Feature/Install/EnvMergeTest.
     */
    public const ALLOW_UNRESOLVED_DEFAULT = true;

    /**
     * Every alias this middleware is registered under (src/Bootstrap.php and
     * StarterKitServiceProvider both bind it twice). Used to spot a route's own
     * argumented `check.permission:<perm>` entry.
     *
     * @var list<string>
     */
    public const PERMISSION_MIDDLEWARE_ALIASES = [
        'check.permission',
        'check.resource.permission',
        self::class,
    ];

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
     * Map a route's LAST name segment to a permission ability.
     *
     * Generic CRUD verbs only. A verb belongs here when "<anything>.<verb>"
     * means the same thing in every app — the resource is then read from the
     * second-to-last segment. A verb whose meaning depends on WHICH resource
     * it hangs off (bulk, run, syncPermissions, testMail) does NOT belong
     * here: a global entry would silently gate an unrelated consumer route
     * that happens to end in the same word. Those go in
     * PACKAGE_ROUTE_PERMISSIONS, keyed by the full route name.
     *
     * DELIBERATELY ABSENT: dt, fetch, add, save, remove. The kit's only users
     * of those suffixes are the five settings.contentLanguages.* routes, and
     * those are pinned by exact name in PACKAGE_ROUTE_PERMISSIONS at higher
     * precedence — so a global entry would buy this package nothing while
     * silently gating a CONSUMER route (orders.save -> orders.update) that
     * passes unresolved today. Adding them is a backward-compatibility break
     * with no upside; do not re-add them.
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
     * The kit's OWN route-name → permission contract, by full route name.
     *
     * Every entry here is a route this package ships whose name the generic
     * segment rule cannot resolve (or resolves WRONGLY). Consulted BEFORE
     * ACTION_ABILITY_MAP — see resolutionFor() for why the precedence is not
     * optional — and before the unresolved branch.
     *
     * It lives in src/ rather than in the route stub on purpose. sk:update is
     * hash-tracked, so a consumer who edited their published route file would
     * never receive a stub-side fix; the middleware reaches every install
     * unconditionally.
     *
     * Names carry NO "admin." prefix: bootstrap/app.php mounts routes/web.php
     * through withRouting(web: ...), which adds the `web` middleware group and
     * no name prefix. The API names come from routes/api.php's own
     * ->name('api.v1.').
     *
     * These names are now a CONTRACT. Renaming a route in stubs/routes/ without
     * updating this map drops that route back into the unresolved branch.
     *
     * @var array<string, string>
     */
    public const PACKAGE_ROUTE_PERMISSIONS = [
        // --- Content languages (stubs/routes/web/content-language-route.php)
        // Content languages are a SETTING, not a resource: the kit seeds no
        // "contentLanguages.*" permission. The generic rule would read the
        // second-to-last segment and derive exactly that non-existent
        // permission, which on a fail-closed host turns a working Settings tab
        // into a 403 — hence the exact-name entries and the precedence.
        // Each value is the permission the route's own explicit
        // `check.permission:...` argument already enforces, so the group-level
        // parameterless pass can never deny a request the route-level pass
        // would have allowed.
        'settings.contentLanguages.dt' => 'settings.read',
        'settings.contentLanguages.fetch' => 'settings.read',
        'settings.contentLanguages.add' => 'settings.update',
        'settings.contentLanguages.save' => 'settings.update',
        'settings.contentLanguages.remove' => 'settings.update',

        // --- Settings writes (stubs/routes/web/settings-route.php)
        // "settings.update.general" ends in `general`, "settings.upload.logo"
        // in `logo`: the last segment is the settings SECTION, not a verb, so
        // the generic rule reads resource="update"/"upload" and derives
        // nothing. Every one of these already carries an explicit
        // `check.permission:settings.update`, so mapping them to the same
        // permission is behaviour-neutral by construction.
        'settings.update.general' => 'settings.update',
        'settings.update.auth' => 'settings.update',
        'settings.update.mail' => 'settings.update',
        'settings.update.storage' => 'settings.update',
        'settings.update.fileManager' => 'settings.update',
        'settings.update.turnstile' => 'settings.update',
        'settings.update.postman' => 'settings.update',
        'settings.update.apidog' => 'settings.update',
        'settings.update.appearance' => 'settings.update',
        'settings.upload.logo' => 'settings.update',
        'settings.delete.logo' => 'settings.update',
        'settings.upload.appearanceLogo' => 'settings.update',
        'settings.delete.appearanceLogo' => 'settings.update',
        'settings.upload.favicon' => 'settings.update',
        'settings.delete.favicon' => 'settings.update',
        // Sending a test mail writes nothing, but it spends the app's
        // configured SMTP credentials — it is a settings WRITE surface, and it
        // already ships behind `check.permission:settings.update`.
        'settings.testMail' => 'settings.update',

        // --- Roles (stubs/routes/web/role-route.php)
        // syncPermissions rewrites the permission table from config. It is a
        // write against the roles surface; the controller additionally rejects
        // anyone who is not system_admin, so this gate can only ever be the
        // looser of the two.
        'roles.syncPermissions' => 'roles.update',

        // NOTE: roles.bulk / users.bulk are deliberately NOT mapped here — see
        // PACKAGE_UNRESTRICTED_ROUTES for why a single static permission cannot
        // express what those endpoints require.
    ];

    /**
     * Kit routes that are deliberately NOT gated by the dynamic permission
     * check, merged with the consumer's `unrestricted_routes` config.
     *
     * EXACT names only — no wildcards. A wildcard shipped by the package would
     * silently exempt consumer routes that merely share a prefix, which is the
     * one way this list could open the panel.
     *
     * @var list<string>
     */
    public const PACKAGE_UNRESTRICTED_ROUTES = [
        // POST system-health/run. Not "permission-free" in the abstract — the
        // controller opens with Gate::authorize('system.health.view')
        // (src/Http/Controllers/Admin/SystemHealthController.php) and the route
        // group carries role:system_admin. Both of those are enforced whatever
        // this middleware does, and the second one means only system_admin ever
        // arrives here — a role that Gate::before short-circuits to true, so a
        // permission entry could never DENY a legitimate caller either.
        // What it COULD do is deny an illegitimate-looking one: permissionExists()
        // reads the permissions TABLE, not the gate, and "system.health.view"
        // comes from the `custom_permissions` array of the app-owned
        // config/permission-resources.php (added in v13.5.3). An install whose
        // published copy predates that — sk:update preserves an edited config
        // rather than refreshing it — has no such row, and a mapping here would
        // 403 its system_admin on a page that works today. Zero upside, real
        // downside: the route is declared, not gated.
        'system-health.run',

        // Every authenticated API consumer must be able to end its own session
        // and read its own identity; neither can depend on a resource
        // permission. In the api.php the kit currently ships these load under
        // `auth:api` only and never reach this middleware at all, so the
        // entries are inert there — they exist so a consumer whose own route
        // orchestrator does put auth-route.php behind check.permission is not
        // locked out of logout when allow_unresolved flips to deny.
        'api.v1.auth.logout',
        'api.v1.auth.me',

        // POST roles/bulk, POST users/bulk. Declared, not gated — because the
        // ability these endpoints require is a property of the ACTION named in
        // the request body, not of the route. BulkActionDispatcher authorizes
        // every item against the handler's own ability (BulkDeleteUserAction
        // requires users.delete), so authorization here is action-accurate in a
        // way no static route→permission mapping can be.
        //
        // Any single mapping would only over-deny, and each candidate breaks a
        // different real role: `.delete` 403s a consumer who registers a
        // non-destructive bulk action (activate, export) and holds only
        // `.update`; `.update` 403s a role holding `.delete` without `.update`;
        // `.read` 403s a role holding `.delete` without `.read`. These
        // abilities are independent in permission-resources.php, so none of
        // those combinations is hypothetical — and all three work today, since
        // the routes currently derive nothing at all.
        //
        // The middleware layer therefore adds no security here (the per-item
        // check is strictly tighter) and can only take some of it away. Listing
        // them keeps that decision explicit and keeps them off the unresolved
        // axis, so the flip cannot silently break bulk actions later.
        'roles.bulk',
        'users.bulk',
    ];

    /**
     * Route keys already warned about for the UNRESOLVED axis.
     *
     * Throttle scope is deliberately the request lifecycle and no longer: a
     * plain PHP-FPM worker resets statics between requests, and flushCache()
     * clears it for a long-lived Octane worker. Anything more durable (a cache
     * entry, a per-day marker) would hide the gap from the consumer entirely,
     * which is the failure this warning exists to prevent.
     *
     * Keyed by route NAME — or, for a nameless route, method + the route URI
     * PATTERN — so the set stays bounded by the route table and never grows
     * with traffic or with path parameters.
     *
     * @var array<string, true>
     */
    private static array $warnedUnresolvedRoutes = [];

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
            $route = $request->route();
            $route = $route instanceof Route ? $route : null;

            $permission = $route !== null ? self::resolutionFor($route) : null;

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

            // Single exit for every UNRESOLVED shape — no route name, fewer
            // than two name segments, or an unmapped action segment. They are
            // one condition ("no permission could be derived"), not three, so
            // they share one policy instead of three silent early returns.
            if (! $permission) {
                return $this->handleUnresolvedRoute($request, $route, $next);
            }
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
     * The permission a route requires, or null when none can be derived.
     *
     * "admin.users.index" → "users.read"
     * "users.store"       → "users.create"
     * unnamed / "dashboard" / "admin.users.reorder" → null (UNRESOLVED)
     *
     * SINGLE SOURCE OF TRUTH for the route-name → permission rule. It is
     * public so the sk:doctor route audit can ask the same question the
     * middleware asks at request time; a second copy of this segment parsing
     * anywhere else will drift from this one and report a gate that does not
     * exist. Call this — do not re-implement it.
     *
     * Deliberately narrow: it answers only "which permission, if any". The
     * unresolved POLICY (warn / deny / exempt) is this middleware's own and
     * is not exposed.
     */
    public static function resolutionFor(Route $route): ?string
    {
        $routeName = $route->getName();

        if (! $routeName) {
            return null;
        }

        // The kit's own contract wins over the generic suffix rule, and the
        // precedence is load-bearing rather than stylistic. An exact full-name
        // entry is strictly more specific than "look at the last two segments",
        // and for at least one shipped route the two DISAGREE:
        // "settings.contentLanguages.dt" would generically derive
        // "contentLanguages.read" — a permission this kit never seeds — so with
        // the opposite order a fail-closed host would 403 the Settings tab that
        // works today. Checking the exact map first also means adding a generic
        // verb to ACTION_ABILITY_MAP can never retroactively re-point a route
        // the package has already pinned.
        if (isset(self::PACKAGE_ROUTE_PERMISSIONS[$routeName])) {
            return self::PACKAGE_ROUTE_PERMISSIONS[$routeName];
        }

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
     * Policy for a request whose required permission could not be derived.
     *
     * Order matters: an explicitly exempt route passes SILENTLY (it is not a
     * gap, so it is neither logged nor denied), everything else is measured
     * against allow_unresolved, and only a request that is allowed through
     * gets warned about.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws AuthorizationException
     */
    private function handleUnresolvedRoute(Request $request, ?Route $route, Closure $next): Response
    {
        $routeName = $route?->getName();

        if ($this->isUnrestrictedRoute($routeName)) {
            return $next($request);
        }

        // A route can sit inside the scaffold's PARAMETERLESS `check.permission`
        // group and additionally declare its own `check.permission:reports.read`.
        // Laravel runs both entries, so the parameterless pass lands here even
        // though the route is explicitly gated. Denying it would make the
        // remediation the upgrade guide prescribes ("gate it with an explicit
        // permission argument") not actually work — and would 403 a route that
        // is, in fact, the best-protected kind there is. The explicit entry owns
        // the decision; this pass steps aside silently.
        if ($this->hasExplicitPermissionArgument($route)) {
            return $next($request);
        }

        if (! $this->allowsUnresolvedRoute()) {
            throw new AuthorizationException('You are not authorized for this action.');
        }

        $this->warnUnresolvedRouteOnce($request, $route, $routeName);

        return $next($request);
    }

    /**
     * Whether the route declares its own permission-middleware entry WITH an
     * argument (`check.permission:reports.read`), alongside the parameterless
     * group entry that brought us here.
     *
     * Only the argumented form counts: the bare alias is the group pass we are
     * currently executing, and treating it as an explicit gate would exempt
     * every route in the group at once.
     *
     * PUBLIC so the sk:doctor unresolved-route audit applies exactly this rule
     * instead of re-deriving it — two copies of a permission rule diverge.
     */
    public static function hasExplicitPermissionArgument(?Route $route): bool
    {
        if ($route === null) {
            return false;
        }

        foreach ((array) $route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware) || ! str_contains($middleware, ':')) {
                continue;
            }

            [$alias, $argument] = explode(':', $middleware, 2);

            if ($argument === '') {
                continue;
            }

            if (in_array($alias, self::PERMISSION_MIDDLEWARE_ALIASES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether an unresolved route should fail OPEN (warn + allow) rather than
     * fail CLOSED (deny).
     *
     * Config-driven with no environment clamp, unlike allowsUnmappedPermission
     * — see the class docblock for why the asymmetry is intentional. The
     * fallback is the class constant, so a consumer running a stale published
     * config (whose `permissions` array predates this key; mergeConfigFrom is
     * a shallow merge and will NOT inject it) still gets the shipped default
     * and follows the scheduled flip.
     */
    private function allowsUnresolvedRoute(): bool
    {
        return (bool) config('starter-kit.permissions.allow_unresolved', self::ALLOW_UNRESOLVED_DEFAULT);
    }

    /**
     * Whether the route is declared deliberately permission-free — either by
     * the package (self::PACKAGE_UNRESTRICTED_ROUTES, exact names) or by the
     * consumer (`starter-kit.permissions.unrestricted_routes`, Str::is
     * wildcards, e.g. "api.v1.auth.*").
     *
     * The package list is checked first and independently of config, so a
     * consumer running a stale published config still gets the kit's own
     * exemptions; it is a union, so a consumer can never SHRINK it by
     * overwriting the config key.
     *
     * A nameless route can NEVER match: there is no name to declare in config,
     * and testing null against a wildcard would silently exempt every unnamed
     * endpoint in the app at once.
     */
    private function isUnrestrictedRoute(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        if (in_array($routeName, self::PACKAGE_UNRESTRICTED_ROUTES, true)) {
            return true;
        }

        $patterns = config('starter-kit.permissions.unrestricted_routes', []);

        if (! is_array($patterns)) {
            return false;
        }

        $patterns = array_values(array_filter($patterns, 'is_string'));

        return $patterns !== [] && Str::is($patterns, $routeName);
    }

    /**
     * Warn once per route (see self::$warnedUnresolvedRoutes) that an endpoint
     * is running with no derivable permission.
     */
    private function warnUnresolvedRouteOnce(Request $request, ?Route $route, ?string $routeName): void
    {
        // Route URI PATTERN, not the concrete request URI: it is bounded by the
        // route table (a path parameter cannot grow the seen-set) and it never
        // carries a query string, which is where a token would be.
        $uri = $route?->uri() ?? $request->path();

        $key = ($routeName !== null && $routeName !== '')
            ? 'name:'.$routeName
            : 'uri:'.$request->getMethod().' '.$uri;

        if (isset(self::$warnedUnresolvedRoutes[$key])) {
            return;
        }

        self::$warnedUnresolvedRoutes[$key] = true;

        Log::warning('check.resource.permission: no permission could be derived from the route; allowing (starter-kit.permissions.allow_unresolved is enabled). Give the route a "<resource>.<action>" name with a mapped action, gate it with an explicit permission argument, or declare it under starter-kit.permissions.unrestricted_routes.', [
            'route' => $routeName,
            'method' => $request->getMethod(),
            'uri' => $uri,
            'environment' => app()->environment(),
        ]);
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
     * Forget the cached permission-name set and the unresolved-route warn set.
     *
     * Called after sk:seed-permissions so a freshly seeded permission is
     * honored immediately instead of waiting out the short TTL — important
     * under Octane where the process (and its cache) is long-lived. The warn
     * set rides along for the same reason: on a long-lived worker a static
     * would otherwise silence the unresolved-route warning for the whole
     * worker lifetime instead of the request lifecycle it is scoped to.
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::$warnedUnresolvedRoutes = [];
    }
}
