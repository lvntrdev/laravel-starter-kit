<?php

namespace Lvntr\StarterKit\Domain\Shared\Services;

use App\Models\Definition;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class DefinitionService
{
    private const CACHE_TTL = 3600;

    /**
     * Single cache key holding every locale's grouped definitions. Shared with
     * App\Models\Definition::booted() so the reader and the invalidator can
     * never drift over a per-locale key list. Previously the read path keyed
     * by App::getLocale() while clearCache() looped config('app.languages')
     * (mutated at runtime by SettingsServiceProvider) — the two desynced and
     * left stale, locale-specific cache after a definition changed.
     */
    public const CACHE_KEY = 'definitions';

    /**
     * Get all definition items for a specific key in the current locale.
     *
     * @return list<array<string, mixed>>
     */
    public function get(string $key): array
    {
        return $this->loadAll()[App::getLocale()][$key] ?? [];
    }

    /**
     * Get all definitions grouped by key for the current locale, optionally
     * filtered by keys.
     *
     * @param  list<string>|null  $keys
     * @return array<string, list<array<string, mixed>>>
     */
    public function all(?array $keys = null): array
    {
        $grouped = $this->loadAll()[App::getLocale()] ?? [];

        if ($keys === null) {
            return $grouped;
        }

        return array_intersect_key($grouped, array_flip($keys));
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Load every active definition under one cache entry, grouped
     * locale => key => items. Filtering by the current locale happens in
     * memory so a single flush invalidates all locales at once.
     *
     * @return array<string, array<string, list<array<string, mixed>>>>
     */
    private function loadAll(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Definition::query()
                ->where('is_active', true)
                ->orderBy('order')
                ->get()
                ->groupBy('lang')
                ->map(fn ($localeItems) => $localeItems
                    ->groupBy('key')
                    ->map(fn ($items) => $items->map(fn ($d) => [
                        'value' => $d->value,
                        'label' => $d->label,
                        'order' => $d->order,
                        'severity' => $d->severity,
                        'icon' => $d->icon,
                        'explanation' => $d->explanation,
                        'visibility' => $d->visibility,
                    ])->values()->all())
                    ->all())
                ->all();
        });
    }
}
