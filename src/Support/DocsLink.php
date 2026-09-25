<?php

namespace Lvntr\StarterKit\Support;

/**
 * Build a URL to a documentation page that is reachable from an installed app.
 *
 * `.gitattributes` marks the docs tree as `export-ignore`, so a `prefer-dist`
 * install — the Composer default, and what CI and most deploys use — has no
 * documentation directory under `vendor/lvntr/laravel-starter-kit/`. Console
 * output that points at a local relative path therefore names a file the
 * operator cannot open, and it does so exactly when it matters most: mid
 * migration, or while an encryption key is being rotated.
 *
 * The URL is pinned to the INSTALLED version so the guidance matches the code
 * that printed it. A source install, a dev branch, or a missing Composer
 * runtime falls back to the default branch.
 */
final class DocsLink
{
    private const REPOSITORY = 'https://github.com/lvntrdev/laravel-starter-kit';

    private const FALLBACK_REF = 'main';

    /**
     * Resolve a documentation page name, such as `timezone.md`, to a URL.
     */
    public static function to(string $page): string
    {
        return sprintf('%s/blob/%s/docs/%s', self::REPOSITORY, self::ref(), ltrim($page, '/'));
    }

    /**
     * Resolve the git ref the installed package corresponds to. A branch
     * install (`dev-main`) is not a useful permalink, so it falls back to the
     * default branch.
     */
    private static function ref(): string
    {
        return KitVersion::tag() ?? self::FALLBACK_REF;
    }
}
