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
use Inertia\Inertia;
use Laravel\Passport\Passport;
use Lvntr\StarterKit\Domain\FileManager\Policies\MediaPolicy;
use Lvntr\StarterKit\Domain\FileManager\Support\ContextRegistry;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;
use Lvntr\StarterKit\Domain\Shared\Contracts\PipeableAction;
use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;
use Lvntr\StarterKit\Domain\Shared\Pipelines\ActionPipeline;
use Lvntr\StarterKit\Domain\Shared\Services\DefinitionService;
use Lvntr\StarterKit\Exceptions\ApiException;
use Lvntr\StarterKit\Exceptions\ApiExceptionHandler;
use Lvntr\StarterKit\Facades\FileManager as FileManagerFacade;
use Lvntr\StarterKit\Http\Middleware\AssignTraceId;
use Lvntr\StarterKit\Http\Middleware\CheckResourcePermission;
use Lvntr\StarterKit\Http\Middleware\SecurityHeaders;
use Lvntr\StarterKit\Http\Middleware\SetLocale;
use Lvntr\StarterKit\Http\Middleware\ValidateTurnstile;
use Lvntr\StarterKit\Http\Responses\ApiResponse;
use Lvntr\StarterKit\Support\HtmlSanitizer;
use Lvntr\StarterKit\Support\MediaPathGenerator;
use Lvntr\StarterKit\Support\Scramble\ApiResponseExtension;
use Lvntr\StarterKit\Support\TranslatableQueryHelpers;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

        // Backward-compatibility aliases: consumer apps generated before
        // v13.5.1 still import these classes from the `App\` namespace; the
        // aliases make those imports resolve to the vendor classes so consumer
        // code keeps working. New installs should import directly from
        // `Lvntr\StarterKit\*`. The tiering (unconditional vs. consumer-
        // overridable) lives in backwardCompatAliasPlan().
        //
        // `base_path()` can be unavailable mid-bootstrap, so fall back to the
        // application instance when the helper is missing.
        $basePath = function_exists('base_path') ? base_path() : $this->app->basePath();

        foreach ($this->backwardCompatAliasPlan($basePath) as $appClass => $vendorClass) {
            if (! class_exists($appClass, false) && ! interface_exists($appClass, false)) {
                class_alias($vendorClass, $appClass);
            }
        }
    }

    /**
     * Plan the `App\` → vendor backward-compatibility class aliases to register
     * for a consumer rooted at `$basePath`. Pure (no `class_alias` side effects)
     * so the decision is unit-testable.
     *
     * Two tiers:
     *
     *  - **Unconditional.** `App\Http\Responses\ApiResponse` has NO valid
     *    consumer override: a real `App\` subclass breaks the return-type
     *    covariance of `DatatableQueryBuilder::response()` (which returns the
     *    vendor type) in query classes — that is exactly why it is an alias,
     *    not an extension point. It is therefore aliased on EVERY boot, early
     *    (before any controller return-type check) and regardless of any file
     *    at the consumer path. That determinism is the fix for the intermittent
     *    post-install "Return value must be of type App\Http\Responses\
     *    ApiResponse, Lvntr\StarterKit\Http\Responses\ApiResponse returned"
     *    TypeError: previously the alias was deferred to a class_alias-only stub
     *    (`app/Http/Responses/ApiResponse.php`) that declares no class, so it is
     *    absent from the optimized classmap and its load — and thus the alias's
     *    existence and timing — depended on PSR-4 fallback + opcache state.
     *
     *  - **Overridable.** The rest may be replaced by a consumer's own `app/`
     *    class; the alias is skipped when such a file exists so the override
     *    wins (otherwise `class_alias` would short-circuit Composer's autoloader
     *    and silently drop the override).
     *
     * Note: PHP traits cannot be safely aliased via class_alias() —
     * HasActivityLogging/HasMediaCollections are NOT here. DatatableQueryBuilder,
     * HttpsOrLocalhostUrl and TurnstileRule ship a thin App\ subclass shim in the
     * scaffold, so they need no alias here either.
     *
     * @return array<class-string, class-string>
     */
    protected function backwardCompatAliasPlan(string $basePath): array
    {
        // Aliased unconditionally — no valid consumer override exists.
        $plan = [
            'App\Http\Responses\ApiResponse' => ApiResponse::class,
        ];

        // Aliased only when the consumer ships no override at that path.
        $overridable = [
            'App\Domain\Shared\Actions\BaseAction' => BaseAction::class,
            'App\Domain\Shared\Contracts\PipeableAction' => PipeableAction::class,
            'App\Domain\Shared\DTOs\BaseDTO' => BaseDTO::class,
            'App\Domain\Shared\Pipelines\ActionPipeline' => ActionPipeline::class,
            'App\Domain\Shared\Services\DefinitionService' => DefinitionService::class,
            'App\Exceptions\ApiException' => ApiException::class,
            'App\Exceptions\ApiExceptionHandler' => ApiExceptionHandler::class,
            'App\Http\Middleware\CheckResourcePermission' => CheckResourcePermission::class,
            'App\Http\Middleware\SecurityHeaders' => SecurityHeaders::class,
            'App\Http\Middleware\AssignTraceId' => AssignTraceId::class,
            'App\Http\Middleware\SetLocale' => SetLocale::class,
            'App\Http\Middleware\ValidateTurnstile' => ValidateTurnstile::class,
            'App\Support\HtmlSanitizer' => HtmlSanitizer::class,
            'App\Support\TranslatableQueryHelpers' => TranslatableQueryHelpers::class,
            'App\Support\MediaPathGenerator' => MediaPathGenerator::class,
            'App\Support\Scramble\ApiResponseExtension' => ApiResponseExtension::class,
            'App\Domain\FileManager\Support\ContextRegistry' => ContextRegistry::class,
        ];

        foreach ($overridable as $appClass => $vendorClass) {
            $relativePath = str_replace('\\', '/', $appClass);
            if (str_starts_with($relativePath, 'App/')) {
                $relativePath = substr($relativePath, 4);
            }

            if (! file_exists($basePath.'/app/'.$relativePath.'.php')) {
                $plan[$appClass] = $vendorClass;
            }
        }

        return $plan;
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->applyVendorConfigDefaults();
        $this->configureModels();
        $this->configurePassport();
        $this->configureGates();
        $this->configurePolicies();
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
        $this->shareInertiaData();
    }

    /**
     * Share file-manager settings with Inertia so Vue components can read them
     * without explicit prop passing.
     */
    private function shareInertiaData(): void
    {
        if (! class_exists(Inertia::class)) {
            return;
        }

        Inertia::share('fileManagerSettings', fn () => [
            'enable_trash' => (bool) config('file-manager.settings.enable_trash', true),
        ]);
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

        // Laravel 11 varsayılan auth.php'de 'api' guard artık yok.
        // Passport::createToken() guard'ı config'den aradığı için bulamazsa
        // LogicException fırlatır. Kullanıcı kendi guard'ını tanımlamışsa dokunma.
        if (! config('auth.guards.api')) {
            config(['auth.guards.api' => [
                'driver' => 'passport',
                'provider' => 'users',
            ]]);
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
     * FileManager domain share gate'lerini register eder.
     *
     * K4 (security): Gate::policy(Media::class, MediaPolicy::class) kaldırıldı.
     * Policy-based kayıt tüm Media abilities'i (view, delete, update, ...) zorunlu
     * yapar ve MediaPolicy sadece share/revokeShare tanımladığından diğer abilities
     * için false dönüyor — consumer uygulamalarda sessiz erişim regression'ı yaratır.
     *
     * Yerine flat gate tanımları kullanılır. MediaPolicy class'ı internal kullanım için
     * hâlâ mevcuttur; ancak artık Gate'e register edilmez. Flat gate'ler yalnızca
     * kendi ability adlarını etkiler, başka Media abilities'e dokunmaz.
     *
     * Gate::before ile admin kullanıcılar zaten tüm gate'leri atlatır;
     * bu tanımlar non-admin kullanıcılar için ownership'i zorlar.
     */
    private function configurePolicies(): void
    {
        if (! config('file-manager.share.enabled', true)) {
            return;
        }

        $policy = new MediaPolicy;

        Gate::define('share-media', function ($user, Media $media) use ($policy): bool {
            // $user null → guest isteği; auth middleware bunu yakalamış olmalı
            // ama gate seviyesinde de güvenli bir şekilde reddediyoruz.
            if ($user === null) {
                return false;
            }

            return $policy->share($user, $media);
        });

        Gate::define('revoke-share-media', function ($user, Media $media) use ($policy): bool {
            if ($user === null) {
                return false;
            }

            return $policy->revokeShare($user, $media);
        });
    }

    /**
     * Apply the kit's third-party config defaults from vendor.
     *
     * These configs (media-library, activitylog, inertia) are no longer
     * published into the consumer app — the kit ships only the few overrides
     * it requires and applies them at runtime here. `mergeConfigFrom()` cannot
     * be used because the third-party providers already register the same keys
     * and shallow merge never overrides an existing key. Each override is
     * skipped when the consumer published their own copy of that config, so
     * publishing (the optional escape hatch) keeps full control.
     */
    private function applyVendorConfigDefaults(): void
    {
        $configPath = fn (string $file): string => function_exists('config_path')
            ? config_path($file)
            : $this->app->basePath('config/'.$file);

        // media-library: the FileManager Trash feature needs the kit's
        // soft-deletes Media model and the context-aware path generator.
        if (! file_exists($configPath('media-library.php'))) {
            config(['media-library.path_generator' => MediaPathGenerator::class]);

            if (class_exists('App\\Models\\Media')) {
                config(['media-library.media_model' => 'App\\Models\\Media']);
            }
        }

        // activitylog: include soft-deleted subjects in the subject relation.
        if (! file_exists($configPath('activitylog.php'))) {
            config(['activitylog.include_soft_deleted_subjects' => true]);
        }

        // inertia: SSR is opt-in (enable via INERTIA_SSR_ENABLED=true).
        if (! file_exists($configPath('inertia.php'))) {
            config(['inertia.ssr.enabled' => (bool) env('INERTIA_SSR_ENABLED', false)]);
        }
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

        // Teach Scramble to document the ApiResponse envelope. The extension
        // runs from vendor now, so it is registered here rather than relying
        // on a published config/scramble.php in the consumer app.
        config(['scramble.extensions' => array_values(array_unique(array_merge(
            (array) config('scramble.extensions', []),
            [ApiResponseExtension::class],
        )))]);

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
        // DoctorCommand is registered unconditionally so that Artisan::call('sk:doctor')
        // works from web requests (e.g. SystemHealthController::run).
        $this->commands([Console\Commands\DoctorCommand::class]);

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
        //
        // K1 (security): The public share/show endpoint uses withoutMiddleware()
        // inside the route file to strip auth+verified from the outer group.
        // No special handling needed here — the route file is self-contained.
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
