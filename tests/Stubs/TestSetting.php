<?php

namespace Lvntr\StarterKit\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Domain\Setting\SettingService;

/**
 * Test-only stub: consumer'ın App\Models\Setting'ini simüle eder.
 *
 * SettingService doğrudan App\Models\Setting FQCN'ine yazar; package test
 * ortamında App\ namespace autoload edilmediğinden bu stub
 * SensitiveKeysFallbackTest içinde class_alias ile App\Models\Setting'e
 * bağlanır. Şema DatabaseTestCase'in inline `settings` tablosuyla,
 * fillable/cast'lar ve statik facade metodları (getValue/setValue/
 * getGroup/setGroup/allGrouped) stubs/app/Models/Setting.php ile
 * birebir aynıdır — vendor sınıfları (SettingsDefaultsQuery,
 * UpdateAuthSettingsAction) bu statikler üzerinden çalışır.
 */
class TestSetting extends Model
{
    protected $table = 'settings';

    protected $fillable = [
        'group',
        'key',
        'value',
        'encrypted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encrypted' => 'boolean',
        ];
    }

    public static function getValue(string $path, mixed $default = null): mixed
    {
        return app(SettingService::class)->getValue($path, $default);
    }

    public static function setValue(string $path, mixed $value): void
    {
        app(SettingService::class)->setValue($path, $value);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getGroup(string $group): array
    {
        return app(SettingService::class)->getGroup($group);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function setGroup(string $group, array $values): void
    {
        app(SettingService::class)->setGroup($group, $values);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function allGrouped(): array
    {
        return app(SettingService::class)->allGrouped();
    }
}
