<?php

use App\Models\Setting;
use Lvntr\StarterKit\Domain\Setting\Actions\UpdateSettingsAction;
use Lvntr\StarterKit\Domain\Setting\DTOs\AppearanceSettingsDTO;
use Lvntr\StarterKit\Domain\Setting\Queries\SettingsDefaultsQuery;
use Lvntr\StarterKit\Domain\Setting\SettingService;
use Lvntr\StarterKit\Support\ThemeResolver;
use Lvntr\StarterKit\Tests\Stubs\TestSetting;

/*
|--------------------------------------------------------------------------
| Appearance Settings — backend domain (Görünüm sekmesi · Task 1)
|--------------------------------------------------------------------------
|
| Kapsam:
|   1. AppearanceSettingsDTO — payload → '1'/'0' string-persist sözleşmesi.
|   2. SettingsDefaultsQuery::appearance() — DB boşken nötr fallback'ler,
|      kayıtlı değerlerin cast'lanması, logo_light → general.logo geri
|      uyumluluğu, all() içinde 'appearance' grubunun bulunması.
|   3. UpdateSettingsAction('appearance', dto) persist + ThemeResolver tema
|      marker yazımı (slug doğrulamalı, traversal-safe).
|   4. ThemeResolver — slug doğrulama, kullanılabilir tema taraması, marker
|      okuma/yazma, aktif tema çözümü (marker → VITE_SK_THEME → main).
|
| SettingService App\Models\Setting FQCN'ine yazar; package test ortamında
| App\ autoload edilmediğinden TestSetting stub'ı alias'lanır (diğer
| Settings testleriyle aynı guard'lı desen).
|
*/

if (! class_exists(Setting::class)) {
    class_alias(TestSetting::class, Setting::class);
}

/**
 * Tema dizinini (base_path/resources/css/theme) test süresince oluştur, alt
 * tema klasörlerini ekle ve marker'ı temizle. Her testten sonra silinir.
 *
 * @param  list<string>  $themes  main dışında oluşturulacak tema klasörleri.
 */
function setupThemeDir(array $themes = []): string
{
    $themeDir = base_path('resources/css/theme');

    @mkdir($themeDir.'/main', 0777, true);
    foreach ($themes as $theme) {
        @mkdir($themeDir.'/'.$theme, 0777, true);
    }

    return $themeDir;
}

