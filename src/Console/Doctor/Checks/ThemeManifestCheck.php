<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;

/**
 * Tema-resolver çıktısının (_active.css) varlığını kontrol eder.
 *
 * resources/css/theme/theme.css, resolver tarafından üretilen
 * `_active.css`'i @import eder. Bu artefakt gitignore'lu (build sırasında
 * scripts/sk-theme-build.mjs üretir); eksikse `npm run build`/`vite build`
 * "Can't resolve './_active.css'" ile hard-fail eder.
 *
 * Tema-resolver sistemini henüz almamış (theme.css `_active.css` import
 * etmeyen) eski consumer'larda check uygulanmaz → Ok döner.
 */
class ThemeManifestCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'Theme Manifest';
    }

    public function run(): DoctorReport
    {
        $themeEntryPath = base_path('resources/css/theme/theme.css');

        // theme.css yok ya da _active.css import etmiyor → tema-resolver
        // sistemi kullanımda değil (eski/migrate olmamış consumer) → uygulanmaz.
        if (! file_exists($themeEntryPath) || ! $this->importsActiveManifest($themeEntryPath)) {
            return DoctorReport::ok(
                $this->name(),
                'Theme resolver not in use (no _active.css import) — check skipped.'
            );
        }

        $activeManifestPath = base_path('resources/css/theme/_active.css');

        if (! file_exists($activeManifestPath)) {
            return DoctorReport::fail(
                $this->name(),
                'resources/css/theme/_active.css is missing (theme resolver output).',
                'Run npm run theme:build or npm run build.'
            );
        }

        return DoctorReport::ok(
            $this->name(),
            'Theme manifest present (resources/css/theme/_active.css).'
        );
    }

    /**
     * theme.css içinde `_active.css` import satırı var mı?
     */
    private function importsActiveManifest(string $themeEntryPath): bool
    {
        $contents = file_get_contents($themeEntryPath);

        if ($contents === false) {
            return false;
        }

        return str_contains($contents, '_active.css');
    }
}
