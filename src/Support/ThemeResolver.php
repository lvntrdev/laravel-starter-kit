<?php

namespace Lvntr\StarterKit\Support;

/**
 * Server-side counterpart to the node build resolver (sk-theme-build.mjs).
 *
 * The kit now ships TWO theme layers that live side by side:
 *
 *   1. Runtime kit themes ({@see RUNTIME_THEMES} — `main`, `aura`): always in
 *      the CSS bundle. `main` is the unscoped default; `aura`'s rules are
 *      scoped to `html[data-sk-theme='aura']` and switched on instantly by a
 *      DOM attribute (no rebuild). The DB `appearance.theme` value selects
 *      which one is active. These are NOT folders the build resolves into
 *      slots — `aura` lives under resources/css/theme-runtime/ and is imported
 *      directly, so it never appears in the folder enumeration.
 *
 *   2. Build-time custom themes (a consumer's `theme/<custom>/` folders): the
 *      legacy slot-override + marker + rebuild flow, unchanged for backward
 *      compatibility. Selecting a custom theme writes the marker and the next
 *      build resolves it.
 *
 * This class is the PHP half of the contract for BOTH layers:
 *
 *   - it enumerates the selectable theme set (runtime themes ∪ custom folders
 *     under resources/css/theme/),
 *   - it tells runtime themes apart from build-time custom ones,
 *   - it validates a theme name as a conservative slug (no path traversal),
 *   - it writes the `.sk-active-theme` marker the node resolver reads, and
 *   - it resolves the *build-time* active theme name (marker → VITE_SK_THEME →
 *     `main`) for read paths. NOTE: this is distinct from the *runtime* active
 *     theme, which is the raw `appearance.theme` DB value resolved in
 *     SettingsDefaultsQuery::appearance(); `activeTheme()`'s meaning and the
 *     build chain that depends on it are unchanged by the runtime layer.
 *
 * Keeping all of this in one place guarantees the validation rule, the payload
 * query and the marker write agree on the exact same theme set and slug rule —
 * and that the value written to a path-segment file is always traversal-safe.
 */
class ThemeResolver
{
    /** Default theme shipped by the kit; the fallback when nothing else resolves. */
    public const DEFAULT_THEME = 'main';

    /**
     * Runtime kit themes — always present in the CSS bundle and switched on
     * instantly via the `<html data-sk-theme>` attribute (no rebuild). These
     * are NOT build-time slot folders: `main` is the unscoped default and
     * `aura` is imported from resources/css/theme-runtime/ with its rules
     * scoped to `html[data-sk-theme='aura']`. Selecting one of these neutralizes
     * the build-time layer (the marker is reset to `main`).
     *
     * @var list<string>
     */
    public const RUNTIME_THEMES = ['main', 'aura'];

    /** Marker file (relative to base path) the node build resolver reads. */
    public const MARKER_RELATIVE_PATH = 'resources/css/theme/.sk-active-theme';

    /**
     * Whether $theme is one of the always-bundled runtime kit themes (switched
     * by `data-sk-theme`, no rebuild) as opposed to a build-time custom theme.
     */
    public static function isRuntimeTheme(string $theme): bool
    {
        return in_array($theme, self::RUNTIME_THEMES, true);
    }

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
     * The full selectable theme set: the always-available runtime themes
     * ({@see RUNTIME_THEMES}, in order: `main`, `aura`) followed by every
     * build-time custom theme folder directly under resources/css/theme/ whose
     * name is a valid slug. Underscore-prefixed entries (e.g. an `_active` build
     * dir) and loose files (theme.css, _active.css) are excluded — only real
     * theme folders qualify.
     *
     * Runtime themes come from the constant, NOT the filesystem: `aura` now
     * lives under resources/css/theme-runtime/ and is imported directly, so it
     * is no longer a slot folder under theme/. Seeding from RUNTIME_THEMES keeps
     * it (and `main`) in the set even on a pre-build / partial install, and
     * array_unique collapses any leftover legacy `theme/aura` folder onto its
     * runtime position so the order stays main → aura → custom.
     *
     * @return list<string>
     */
    public static function availableThemes(): array
    {
        $themeDir = base_path('resources/css/theme');

        $themes = self::RUNTIME_THEMES;

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
