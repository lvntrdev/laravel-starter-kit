<?php

namespace App\Domain\Setting;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Service: Centralized read/write operations for application settings.
 *
 * Encapsulates encryption, caching, and bulk operations that were
 * previously static methods on the Setting model.
 */
class SettingService
{
    /**
     * Keys that must be stored encrypted.
     *
     * @var list<string>
     */
    private array $sensitiveKeys;

    public function __construct()
    {
        $this->sensitiveKeys = config('settings.sensitive_keys', [
            'mail.password',
            'storage.spaces_secret',
        ]);
    }

    /**
     * Get a setting value by "group.key" notation.
     */
    public function getValue(string $path, mixed $default = null): mixed
    {
        [$group, $key] = $this->parsePath($path);

        $setting = Setting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->first();

        if (! $setting) {
            return $default;
        }

        return $this->decryptIfNeeded($setting);
    }

    /**
     * Set a setting value by "group.key" notation.
     */
    public function setValue(string $path, mixed $value): void
    {
        [$group, $key] = $this->parsePath($path);

        $isSensitive = in_array($path, $this->sensitiveKeys, true);

        Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => $isSensitive && $value !== null ? Crypt::encryptString((string) $value) : $value,
                'encrypted' => $isSensitive,
            ],
        );

        Cache::forget('settings');
    }

    /**
     * Get all settings for a group as a key-value array.
     *
     * @return array<string, mixed>
     */
    public function getGroup(string $group): array
    {
        $settings = Setting::query()->group($group)->get();

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $this->decryptIfNeeded($setting);
        }

        return $result;
    }

    /**
     * Bulk-set settings for a group with a single cache clear.
     *
     * @param  array<string, mixed>  $values
     */
    public function setGroup(string $group, array $values): void
    {
        foreach ($values as $key => $value) {
            $path = "{$group}.{$key}";
            [$g, $k] = $this->parsePath($path);

            $isSensitive = in_array($path, $this->sensitiveKeys, true);

            Setting::query()->updateOrCreate(
                ['group' => $g, 'key' => $k],
                [
                    'value' => $isSensitive && $value !== null ? Crypt::encryptString((string) $value) : $value,
                    'encrypted' => $isSensitive,
                ],
            );
        }

        Cache::forget('settings');
    }

    /**
     * Get all settings grouped by group name (cached).
     *
     * @return array<string, array<string, mixed>>
     */
    public function allGrouped(): array
    {
        return Cache::remember('settings', 3600, function () {
            $all = Setting::all();
            $grouped = [];

            foreach ($all as $setting) {
                $grouped[$setting->group][$setting->key] = $this->decryptIfNeeded($setting);
            }

            return $grouped;
        });
    }

    /**
     * Decrypt a setting value if it is marked as encrypted.
     */
    private function decryptIfNeeded(Setting $setting): mixed
    {
        $value = $setting->value;

        if ($setting->encrypted && $value !== null) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception) {
                $value = null;
            }
        }

        return $value;
    }

    /**
     * Parse "group.key" path into [group, key].
     *
     * @return array{0: string, 1: string}
     */
    private function parsePath(string $path): array
    {
        $parts = explode('.', $path, 2);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Setting path must be in 'group.key' format, got: {$path}");
        }

        return [$parts[0], $parts[1]];
    }
}
