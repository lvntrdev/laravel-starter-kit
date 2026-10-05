<?php

namespace Lvntr\StarterKit\Support;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Http;
use stdClass;
use Throwable;

/**
 * Detect kit-required Composer packages that are missing from the consumer app.
 *
 * The kit's own `composer.json` `require` block is the source of truth for what
 * `sk:install` / `sk:doctor` expect to find installed. A consumer that removed a
 * package (or a lockfile that drifted) should be caught here instead of failing
 * later with an opaque "class not found".
 *
 * Fails soft everywhere: this runs inside console commands, and an exception here
 * must never take down `sk:update`/`sk:doctor` for the operator.
 */
final class KitDependencies
{
    /** Root composer.json script that updates the kit with `-W`, then runs `sk:update`. */
    public const UPDATE_SCRIPT = 'sk-update';

    private const PACKAGIST_METADATA = 'https://repo.packagist.org/p2/lvntr/laravel-starter-kit.json';

    /**
     * Package names required by the kit but not installed in the consumer app.
     *
     * @return list<string>
     */
    public static function missing(): array
    {
        try {
            if (! class_exists(InstalledVersions::class)) {
                return [];
            }

            $composerJsonPath = dirname(__DIR__, 2).'/composer.json';

            if (! is_readable($composerJsonPath)) {
                return [];
            }

            $decoded = json_decode((string) file_get_contents($composerJsonPath), true);

            if (! is_array($decoded) || ! isset($decoded['require']) || ! is_array($decoded['require'])) {
                return [];
            }

            $missing = [];

            foreach (array_keys($decoded['require']) as $name) {
                if (! is_string($name) || $name === 'php' || str_starts_with($name, 'ext-')) {
                    continue;
                }

                if (! InstalledVersions::isInstalled($name)) {
                    $missing[] = $name;
                }
            }

            return $missing;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Kit-managed `lvntr/*` packages the consumer's own composer.json requires
     * directly.
     *
     * The kit brings these in transitively; a root entry only gets in the way.
     * `composer require` writes `^0.0.x` for a 0.0.x package, which pins that
     * exact patch, so the next kit release that raises its floor can never
     * resolve and Composer quietly stays on the older kit.
     *
     * @return list<string>
     */
    public static function rootPinned(?string $rootComposerJsonPath = null): array
    {
        try {
            $rootPath = $rootComposerJsonPath ?? base_path('composer.json');

            if (! is_readable($rootPath)) {
                return [];
            }

            $kit = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true);
            $root = json_decode((string) file_get_contents($rootPath), true);

            if (! is_array($kit) || ! is_array($root)) {
                return [];
            }

            $rootNames = array_keys(array_merge(
                is_array($root['require'] ?? null) ? $root['require'] : [],
                is_array($root['require-dev'] ?? null) ? $root['require-dev'] : [],
            ));

            return array_values(array_filter(
                array_keys(is_array($kit['require'] ?? null) ? $kit['require'] : []),
                fn ($name) => is_string($name) && str_starts_with($name, 'lvntr/') && in_array($name, $rootNames, true),
            ));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * The newest stable kit release on the installed major line, when Composer
     * left the app on an older one.
     *
     * `composer update lvntr/laravel-starter-kit` without `-W` keeps the kit's
     * dependencies at their locked versions; when a release raises one of their
     * floors, Composer quietly installs the newest kit that fits the old lock.
     * The `sk:update` that follows is then the old one and cannot know a newer
     * release exists — this asks Packagist instead. Null when the kit is up to
     * date, not a tagged install, or Packagist does not answer within 3 seconds.
     */
    public static function heldBackBy(?string $installedTag = null): ?string
    {
        try {
            $installed = ltrim($installedTag ?? KitVersion::tag() ?? '', 'v');

            if (! preg_match('/^(\d+)\.\d+\.\d+$/', $installed, $installedParts)) {
                return null;
            }

            $releases = Http::timeout(3)->get(self::PACKAGIST_METADATA)->throw()->json('packages.lvntr/laravel-starter-kit');
            $latest = $installed;

            foreach (is_array($releases) ? $releases : [] as $release) {
                $version = ltrim((string) (is_array($release) ? ($release['version'] ?? '') : ''), 'v');

                if (preg_match('/^(\d+)\.\d+\.\d+$/', $version, $parts)
                    && $parts[1] === $installedParts[1]
                    && version_compare($version, $latest, '>')) {
                    $latest = $version;
                }
            }

            return $latest === $installed ? null : 'v'.$latest;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Add the `sk-update` script to the app's composer.json, so updating the
     * kit is one command that cannot forget `-W`: `composer sk-update`.
     * `sk:update` runs as a separate process after Composer, so it is the NEW
     * release's updater. An existing script of that name is left alone.
     */
    public static function ensureUpdateScript(?string $rootComposerJsonPath = null): bool
    {
        try {
            $path = $rootComposerJsonPath ?? base_path('composer.json');

            if (! is_readable($path) || ! is_writable($path)) {
                return false;
            }

            // Decoded as objects so an empty `{}` section is written back as `{}`, not `[]`.
            $root = json_decode((string) file_get_contents($path));

            if (! $root instanceof stdClass) {
                return false;
            }

            $root->scripts ??= new stdClass;

            if (! $root->scripts instanceof stdClass || property_exists($root->scripts, self::UPDATE_SCRIPT)) {
                return false;
            }

            $root->scripts->{self::UPDATE_SCRIPT} = [
                'Composer\\Config::disableProcessTimeout',
                '@composer update lvntr/laravel-starter-kit -W',
                '@php artisan sk:update --ansi',
            ];

            return file_put_contents(
                $path,
                json_encode($root, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
            ) !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
