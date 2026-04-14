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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class StarterKitServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/starter-kit.php', 'starter-kit');
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

        $accessDays = (int) config('starter-kit.passport.access_token_days', 15);
        $refreshDays = (int) config('starter-kit.passport.refresh_token_days', 30);
        $personalMonths = (int) config('starter-kit.passport.personal_token_months', 6);

        Passport::tokensExpireIn(now()->addDays($accessDays));
        Passport::refreshTokensExpireIn(now()->addDays($refreshDays));
        Passport::personalAccessTokensExpireIn(now()->addMonths($personalMonths));

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
     * Domain commands are available but never published.
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\InstallCommand::class,
                Console\Commands\UpdateCommand::class,
                Console\Commands\UpgradeCommand::class,
                Console\Commands\PublishCommand::class,
                Console\Commands\MakeDomainCommand::class,
                Console\Commands\RemoveDomainCommand::class,
                Console\Commands\EnvSyncCommand::class,
            ]);
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
            __DIR__.'/../resources/js/components' => resource_path('js/components/Lvntr-Starter-Kit'),
        ], 'starter-kit-components');
    }

    /**
     * Register package migrations.
     */
    private function registerMigrations(): void
    {
        if ($this->app->runningInConsole() && config('starter-kit.run_migrations', false)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
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
