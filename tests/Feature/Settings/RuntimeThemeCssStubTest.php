<?php

/*
|--------------------------------------------------------------------------
| Runtime Theme CSS — Stub dosyası regression guard'ları (Task 5)
|--------------------------------------------------------------------------
|
| Bu testler iki-katman tema modelinin CSS tarafının doğruluğunu yapısal
| olarak kilitler:
|
|   1. theme/theme.css → theme-runtime/aura/aura.css import'unu içerir
|      (runtime katman her zaman bundle'a dahil olur).
|   2. theme-runtime/aura/ altındaki her .css dosyası html[data-sk-theme='aura']
|      scope'unu içerir (kural spec sözleşmesi; aura main'i her zaman ezer).
|   3. theme/aura/ klasörü YOKTUR — aura artık build-time slot değil; orada
|      kalırsa availableThemes() onu custom sanır ve build resolver kırılır.
|
| Sabit: "data-sk-theme" eşleşmesi her dosyanın her CSS dosyasında tek bir
| string arama ile doğrulanır (aura.css index dahil, çünkü dosya başı yorumu
| sözleşmeyi belgeler).
|
*/

// Proje kökü (paket kökü / repo kökü) — hem CI hem local için güvenli.
$stubsDir = dirname(__DIR__, 3).'/stubs/resources/css';

// ──────────────────────────────────────────────────────────────────────────────
// 1. theme.css → runtime aura import
// ──────────────────────────────────────────────────────────────────────────────

it('theme.css mevcut', function () use ($stubsDir): void {
    $path = $stubsDir.'/theme/theme.css';

    expect(is_file($path))->toBeTrue("Stub bulunamadı: {$path}");
});

it('theme.css theme-runtime/aura/aura.css import ediyor', function () use ($stubsDir): void {
    $contents = file_get_contents($stubsDir.'/theme/theme.css');

    expect($contents)->toContain("@import '../theme-runtime/aura/aura.css'");
});

it('theme.css önce _active.css sonra aura.css import ediyor (kaynak sırası doğru)', function () use ($stubsDir): void {
    $contents = file_get_contents($stubsDir.'/theme/theme.css');

    $activePos = strpos($contents, '_active.css');
    $auraPos = strpos($contents, 'aura/aura.css');

    expect($activePos)->not->toBeFalse('_active.css import bulunamadı')
        ->and($auraPos)->not->toBeFalse('aura.css import bulunamadı');

    // _active.css'in aura.css'ten ÖNCE geldiğini assert et.
    expect($activePos)->toBeLessThan($auraPos);
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. theme-runtime/aura/ altındaki her .css data-sk-theme içeriyor
// ──────────────────────────────────────────────────────────────────────────────

it('theme-runtime/aura/aura.css mevcut', function () use ($stubsDir): void {
    $path = $stubsDir.'/theme-runtime/aura/aura.css';

    expect(is_file($path))->toBeTrue("Stub bulunamadı: {$path}");
});

it('theme-runtime/aura/tokens.css data-sk-theme scope içeriyor', function () use ($stubsDir): void {
    $path = $stubsDir.'/theme-runtime/aura/tokens.css';

    expect(is_file($path))->toBeTrue("Stub bulunamadı: {$path}");

    $contents = file_get_contents($path);
    expect($contents)->toContain('data-sk-theme');
});

it('theme-runtime/aura/layout/shell.css data-sk-theme scope içeriyor', function () use ($stubsDir): void {
    $path = $stubsDir.'/theme-runtime/aura/layout/shell.css';

    expect(is_file($path))->toBeTrue("Stub bulunamadı: {$path}");

    $contents = file_get_contents($path);
    expect($contents)->toContain('data-sk-theme');
});

it('theme-runtime/aura/layout/sidebar.css data-sk-theme scope içeriyor', function () use ($stubsDir): void {
    $path = $stubsDir.'/theme-runtime/aura/layout/sidebar.css';

    expect(is_file($path))->toBeTrue("Stub bulunamadı: {$path}");

    $contents = file_get_contents($path);
    expect($contents)->toContain('data-sk-theme');
});

it('theme-runtime/aura/layout/header.css data-sk-theme scope içeriyor', function () use ($stubsDir): void {
    $path = $stubsDir.'/theme-runtime/aura/layout/header.css';

    expect(is_file($path))->toBeTrue("Stub bulunamadı: {$path}");

    $contents = file_get_contents($path);
    expect($contents)->toContain('data-sk-theme');
});

it('theme-runtime/aura/ altındaki tüm .css dosyaları data-sk-theme scope içeriyor', function () use ($stubsDir): void {
    $auraDir = $stubsDir.'/theme-runtime/aura';

    expect(is_dir($auraDir))->toBeTrue("theme-runtime/aura dizini bulunamadı: {$auraDir}");

    $cssFiles = array_merge(
        glob($auraDir.'/*.css') ?: [],
        glob($auraDir.'/layout/*.css') ?: [],
    );

    expect($cssFiles)->not->toBeEmpty('theme-runtime/aura/ altında .css dosyası bulunamadı');

    foreach ($cssFiles as $file) {
        $contents = file_get_contents($file);
        $rel = str_replace($stubsDir.'/', '', $file);
        $hasScopeAttr = str_contains($contents, 'data-sk-theme');
        expect($hasScopeAttr)->toBeTrue("Scope eksik: {$rel}");
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. theme/aura/ klasörü YOKTUR (build-time slot çakışması imkânsız)
// ──────────────────────────────────────────────────────────────────────────────

it('stubs/resources/css/theme/aura/ klasörü mevcut DEĞİL', function () use ($stubsDir): void {
    $legacyAuraDir = $stubsDir.'/theme/aura';

    expect(is_dir($legacyAuraDir))->toBeFalse(
        'theme/aura/ hâlâ mevcut — availableThemes() onu custom tema sanır ve build resolver bozulur. '.
        "Klasörü theme-runtime/aura/'ya taşı ve theme/aura/'yı sil."
    );
});
