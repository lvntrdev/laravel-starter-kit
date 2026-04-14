<?php

namespace Lvntr\StarterKit;

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/**
 * Runtime bootstrap wiring for the starter kit.
 *
 * Consumers call these helpers from `bootstrap/app.php` so the Laravel
 * default file stays intact except for two delegating lines. Class names
 * are written as fully qualified strings — the referenced classes come
 * from published stubs that exist in the consumer application, not in
 * this package.
 */
class Bootstrap
{
    /**
     * Register starter kit middleware, aliases, and auth redirect targets.
     */
    public static function middleware(Middleware $middleware): void
    {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
            'guest' => \Illuminate\Auth\Middleware\RedirectIfAuthenticated::class,
            'check.permission' => \App\Http\Middleware\CheckResourcePermission::class,
        ]);

        $middleware->redirectTo(guests: '/login', users: '/dashboard');
    }

    /**
     * Register the API exception handler for JSON responses.
     */
    public static function exceptions(Exceptions $exceptions): void
    {
        \App\Exceptions\ApiExceptionHandler::register($exceptions);
    }
}
