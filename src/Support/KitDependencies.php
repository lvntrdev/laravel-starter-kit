<?php

namespace Lvntr\StarterKit\Support;

use Composer\InstalledVersions;
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
}
