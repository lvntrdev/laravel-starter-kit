<?php

namespace Lvntr\StarterKit\Support;

/**
 * Server-side counterpart to the node build resolver (sk-theme-build.mjs).
 *
 * The kit's theme is selected at build time and lives as a single CSS bundle;
 * the DB is the source of truth for WHICH theme to build. This class is the
 * PHP half of that contract:
 *
 *   - it enumerates the available theme folders under resources/css/theme/,
 *   - it validates a theme name as a conservative slug (no path traversal),
 *   - it writes the `.sk-active-theme` marker the node resolver reads, and
 *   - it resolves the currently active theme name (marker → VITE_SK_THEME →
 *     `main`) for read paths.
 *
 * Keeping all four in one place guarantees the validation rule, the payload
 * query and the marker write agree on the exact same theme set and slug rule —
 * and that the value written to a path-segment file is always traversal-safe.
 */
class ThemeResolver
{
    /** Default theme shipped by the kit; the fallback when nothing else resolves. */
    public const DEFAULT_THEME = 'main';

    /** Marker file (relative to base path) the node build resolver reads. */
    public const MARKER_RELATIVE_PATH = 'resources/css/theme/.sk-active-theme';

    /**
     * A theme name is used verbatim as a path segment under resources/css/theme/
     * (both for the marker file and the node resolver), so it MUST be a
     * conservative slug — letters, digits, `-`, `_`. This structurally cannot
     * contain `/`, `\`, `.`, a null byte or whitespace, so `../`-style traversal
     * is impossible by construction. Identical to the node resolver's rule.
     */
    public static function isValidName(string $name): bool
    {
        return $name !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $name) === 1;
    }

    /**
     * Available theme folder names: every directory directly under
     * resources/css/theme/ whose name is a valid slug. Underscore-prefixed
     * entries (e.g. an `_active` build dir) and loose files (theme.css,
     * _active.css) are excluded — only real theme folders qualify.
     *
     * Always includes the default `main` so the set is never empty even on a
     * pre-build / partial install.
     *
     * @return list<string>
     */
    public static function availableThemes(): array
    {
        $themeDir = base_path('resources/css/theme');

        $themes = [self::DEFAULT_THEME];

        if (is_dir($themeDir)) {
            foreach ((array) scandir($themeDir) as $entry) {
                if (! is_string($entry) || $entry === '' || $entry[0] === '_' || $entry[0] === '.') {
                    continue;
                }

                if (! self::isValidName($entry)) {
                    continue;
                }

                if (is_dir($themeDir.DIRECTORY_SEPARATOR.$entry)) {
                    $themes[] = $entry;
                }
            }
        }

        return array_values(array_unique($themes));
    }

    /**
     * Resolve the active theme name for read paths.
     *
     * Precedence mirrors the node resolver MINUS the explicit arg (which only
     * the build passes): marker file → VITE_SK_THEME → `main`. An invalid or
     * unavailable value is ignored (falls through to the next source) rather
     * than throwing — the read path must never hard-fail a page render.
     */
    public static function activeTheme(): string
    {
        $available = self::availableThemes();

        $marker = self::readMarker();
        if ($marker !== null && self::isValidName($marker) && in_array($marker, $available, true)) {
            return $marker;
        }

        $env = env('VITE_SK_THEME');
        if (is_string($env)) {
            $env = trim($env);
            if (self::isValidName($env) && in_array($env, $available, true)) {
                return $env;
            }
        }

        return self::DEFAULT_THEME;
    }

    /**
     * Write the `.sk-active-theme` marker so the next build resolves this theme.
     *
     * The name is slug-validated before it touches the filesystem; an invalid
     * name is refused (returns false, writes nothing) rather than risking a
     * traversal or a garbage marker. Overwrites any existing marker.
     */
    public static function writeMarker(string $theme): bool
    {
        if (! self::isValidName($theme)) {
            return false;
        }

        $path = base_path(self::MARKER_RELATIVE_PATH);
        $dir = dirname($path);

        if (! is_dir($dir)) {
            return false;
        }

        return file_put_contents($path, $theme.PHP_EOL) !== false;
    }

    /**
     * Read the raw marker value (trimmed) or null when absent/empty/unreadable.
     */
    public static function readMarker(): ?string
    {
        $path = base_path(self::MARKER_RELATIVE_PATH);

        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $value = trim($raw);

        return $value === '' ? null : $value;
    }
}
