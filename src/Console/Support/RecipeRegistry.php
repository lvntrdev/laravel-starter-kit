<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Support;

use InvalidArgumentException;
use Lvntr\StarterKit\Console\Commands\PublishCommand;

/**
 * Static catalog of optional observability/monitoring recipes offered by
 * `sk:install` (and, later, `sk:publish`/`sk:doctor`). Mirrors the shape of
 * {@see PublishCommand::PUBLISHABLE_TAGS}:
 * a single source-of-truth array read by whichever command needs it.
 *
 * This class is intentionally side-effect-free — it only returns data. Running
 * `composer require` / `artisan` for a selected recipe is the caller's job
 * (`InstallCommand`), not this registry's.
 */
class RecipeRegistry
{
    /**
     * `migrations` lists the basenames (no date prefix) of the migrations a
     * recipe's `post_install` publishes into database/migrations. Laravel's
     * vendor:publish re-dates a published migration to "now", so a second run
     * cannot see the first copy; the installer uses these names to skip a
     * recipe that is already published.
     *
     * @var array<string, array{composer: string, dev: bool, label: string, post_install: list<string>, migrations: list<string>}>
     */
    private const RECIPES = [
        // NOT a --dev requirement, deliberately: `telescope:install` registers
        // App\Providers\TelescopeServiceProvider (which extends Telescope's own
        // provider) in bootstrap/providers.php, so a production deploy running
        // `composer install --no-dev` would boot into a missing parent class and
        // fail to start. Telescope's generated provider already filters recording
        // down to exceptions/failed jobs outside local, which is what keeps it
        // cheap in production. A local-only install is still possible by hand:
        // https://laravel.com/docs/13.x/telescope#local-only-installation
        'telescope' => [
            'composer' => 'laravel/telescope',
            'dev' => false,
            'label' => 'Laravel Telescope (debugging/request inspector)',
            'post_install' => ['telescope:install'],
            'migrations' => ['create_telescope_entries_table'],
        ],
        // Pulse registers its migrations as PUBLISHABLE, not as an auto-loaded
        // migration path, so the installer's later `migrate` creates no Pulse
        // table unless they are published first.
        'pulse' => [
            'composer' => 'laravel/pulse',
            'dev' => false,
            'label' => 'Laravel Pulse (application performance monitoring)',
            'post_install' => ['vendor:publish --tag=pulse-migrations'],
            'migrations' => ['create_pulse_tables'],
        ],
        'horizon' => [
            'composer' => 'laravel/horizon',
            'dev' => false,
            'label' => 'Laravel Horizon (Redis queue dashboard/supervisor)',
            'post_install' => ['horizon:install'],
            'migrations' => [],
        ],
        'sentry' => [
            'composer' => 'sentry/sentry-laravel',
            'dev' => false,
            'label' => 'Sentry (error tracking)',
            'post_install' => ['vendor:publish --tag=sentry-config'],
            'migrations' => [],
        ],
    ];

    /**
     * @return array<string, array{composer: string, dev: bool, label: string, post_install: list<string>, migrations: list<string>}>
     */
    public static function all(): array
    {
        return self::RECIPES;
    }

    /**
     * @return array{composer: string, dev: bool, label: string, post_install: list<string>, migrations: list<string>}
     */
    public static function get(string $key): array
    {
        if (! isset(self::RECIPES[$key])) {
            throw new InvalidArgumentException("Unknown observability recipe: {$key}");
        }

        return self::RECIPES[$key];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_map(fn (array $recipe): string => $recipe['label'], self::RECIPES);
    }
}
