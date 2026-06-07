<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;

/**
 * Tema-resolver çıktısının (_active.css) varlığını VE tutarlılığını kontrol eder.
 *
 * resources/css/theme/theme.css, resolver tarafından üretilen
 * `_active.css`'i @import eder. Bu artefakt gitignore'lu (build sırasında
 * kit tema resolver'ı üretir — sk-theme-build.mjs, vendor-resident); eksikse `npm run build`/`vite build`
 * "Can't resolve './_active.css'" ile hard-fail eder → bu durum Fail.
 *
 * Manifest mevcutsa içeriği de denetlenir (bkz. inspectManifest): tema kökü
 * dışına çıkan (`../`) bir @import veya header'daki aktif tema ile ortamdaki
 * VITE_SK_THEME uyuşmazlığı → Warn (build'i bozmaz, dikkat çeker).
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

        $raw = env('VITE_SK_THEME');

        return $this->inspectManifest($activeManifestPath, is_string($raw) ? $raw : null);
    }

    /**
     * Üretilmiş _active.css içeriğini denetler:
     *
     *  1. Tema kökü dışına çıkan (`../`) bir @import var mı — bu, resolver'ın
     *     tema-adı doğrulamasına (resolver'ın `resolveThemeName`'i, vendor-resident)
     *     EK, bağımsız bir güvenlik ağıdır; elle düzenlenmiş ya da eski/güvensiz
     *     bir resolver'dan kalmış stale artefaktı yakalar.
     *  2. Header'daki "Active theme: <x>" değeri ortamdaki VITE_SK_THEME ile
     *     eşleşiyor mu — eşleşmiyorsa manifest stale'dir (env değişti, rebuild
     *     edilmedi). VITE_SK_THEME okunamıyorsa (unset ya da config cache nedeniyle
     *     env() null döndüyse) bu karşılaştırma atlanır; yanlış pozitif üretmez.
     *
     * Her iki sorun da Warn'dır (build'i bozmaz); tek hard-fail eksik manifest.
     *
     * @param  string|null  $expectedTheme  Ortamdaki aktif tema; null/boş ise eşleşme atlanır.
     */
    protected function inspectManifest(string $activeManifestPath, ?string $expectedTheme): DoctorReport
    {
        $contents = (string) file_get_contents($activeManifestPath);

        // 1) Tema dizininden çıkan import (traversal) — bağımsız güvenlik ağı.
        if (preg_match('#@import\s+[\'"][^\'"]*\.\./#', $contents) === 1) {
            return DoctorReport::warn(
                $this->name(),
                '_active.css contains an @import that escapes the theme directory (../).',
                'Regenerate with npm run build and check VITE_SK_THEME for a stray path value.'
            );
        }

        // 2) Header'daki aktif tema, ortamdaki VITE_SK_THEME ile eşleşiyor mu?
        $expected = $expectedTheme !== null && trim($expectedTheme) !== '' ? trim($expectedTheme) : null;
        if ($expected !== null
            && preg_match('/Active theme:\s*(\S+)/', $contents, $m) === 1
            && $m[1] !== $expected
        ) {
            return DoctorReport::warn(
                $this->name(),
                sprintf('Theme manifest was built for "%s" but VITE_SK_THEME is "%s".', $m[1], $expected),
                'Run npm run build to regenerate _active.css for the active theme.'
            );
        }

        return DoctorReport::ok(
            $this->name(),
            'Theme manifest present and consistent (resources/css/theme/_active.css).'
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
