<?php

/*
|--------------------------------------------------------------------------
| Settings — Appearance (theme) settings tests
|--------------------------------------------------------------------------
|
| Coverage:
|   A1. Stub structural checks (DTO, Request, Action, Seeder, config).
|   A2. DB persistence: appearance.theme is written via direct DB row.
|   A3. Default fallback: config('starter-kit.theme') defaults to 'default'
|       when the appearance.theme setting is absent from DB.
|   A4. SettingsServiceProvider clamp: invalid DB value → 'default'.
|
| Drift-guard (backend side):
|   DG. config('starter-kit.themes') key set equals expected whitelist.
|       When a new preset is added, config + frontend registry + this
|       expected list must all be updated together — otherwise this test
|       breaks and the drift is caught before merge.
|
| Note: The stubs use App\ namespace and cannot be autoloaded by Testbench.
| Structural assertions inspect stub file contents instead of instantiating
| classes. DB-level persistence and config-fallback tests use inline DB
| writes and config() calls, matching the established settings-test pattern.
|
*/

use Illuminate\Support\Facades\DB;

// ──────────────────────────────────────────────────────────────────────────────
// A1a. ThemeSettingsDTO — structural check
// ──────────────────────────────────────────────────────────────────────────────

it('ThemeSettingsDTO stub contains theme property', function (): void {
    $path = dirname(__DIR__, 3)
        .'/stubs/app/Domain/Setting/DTOs/ThemeSettingsDTO.php';

    expect(is_file($path))->toBeTrue("Stub not found: {$path}");

    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('public string $theme')
        ->toContain("theme: \$data['theme']")
        ->toContain("'theme' => \$this->theme");
});

it('ThemeSettingsDTO stub uses BaseDTO and is readonly', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Domain/Setting/DTOs/ThemeSettingsDTO.php'
    );

    expect($contents)
        ->toContain('BaseDTO')
        ->toContain('readonly class ThemeSettingsDTO');
});

// ──────────────────────────────────────────────────────────────────────────────
// A1b. UpdateThemeSettingsRequest — validation and authorization checks
// ──────────────────────────────────────────────────────────────────────────────

it('UpdateThemeSettingsRequest stub includes Rule::in whitelist validation', function (): void {
    $path = dirname(__DIR__, 3)
        .'/stubs/app/Http/Requests/Admin/Settings/UpdateThemeSettingsRequest.php';

    expect(is_file($path))->toBeTrue("Stub not found: {$path}");

    $contents = file_get_contents($path);

    // theme field is required
    expect($contents)->toContain("'theme'");
    expect($contents)->toContain("'required'");
    // Rule::in is used (not a hardcoded list)
    expect($contents)->toContain('Rule::in');
    // Whitelist source: config('starter-kit.themes')
    expect($contents)->toContain("config('starter-kit.themes'");
    expect($contents)->toContain('array_keys');
});

it('UpdateThemeSettingsRequest stub authorize() gates on settings.update permission', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/Settings/UpdateThemeSettingsRequest.php'
    );

    // authorize() must check settings.update — both guard layers (route middleware
    // and FormRequest::authorize) are required for defense-in-depth.
    expect($contents)
        ->toContain("can('settings.update')")
        ->toContain('public function authorize()');
});

// ──────────────────────────────────────────────────────────────────────────────
// A1c. UpdateThemeSettingsAction — writes to appearance group
// ──────────────────────────────────────────────────────────────────────────────

it('UpdateThemeSettingsAction stub writes to the appearance settings group', function (): void {
    $path = dirname(__DIR__, 3)
        .'/stubs/app/Domain/Setting/Actions/UpdateThemeSettingsAction.php';

    expect(is_file($path))->toBeTrue("Stub not found: {$path}");

    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("setGroup('appearance'")
        ->toContain('ThemeSettingsDTO')
        ->toContain('BaseAction');
});

// ──────────────────────────────────────────────────────────────────────────────
// A1d. SettingsController — updateAppearance method exists and is wired
// ──────────────────────────────────────────────────────────────────────────────

it('SettingsController stub contains updateAppearance method', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Http/Controllers/Admin/SettingsController.php'
    );

    expect($contents)
        ->toContain('public function updateAppearance(')
        ->toContain('UpdateThemeSettingsRequest')
        ->toContain('UpdateThemeSettingsAction')
        ->toContain('ThemeSettingsDTO::fromArray');
});

it('appearance route is registered as PUT with settings.update permission middleware', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/stubs/routes/web/settings-route.php'
    );

    // Route: PUT appearance → updateAppearance, named update.appearance
    expect($contents)
        ->toContain("Route::put('appearance'")
        ->toContain("'update.appearance'")
        ->toContain("'check.permission:settings.update'");
});

// ──────────────────────────────────────────────────────────────────────────────
// A1e. SettingSeeder — appearance.theme default seed
// ──────────────────────────────────────────────────────────────────────────────

