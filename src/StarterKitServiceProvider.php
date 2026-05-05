<?php

namespace Lvntr\StarterKit;

use App\Enums\RoleEnum;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Lvntr\StarterKit\Domain\FileManager\Support\ContextRegistry;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;
use Lvntr\StarterKit\Domain\Shared\Contracts\PipeableAction;
use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;
use Lvntr\StarterKit\Domain\Shared\Pipelines\ActionPipeline;
use Lvntr\StarterKit\Domain\Shared\Services\DefinitionService;
use Lvntr\StarterKit\Exceptions\ApiException;
use Lvntr\StarterKit\Exceptions\ApiExceptionHandler;
use Lvntr\StarterKit\Facades\FileManager as FileManagerFacade;
use Lvntr\StarterKit\Http\Middleware\CheckResourcePermission;
use Lvntr\StarterKit\Http\Middleware\SecurityHeaders;
use Lvntr\StarterKit\Http\Responses\ApiResponse;

class StarterKitServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        // Helper autoload order: load the consumer-published copy FIRST (when
        // present) so its function declarations register before the vendor
        // helper's `function_exists` guards run. The vendor file then fills in
        // any helper the consumer did not override.
        //
        // We load helpers here (instead of via `autoload.files` in
        // composer.json) because Composer would load the vendor copy before
        // the consumer's, and the symlinked `vendor/lvntr/laravel-starter-kit`
        // path makes a `dirname(__DIR__, 4)` walk inside the helper file
        // unreliable for locating the consumer copy. Loading from the
        // ServiceProvider lets us use `base_path()` directly.
        $userHelpers = base_path('app/Helpers/sk-helpers.php');
        if (is_file($userHelpers)) {
            require_once $userHelpers;
        }

        require_once __DIR__.'/sk-helpers.php';

        $this->mergeConfigFrom(__DIR__.'/../config/starter-kit.php', 'starter-kit');

        // Task 6/7 hook: file-manager config will live at
        // config/file-manager.php in the package once that domain is moved
        // vendor-first. Guard with file_exists() so we can land this hook
        // in Task 1 without breaking anything until the file ships.
        $fileManagerConfig = __DIR__.'/../config/file-manager.php';
        if (file_exists($fileManagerConfig)) {
            $this->mergeConfigFrom($fileManagerConfig, 'file-manager');
        }

        // Backward-compatibility aliases: consumer apps that were generated
        // before v13.5.1 still import from `App\` namespace. These aliases
        // make those imports resolve to the vendor classes transparently so
        // consumer app code (domain actions, DTOs, models, controllers)
        // continues to work without any changes after the published stubs
        // are removed during cleanup.
        //
        // New installs should import directly from `Lvntr\StarterKit\*`.
        // Note: PHP traits cannot be safely aliased via class_alias() —
        // HasActivityLogging and HasMediaCollections are NOT included here.
        // Consumer models must update their `use` import to the vendor
        // namespace directly (see UPGRADE_.md migration notes).
        $bcAliases = [
            'App\Domain\Shared\Actions\BaseAction' => BaseAction::class,
            'App\Domain\Shared\Contracts\PipeableAction' => PipeableAction::class,
            'App\Domain\Shared\DTOs\BaseDTO' => BaseDTO::class,
            'App\Domain\Shared\Pipelines\ActionPipeline' => ActionPipeline::class,
            'App\Domain\Shared\Services\DefinitionService' => DefinitionService::class,
            'App\Exceptions\ApiException' => ApiException::class,
            'App\Exceptions\ApiExceptionHandler' => ApiExceptionHandler::class,
            'App\Http\Responses\ApiResponse' => ApiResponse::class,
            'App\Http\Middleware\CheckResourcePermission' => CheckResourcePermission::class,
            'App\Http\Middleware\SecurityHeaders' => SecurityHeaders::class,
            'App\Domain\FileManager\Support\ContextRegistry' => ContextRegistry::class,
        ];

        // Resolve the consumer base path. `base_path()` is unavailable during
        // ServiceProvider::register() in some bootstrap contexts, so fall back
        // to the application instance when the helper is missing.
        $basePath = function_exists('base_path') ? base_path() : $this->app->basePath();

        foreach ($bcAliases as $appClass => $vendorClass) {
            // If the consumer ships their own implementation of the App\* class,
            // skip alias registration entirely. Without this guard, class_alias()
            // would register the vendor class against the alias name and PHP's
            // class resolution would short-circuit before Composer's autoloader
            // ever loads the consumer's file — silently dropping their override.
            $relativePath = str_replace('\\', '/', $appClass);
            if (str_starts_with($relativePath, 'App/')) {
                $relativePath = substr($relativePath, 4);
            }
            $appPath = $basePath.'/app/'.$relativePath.'.php';

            if (file_exists($appPath)) {
                continue;
            }

            if (! class_exists($appClass, false) && ! interface_exists($appClass, false)) {
                class_alias($vendorClass, $appClass);
            }
        }
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configurePassport();
        $this->configureGates();
        $this->configureRateLimiting();
        $this->configureScramble();
        $this->registerCommands();
        $this->registerTranslations();
        $this->registerPublishables();
        $this->registerMigrations();
        $this->registerViews();

        // Middleware aliases — registered here so new vendor-first installs
        // resolve both alias names to the same vendor class. ServiceProvider
        // boot() runs AFTER bootstrap/app.php's withMiddleware() closure, so
        // even when consumer apps call Bootstrap::middleware() and register
        // 'check.permission' with their own App\Http\Middleware\CheckResourcePermission,
        // the vendor alias re-registration here wins last. Functionally this
        // is safe: the consumer's CheckResourcePermission and the vendor's
        // implementation both delegate to the same Spatie permission table.
        // Consumers needing a true override should bind the vendor class to
        // their own subclass via the container, not via alias re-registration.
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('check.permission', CheckResourcePermission::class);
        $router->aliasMiddleware('check.resource.permission', CheckResourcePermission::class);

        $this->registerRoutes();
    }

    /**
     * Configure Eloquent strict mode.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    /**
     * Configure Passport token lifetimes + optional scopes.
     */
    private function configurePassport(): void
    {
        if (! class_exists('Laravel\Passport\Passport')) {
            return;
        }

        // Access token TTL: prefer minutes-based config, fall back to the
        // legacy `access_token_days` key when explicitly set.
        $accessMinutes = (int) config('starter-kit.passport.access_token_minutes', 60);
        $legacyAccessDays = config('starter-kit.passport.access_token_days');
        if ($legacyAccessDays !== null && $legacyAccessDays !== '') {
            $accessMinutes = (int) $legacyAccessDays * 24 * 60;
        }

        $refreshDays = (int) config('starter-kit.passport.refresh_token_days', 14);

        // Personal access tokens: prefer days-based config, fall back to
        // the legacy `personal_token_months` key when explicitly set.
        $personalDays = (int) config('starter-kit.passport.personal_token_days', 30);
        $legacyPersonalMonths = config('starter-kit.passport.personal_token_months');
        if ($legacyPersonalMonths !== null && $legacyPersonalMonths !== '') {
            $personalDays = (int) $legacyPersonalMonths * 30;
        }

        Passport::tokensExpireIn(now()->addMinutes($accessMinutes));
        Passport::refreshTokensExpireIn(now()->addDays($refreshDays));
        Passport::personalAccessTokensExpireIn(now()->addDays($personalDays));

        $scopes = config('starter-kit.passport.scopes', []);

        if (is_array($scopes) && $scopes !== []) {
            Passport::tokensCan($scopes);

            $defaultScopes = config('starter-kit.passport.default_scopes', []);

            if (is_array($defaultScopes) && $defaultScopes !== []) {
                Passport::setDefaultScope($defaultScopes);
            }
        }
    }

    /**
     * Configure authorization gates.
     */
    private function configureGates(): void
    {
        if (! class_exists('App\Enums\RoleEnum') || ! class_exists('App\Models\User')) {
            return;
        }

        $systemAdminRole = RoleEnum::SystemAdmin;

        Gate::before(function (User $user) use ($systemAdminRole): ?bool {
            return $user->hasRole($systemAdminRole) ? true : null;
        });

        Gate::define('viewPulse', function (User $user) use ($systemAdminRole) {
            return $user->hasRole($systemAdminRole);
        });
    }

    /**
     * Configure rate limiters.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * Configure Scramble API documentation.
     */
    private function configureScramble(): void
    {
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });

        Gate::define('viewApiDocs', function (User $user) {
            return $user->hasPermissionTo('api-docs.read');
        });
    }

    /**
     * Register Artisan commands.
     * Domain commands run from vendor only — they are NOT published to the
     * consumer's app/Console/Commands. Stub copies were removed in v13.5.2;
     * the vendor command registration here is the single source.
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $commands = [
                Console\Commands\InstallCommand::class,
                Console\Commands\UpdateCommand::class,
                Console\Commands\UpgradeCommand::class,
                Console\Commands\PublishCommand::class,
                Console\Commands\MakeDomainCommand::class,
                Console\Commands\RemoveDomainCommand::class,
                Console\Commands\EnvSyncCommand::class,
                Console\Commands\SeedPermissionsCommand::class,
            ];

            // Register the vendor PurgeFileManagerTrashCommand only when the
            // consumer app does not define its own version. The signature
            // 'file-manager:purge-trash' must appear exactly once — duplicate
            // registration causes an Artisan conflict exception.
            if (! class_exists('App\\Console\\Commands\\PurgeFileManagerTrash')) {
                $commands[] = Console\Commands\PurgeFileManagerTrashCommand::class;
            }

            $this->commands($commands);
        }
    }

    /**
     * Register translation/lang files.
     * Loaded from package namespace: __('starter-kit::admin.menu.dashboard')
     * Users can override by publishing to lang/vendor/starter-kit/
     */
    private function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'starter-kit');
        $this->loadJsonTranslationsFrom(__DIR__.'/../resources/lang');
    }

    /**
     * Register publishable resources.
     *
     * Tag naming convention: every tag is prefixed with `starter-kit-` so
     * the package never collides with consumer-defined tags. Existing tags
     * (`starter-kit-config`, `starter-kit-lang`, `starter-kit-components`)
     * are kept verbatim because `InstallCommand` already references them;
     * Task 1 only adds new placeholder tags for resources that ship in
     * later tasks (views, migrations, stubs, file-manager subset). All
     * placeholder publishes are guarded by file_exists() / is_dir() so
     * `vendor:publish` does not error out before the source ships.
     */
    private function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        // Config
        $this->publishes([
            __DIR__.'/../config/starter-kit.php' => config_path('starter-kit.php'),
        ], 'starter-kit-config');

        // Lang files (optional publish for customization)
        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/starter-kit'),
        ], 'starter-kit-lang');

        // Vue components (optional publish for customization)
        $this->publishes([
            __DIR__.'/../resources/js/components/Lvntr-Starter-Kit' => resource_path('js/components/Lvntr-Starter-Kit'),
        ], 'starter-kit-components');

        // Task 1 placeholders: registered conditionally so they become
        // active automatically once the source files land in later tasks.
        // No existing publish flow changes today.

        // Blade views (Task 2+ may ship a few server-rendered views)
        $viewsPath = __DIR__.'/../resources/views';
        if (is_dir($viewsPath)) {
            $this->publishes([
                $viewsPath => resource_path('views/vendor/starter-kit'),
            ], 'starter-kit-views');
        }

        // Migrations (Task 8 will move package migrations here)
        $migrationsPath = __DIR__.'/../database/migrations';
        if (is_dir($migrationsPath)) {
            $this->publishes([
                $migrationsPath => database_path('migrations'),
            ], 'starter-kit-migrations');
        }

        // Stubs (Task 5+ may publish customizable scaffolding stubs)
        $stubsPath = __DIR__.'/../stubs';
        if (is_dir($stubsPath)) {
            $this->publishes([
                $stubsPath => base_path('stubs/starter-kit'),
            ], 'starter-kit-stubs');
        }

        // FileManager domain publishables (Task 6/7)
        $fileManagerConfig = __DIR__.'/../config/file-manager.php';
        if (file_exists($fileManagerConfig)) {
            $this->publishes([
                $fileManagerConfig => config_path('file-manager.php'),
            ], 'starter-kit-file-manager-config');
        }

        $fileManagerComponentsPath = __DIR__.'/../resources/js/components/Lvntr-Starter-Kit/FileManager';
        if (is_dir($fileManagerComponentsPath)) {
            $this->publishes([
                $fileManagerComponentsPath => resource_path('js/components/Lvntr-Starter-Kit/FileManager'),
            ], 'starter-kit-file-manager-components');
        }
    }

    /**
     * Register package migrations.
     *
     * Default behaviour: auto-load migrations from the package so consumer
     * apps inherit FileManager schema without a publish step. Existing apps
     * that already ran these files have their basenames recorded in the
     * `migrations` table — Laravel keys migration history by basename, so
     * the duplicate vendor copy is silently skipped on the next migrate run.
     * Filenames inside database/migrations/ are therefore immutable.
     */
    private function registerMigrations(): void
    {
        if ($this->app->runningInConsole() && config('starter-kit.run_migrations', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    /**
     * Register vendor FileManager routes.
     *
     * Override mechanism: when the consumer app already ships its own
     * `routes/web/file-manager-route.php` (or the API equivalent) the
     * orchestrator in `routes/web.php` / `routes/api.php` will load that
     * file directly. In that case the package MUST NOT auto-mount — doing
     * so would register the same route names twice and clash with the
     * consumer's customized controller.
     *
     * On a fresh install where neither stub exists, the package mounts the
     * routes itself under the `web` middleware group with `auth + verified`,
     * matching the previously published stub's behaviour.
     */
    private function registerRoutes(): void
    {
        $consumerRouteFiles = [
            base_path('routes/web/file-manager-route.php'),
            base_path('routes/api/file-manager-route.php'),
        ];

        foreach ($consumerRouteFiles as $consumerRouteFile) {
            if (file_exists($consumerRouteFile)) {
                // Consumer owns the route mount (either via the stub
                // one-liner that calls FileManager::routes() or via a fully
                // customized route file pointing to their own controller).
                // Either way the orchestrator in routes/web.php picks it up
                // automatically — nothing for the package to do here.
                return;
            }
        }

        // Fresh install fallback: mount under the standard web auth stack so
        // the FileManager UI works out of the box without requiring a stub
        // route file in the consumer app.
        Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
            FileManagerFacade::routes();
        });
    }

    /**
     * Register package views (Blade templates).
     */
    private function registerViews(): void
    {
        $viewPath = __DIR__.'/../resources/views';

        if (is_dir($viewPath)) {
            $this->loadViewsFrom($viewPath, 'starter-kit');
        }
    }

    /**
     * Get the package base path.
     */
    public static function basePath(string $path = ''): string
    {
        return dirname(__DIR__).($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    /**
     * Get the stubs path.
     */
    public static function stubsPath(string $path = ''): string
    {
        return static::basePath('stubs').($path ? DIRECTORY_SEPARATOR.$path : '');
    }
}
