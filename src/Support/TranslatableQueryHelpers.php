<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Translatable JSON kolonlar için Datatable ve API Resource yardımcıları.
 *
 * Datatable kullanımı (DatatableQueryBuilder ile):
 *
 *   return DatatableQueryBuilder::for(Product::class)
 *       ->filterable([
 *           TranslatableQueryHelpers::searchFilter('name'),
 *       ])
 *       ->sortable([
 *           TranslatableQueryHelpers::localeSort('name'),
 *           'created_at',
 *       ])
 *       ->response();
 *
 * API Resource kullanımı (liste / read modu, frontend name.current kullanır):
 *
 *   public function toArray(Request $request): array
 *   {
 *       return [
 *           'id'   => $this->id,
 *           'name' => TranslatableQueryHelpers::resourceShape($this->resource, 'name'),
 *           // ['translations' => ['tr' => '...', 'en' => '...'], 'current' => '...']
 *       ];
 *   }
 *
 * Form (edit modu) — tüm dilleri frontend'e ver, direkt getTranslations() kullan:
 *
 *   'name' => $this->getTranslations('name'),
 *   // { tr: '...', en: '...' }
 */
class TranslatableQueryHelpers
{
    /**
     * Aktif locale (veya tümü) üzerinde JSON kolon LIKE search filter'ı üret.
     *
     * `$locale` null ise tüm aktif diller (`sk_locale_keys()`) OR'lanarak aranır.
     * Locale belirtilirse yalnızca o dil kolonu aranır.
     *
     * LIKE escape: kullanıcı girdisindeki `%` ve `_` karakterleri escape edilir;
     * SQL injection riski yok (binding kullanılıyor), ancak LIKE pattern bozulması önlenir.
     *
     * @param  string  $column  Translatable kolon adı (örn. 'name').
     * @param  string|null  $locale  null → tüm aktif diller; string → tek dil.
     */
    public static function searchFilter(string $column, ?string $locale = null): AllowedFilter
    {
        return AllowedFilter::callback($column, function (Builder $query, mixed $value) use ($column, $locale): void {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $value).'%';
            $locales = $locale !== null ? [$locale] : sk_locale_keys();
            /** @var Connection $connection */
            $connection = $query->getConnection();
            $driver = $connection->getDriverName();

            $query->where(function (Builder $q) use ($column, $locales, $term, $driver): void {
                foreach ($locales as $loc) {
                    // SQLite does not treat '\' as a default LIKE escape character;
                    // an explicit ESCAPE clause is required for literal % and _ matching.
                    // MySQL treats '\' as escape by default, so ESCAPE '\\' is harmless there.
                    if ($driver === 'sqlite') {
                        $q->orWhereRaw(
                            "json_extract({$column}, '$.{$loc}') LIKE ? ESCAPE '\\'",
                            [$term]
                        );
                    } else {
                        $q->orWhere("{$column}->{$loc}", 'like', $term);
                    }
                }
            });
        });
    }

    /**
     * Aktif locale üzerinde JSON kolon sort'u üret.
     *
     * `$locale` null ise `app()->getLocale()` kullanılır.
     * MySQL: `JSON_UNQUOTE(JSON_EXTRACT(column, '$.locale'))` üzerinden ORDER BY.
     * SQLite: Laravel 13 aynı JSON path syntax'ını destekler.
     *
     * @param  string  $column  Translatable kolon adı (örn. 'name').
     * @param  string|null  $locale  null → aktif locale; string → sabit locale.
     */
    public static function localeSort(string $column, ?string $locale = null): AllowedSort
    {
        $locale ??= app()->getLocale();

        return AllowedSort::field($column, "{$column}->{$locale}");
    }

    /**
     * API Resource'larda translatable alan için dual-shape çıktı üret.
     *
     * Dönen dizi:
     *   - `translations`: tüm locale → değer map'i  (array<string, string>)
     *   - `current`:      aktif locale değeri, yoksa default locale, yoksa ilk değer, yoksa ''
     *
     * Spatie HasTranslations trait'i varsa `getTranslations()` kullanılır (in-memory, N+1 yok).
     * Trait yoksa kolon doğrudan array'e cast edilir.
     *
     * @return array{translations: array<string, string>, current: string}
     */
    public static function resourceShape(Model $model, string $attribute): array
    {
        /** @var array<string, string> $all */
        $all = method_exists($model, 'getTranslations')
            ? $model->getTranslations($attribute)
            : (array) ($model->{$attribute} ?? []);

        $locale = app()->getLocale();
        $current = $all[$locale] ?? $all[sk_default_locale()] ?? array_values($all)[0] ?? '';

        return [
            'translations' => $all,
            'current' => (string) $current,
        ];
    }
}
