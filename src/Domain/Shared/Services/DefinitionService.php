<?php

namespace Lvntr\StarterKit\Domain\Shared\Services;

use App\Models\Definition;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class DefinitionService
{
    private const CACHE_TTL = 3600;

    /**
     * Get all definition items for a specific key in the current locale.
     *
     * @return list<array<string, mixed>>
     */
    public function get(string $key): array
    {
        return $this->loadGrouped(App::getLocale())[$key] ?? [];
    }

    /**
     * Get all definitions grouped by key, optionally filtered by keys.
     *
     * @param  list<string>|null  $keys
     * @return array<string, list<array<string, mixed>>>
     */
    public function all(?array $keys = null): array
    {
        $grouped = $this->loadGrouped(App::getLocale());

        if ($keys === null) {
            return $grouped;
        }

        return array_intersect_key($grouped, array_flip($keys));
    }

    public function clearCache(): void
    {
        foreach (array_keys(config('app.languages', [App::getLocale() => true])) as $locale) {
            Cache::forget("definitions:{$locale}");
        }
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function loadGrouped(string $locale): array
    {
        return Cache::remember("definitions:{$locale}", self::CACHE_TTL, function () use ($locale) {
            return Definition::query()
                ->where('is_active', true)
                ->where('lang', $locale)
                ->orderBy('order')
                ->get()
                ->groupBy('key')
                ->map(fn ($items) => $items->map(fn ($d) => [
                    'value'       => $d->value,
                    'label'       => $d->label,
                    'order'       => $d->order,
                    'severity'    => $d->severity,
                    'icon'        => $d->icon,
                    'explanation' => $d->explanation,
                    'visibility'  => $d->visibility,
                ])->values()->all())
                ->all();
        });
    }
}