function cleanupThemeDir(): void
{
    $themeDir = base_path('resources/css/theme');
    if (! is_dir($themeDir)) {
        return;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($themeDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $entry) {
        $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
    }
    @rmdir($themeDir);
}

afterEach(fn () => cleanupThemeDir());

// ──────────────────────────────────────────────────────────────────────────────
// 1. AppearanceSettingsDTO
// ──────────────────────────────────────────────────────────────────────────────

it('DTO maps a payload to string-persisted values', function (): void {
    $dto = AppearanceSettingsDTO::fromArray([
        'theme' => 'main',
        'accent_color' => 'indigo',
        'dark_mode_default' => true,
        'sidebar_style' => 'light',
    ]);

    expect($dto->toArray())->toBe([
        'theme' => 'main',
        'accent_color' => 'indigo',
        'dark_mode_default' => '1',
        'sidebar_style' => 'light',
    ]);
});

it('DTO coerces a falsey dark_mode_default to the "0" string', function (): void {
    $dto = AppearanceSettingsDTO::fromArray([
        'theme' => 'main',
        'accent_color' => 'default',
        'dark_mode_default' => false,
        'sidebar_style' => 'colored',
    ]);

    expect($dto->toArray()['dark_mode_default'])->toBe('0');
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. SettingsDefaultsQuery::appearance()
// ──────────────────────────────────────────────────────────────────────────────

it('appearance() returns neutral defaults when the DB is empty', function (): void {
    setupThemeDir();

    $appearance = app(SettingsDefaultsQuery::class)->appearance();

    expect($appearance['theme'])->toBe('main')
        ->and($appearance['accent_color'])->toBe('default')
        ->and($appearance['dark_mode_default'])->toBeFalse()
        ->and($appearance['sidebar_style'])->toBe('colored')
        ->and($appearance['available_themes'])->toContain('main')
        ->and($appearance['logo_light_url'])->toBeNull()
        ->and($appearance['logo_dark_url'])->toBeNull()
        ->and($appearance['favicon_url'])->toBeNull();
});

it('appearance() reflects stored values with bool cast', function (): void {
    setupThemeDir();

    app(SettingService::class)->setGroup('appearance', [
        'accent_color' => 'emerald',
        'dark_mode_default' => '1',
        'sidebar_style' => 'light',
    ]);

    $appearance = app(SettingsDefaultsQuery::class)->appearance();

    expect($appearance['accent_color'])->toBe('emerald')
        ->and($appearance['dark_mode_default'])->toBeTrue()
        ->and($appearance['sidebar_style'])->toBe('light');
});

it('appearance() falls back logo_light to the legacy general.logo', function (): void {
    setupThemeDir();

    app(SettingService::class)->setValue('general.logo', 'logo/legacy.png');

    $appearance = app(SettingsDefaultsQuery::class)->appearance();

    expect($appearance['logo_light_url'])->toContain('logo/legacy.png');
});

it('appearance() prefers its own logo_light over the legacy general.logo', function (): void {
    setupThemeDir();

    app(SettingService::class)->setValue('general.logo', 'logo/legacy.png');
    app(SettingService::class)->setValue('appearance.logo_light', 'appearance/new.png');

    $appearance = app(SettingsDefaultsQuery::class)->appearance();

    expect($appearance['logo_light_url'])->toContain('appearance/new.png')
        ->and($appearance['logo_light_url'])->not->toContain('legacy');
});

it('all() includes the appearance group', function (): void {
    setupThemeDir();

    expect(app(SettingsDefaultsQuery::class)->all())->toHaveKey('appearance');
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. UpdateSettingsAction('appearance') + theme marker write
// ──────────────────────────────────────────────────────────────────────────────

it('action persists the appearance group from a DTO', function (): void {
    app(UpdateSettingsAction::class)->execute('appearance', AppearanceSettingsDTO::fromArray([
        'theme' => 'main',
        'accent_color' => 'rose',
        'dark_mode_default' => true,
        'sidebar_style' => 'light',
    ]));

    $service = app(SettingService::class);

    expect($service->getValue('appearance.theme'))->toBe('main')
        ->and($service->getValue('appearance.accent_color'))->toBe('rose')
        ->and($service->getValue('appearance.dark_mode_default'))->toBe('1')
        ->and($service->getValue('appearance.sidebar_style'))->toBe('light');
});

it('writeMarker persists the theme name and activeTheme reads it back', function (): void {
    setupThemeDir(['custom']);

    expect(ThemeResolver::writeMarker('custom'))->toBeTrue();
    expect(ThemeResolver::readMarker())->toBe('custom');
    expect(ThemeResolver::activeTheme())->toBe('custom');
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. ThemeResolver — slug guard / traversal-safety
// ──────────────────────────────────────────────────────────────────────────────

it('isValidName accepts slugs and rejects traversal / separators', function (): void {
    expect(ThemeResolver::isValidName('main'))->toBeTrue()
        ->and(ThemeResolver::isValidName('my-theme_2'))->toBeTrue()
        ->and(ThemeResolver::isValidName('../etc'))->toBeFalse()
        ->and(ThemeResolver::isValidName('a/b'))->toBeFalse()
        ->and(ThemeResolver::isValidName('a.b'))->toBeFalse()
        ->and(ThemeResolver::isValidName(''))->toBeFalse();
});

it('writeMarker refuses a traversal value and writes nothing', function (): void {
    setupThemeDir();

    expect(ThemeResolver::writeMarker('../escape'))->toBeFalse();
    expect(ThemeResolver::readMarker())->toBeNull();
});

it('availableThemes lists real theme folders and excludes underscore/dot entries', function (): void {
    $themeDir = setupThemeDir(['custom']);
    @mkdir($themeDir.'/_active');
    @file_put_contents($themeDir.'/theme.css', '');

    $themes = ThemeResolver::availableThemes();

    expect($themes)->toContain('main')
        ->and($themes)->toContain('custom')
        ->and($themes)->not->toContain('_active')
        ->and($themes)->not->toContain('theme.css');
});

it('activeTheme falls back to main when the marker names an uninstalled theme', function (): void {
    setupThemeDir();

    // Marker dosyasını elle yaz (writeMarker installed-set kontrolü yapmaz,
    // ama activeTheme yapar): kurulu olmayan tema main'e düşer.
    @file_put_contents(base_path(ThemeResolver::MARKER_RELATIVE_PATH), 'ghost'.PHP_EOL);

    expect(ThemeResolver::activeTheme())->toBe('main');
});
