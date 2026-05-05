<?php

namespace Lvntr\StarterKit;

use App\Http\Middleware\AssignTraceId;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ValidateTurnstile;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Lvntr\StarterKit\Exceptions\ApiExceptionHandler;
use Lvntr\StarterKit\Http\Middleware\CheckResourcePermission;
use Lvntr\StarterKit\Http\Middleware\SecurityHeaders;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

/**
 * Runtime bootstrap wiring for the starter kit.
 *
 * Consumers call these helpers from `bootstrap/app.php` so the Laravel
 * default file stays intact except for two delegating lines.
 *
 * App-specific middleware (AssignTraceId, HandleInertiaRequests, SetLocale,
 * ValidateTurnstile) are still resolved from the consumer app's published
 * stubs — they contain app-specific logic (trace IDs, Inertia shared data,
 * locale handling, Turnstile secrets) that belongs in the consumer.
 *
 * Package middleware (CheckResourcePermission, SecurityHeaders) and the
 * exception handler (ApiExceptionHandler) are now resolved from the vendor
 * namespace so consumers no longer need to publish these stubs.
 * Existing published copies remain functional as long as the consumer
 * registers them via the container binding (see UPGRADE.md).
 */
class Bootstrap
{
    /**
     * Register starter kit middleware, aliases, and auth redirect targets.
     */
    public static function middleware(Middleware $middleware): void
    {
        $middleware->web(append: [
            SetLocale::class,
            HandleInertiaRequests::class,
            SecurityHeaders::class,
        ]);

        // AssignTraceId runs first on the API group so every downstream
        // handler (success path via ApiResponse, error path via
        // ApiExceptionHandler) can read a single shared trace id from the
        // request attributes.
        $middleware->api(prepend: [
            AssignTraceId::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'check.permission' => CheckResourcePermission::class,
            'turnstile' => ValidateTurnstile::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        $middleware->redirectTo(guests: '/login', users: '/dashboard');
    }

    /**
     * Register the API exception handler for JSON responses.
     */
    public static function exceptions(Exceptions $exceptions): void
    {
        ApiExceptionHandler::register($exceptions);
    }
}