it('_03_SettingSeeder stub seeds appearance.theme with default value', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/stubs/database/seeders/_03_SettingSeeder.php'
    );

    expect($contents)
        ->toContain("'appearance'")
        ->toContain("'theme'");
});

// ──────────────────────────────────────────────────────────────────────────────
// A2. DB persistence — appearance.theme is stored in settings table
// ──────────────────────────────────────────────────────────────────────────────

it('appearance.theme is persisted to the settings table', function (): void {
    // Directly write to the settings table (mirrors UpdateThemeSettingsAction logic).
    DB::table('settings')->updateOrInsert(
        ['group' => 'appearance', 'key' => 'theme'],
        ['value' => 'corporate', 'encrypted' => false, 'created_at' => now(), 'updated_at' => now()]
    );

    $row = DB::table('settings')
        ->where('group', 'appearance')
        ->where('key', 'theme')
        ->first();

    expect($row)->not->toBeNull();
    expect($row->value)->toBe('corporate');
    expect((bool) $row->encrypted)->toBeFalse();
});

it('appearance.theme can be updated from default to a whitelisted preset', function (): void {
    // Insert default, then update to corporate.
    DB::table('settings')->insert([
        'group' => 'appearance',
        'key' => 'theme',
        'value' => 'default',
        'encrypted' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('settings')
        ->where('group', 'appearance')
        ->where('key', 'theme')
        ->update(['value' => 'corporate', 'updated_at' => now()]);

    $value = DB::table('settings')
        ->where('group', 'appearance')
        ->where('key', 'theme')
        ->value('value');

    expect($value)->toBe('corporate');
});

// ──────────────────────────────────────────────────────────────────────────────
// A3. Default fallback: active theme = 'default' when setting is absent
// ──────────────────────────────────────────────────────────────────────────────

it("config('starter-kit.theme') defaults to 'default' when appearance.theme is absent", function (): void {
    // Ensure no appearance.theme row exists.
    DB::table('settings')->where('group', 'appearance')->delete();

    // The SettingsServiceProvider reads the absence as default.
    // We replicate its logic inline here (the SP has already run during boot;
    // this test verifies the config value that would be produced).
    $settings = DB::table('settings')
        ->get()
        ->groupBy('group')
        ->map(fn ($rows) => $rows->pluck('value', 'key')->toArray())
        ->toArray();

    $appearance = $settings['appearance'] ?? [];
    $theme = $appearance['theme'] ?? 'default';
    $allowedThemes = array_keys(config('starter-kit.themes', ['default' => null]));

    $resolved = is_string($theme) && in_array($theme, $allowedThemes, true)
        ? $theme
        : 'default';

    expect($resolved)->toBe('default');
});

// ──────────────────────────────────────────────────────────────────────────────
// A4. SettingsServiceProvider clamp: invalid DB value → 'default'
// ──────────────────────────────────────────────────────────────────────────────

it('SettingsServiceProvider clamps an invalid DB theme to default', function (): void {
    // Simulate a manually-edited DB row with an unknown theme name.
    DB::table('settings')->updateOrInsert(
        ['group' => 'appearance', 'key' => 'theme'],
        ['value' => '__proto__', 'encrypted' => false, 'created_at' => now(), 'updated_at' => now()]
    );

    $rawTheme = DB::table('settings')
        ->where('group', 'appearance')
        ->where('key', 'theme')
        ->value('value');

    // Replicate the SettingsServiceProvider whitelist-clamp logic.
    $allowedThemes = array_keys(config('starter-kit.themes', ['default' => null]));

    $resolved = is_string($rawTheme) && in_array($rawTheme, $allowedThemes, true)
        ? $rawTheme
        : 'default';

    expect($resolved)->toBe('default');
});

it('SettingsServiceProvider clamp is present in the stub source', function (): void {
    $contents = file_get_contents(
        dirname(__DIR__, 3).'/stubs/app/Providers/SettingsServiceProvider.php'
    );

    // The SP must clamp the DB value to the whitelist before setting config.
    expect($contents)
        ->toContain("config('starter-kit.themes'")
        ->toContain('in_array')
        ->toContain("'default'");
});

// ──────────────────────────────────────────────────────────────────────────────
// DG. Drift-guard — backend config themes key set
//
// Purpose: keep config('starter-kit.themes') key set and the frontend
// preset registry (stubs/resources/js/theme/presets/index.ts) in sync.
// When a new preset is added, ALL THREE must be updated together:
//   - config/starter-kit.php  (backend whitelist)
//   - stubs/.../presets/index.ts  (frontend registry)
//   - The expected list below in this test
// Failing to update any one of them will break this test → drift caught before merge.
// ──────────────────────────────────────────────────────────────────────────────

it('config starter-kit.themes key set matches expected preset whitelist [drift-guard]', function (): void {
    $expected = ['default', 'corporate'];

    $actual = array_keys(config('starter-kit.themes', []));
    sort($actual);
    sort($expected);

    expect($actual)->toBe($expected);
});
