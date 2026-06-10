<?php

namespace Lvntr\StarterKit\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

/**
 * Test-only stub: consumer'ın App\Models\Setting'ini simüle eder.
 *
 * SettingService doğrudan App\Models\Setting FQCN'ine yazar; package test
 * ortamında App\ namespace autoload edilmediğinden bu stub
 * SensitiveKeysFallbackTest içinde class_alias ile App\Models\Setting'e
 * bağlanır. Şema DatabaseTestCase'in inline `settings` tablosuyla,
 * fillable/cast'lar stubs/app/Models/Setting.php ile birebir aynıdır.
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
}
