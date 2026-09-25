<?php

namespace Lvntr\StarterKit\Support;

use Composer\InstalledVersions;

/**
 * The installed kit version, read from Composer's runtime metadata so console
 * output never carries a hand-written version that goes stale on release.
 */
final class KitVersion
{
    private const PACKAGE = 'lvntr/laravel-starter-kit';

    /**
     * The release tag of the installed package, such as `v13.9.0`.
     *
     * A tagged release reports `13.9.0`, published as `v13.9.0`. A branch
     * install (`dev-main`), a source checkout or a missing Composer runtime has
     * no release tag and returns null.
     */
    public static function tag(): ?string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled(self::PACKAGE)) {
            return null;
        }

        $version = InstalledVersions::getPrettyVersion(self::PACKAGE);

        if (! is_string($version) || $version === '' || str_starts_with($version, 'dev-')) {
            return null;
        }

        return str_starts_with($version, 'v') ? $version : 'v'.$version;
    }
}
