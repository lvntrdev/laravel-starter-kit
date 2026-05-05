<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;

/**
 * FileManager facade.
 *
 * Resolves to the `lvntr.file-manager` container binding for future manager
 * APIs. The static {@see FileManager::routes()} helper lets consumer apps
 * mount the vendor FileManager route group with a single line:
 *
 *     // routes/web/file-manager-route.php
 *     \Lvntr\StarterKit\Facades\FileManager::routes();
 *
 * Route names, URL prefix and parameter names match the previously published
 * stub 1:1 so Wayfinder-generated typed routes and existing axios callers
 * keep working unchanged.
 *
 * @method static void routes(array $options = [])
 */
class FileManager extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'lvntr.file-manager';
    }

    /**
     * Mount the vendor FileManager routes.
     *
     * The route definition file is intentionally inert (no outer middleware),
     * so the caller controls the auth/permission stack. When invoked from a
     * consumer's `routes/web/file-manager-route.php` stub, the orchestrator
     * in `routes/web.php` already wraps it with the desired middleware (e.g.
     * `auth + verified`). When auto-mounted by the package ServiceProvider on
     * a fresh install, the provider applies its own middleware group.
     *
     * Optional overrides:
     *   - middleware: Additional middleware to wrap the group with. Layered
     *                 outside the existing group; route-level middleware
     *                 declared in the route file (e.g. `throttle:30,1` on
     *                 the upload endpoint) keeps applying.
     *
     * @param  array{middleware?: array<int, string>|string}  $options
     */
    public static function routes(array $options = []): void
    {
        $loader = static function (): void {
            require __DIR__.'/../routes/file-manager.php';
        };

        if (! empty($options['middleware'])) {
            Route::middleware($options['middleware'])->group($loader);

            return;
        }

        $loader();
    }
}
