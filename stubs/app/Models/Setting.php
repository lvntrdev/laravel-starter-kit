<?php

namespace App\Models;

use App\Domain\Setting\SettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
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

    /**
     * Scope to a specific settings group.
     */
    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * Get a setting value by "group.key" notation.
     */
    public static function getValue(string $path, mixed $default = null): mixed
    {
        return app(SettingService::class)->getValue($path, $default);
    }

    /**
     * Set a setting value by "group.key" notation.
     */
    public static function setValue(string $path, mixed $value): void
    {
        app(SettingService::class)->setValue($path, $value);
    }

    /**
     * Get all settings for a group as a key-value array.
     *
     * @return array<string, mixed>
     */
    public static function getGroup(string $group): array
    {
        return app(SettingService::class)->getGroup($group);
    }

    /**
     * Bulk-set settings for a group.
     *
     * @param  array<string, mixed>  $values
     */
    public static function setGroup(string $group, array $values): void
    {
        app(SettingService::class)->setGroup($group, $values);
    }

    /**
     * Get all settings grouped by group name (cached).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function allGrouped(): array
    {
        return app(SettingService::class)->allGrouped();
    }
}
