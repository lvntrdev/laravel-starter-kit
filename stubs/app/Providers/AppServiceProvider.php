<?php

namespace App\Providers;

use App\Domain\FileManager\Support\ContextRegistry;
use App\Listeners\UpdateLastLogin;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Only available in main project (dev tooling) — never required by consumers.
        if ($this->app->environment('local') && class_exists(IdeHelperServiceProvider::class)) {
            $this->app->register(IdeHelperServiceProvider::class);
        }

        $this->app->singleton(ContextRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(Login::class, UpdateLastLogin::class);

        // Every FormRequest that relies on Password::defaults() picks this
        // policy up automatically. Raises the bar from Laravel's 8-char
        // default to 10+ chars with mixed case, digits and symbols.
        Password::defaults(function () {
            return Password::min(10)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();
        });
    }
}
