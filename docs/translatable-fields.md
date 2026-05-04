# Translatable Fields

FormBuilder's translatable field types let you store and edit multi-language text in a single JSON database column. Active languages are driven by the `general.languages` setting — add or remove a language in Settings and every translatable form on the next page load adapts automatically.

## What it does

- Renders one input per active language, grouped with a locale badge (`TR`, `EN`, …).
- Saves values as `{"tr": "...", "en": "..."}` in a JSON column via `spatie/laravel-translatable`.
- Generates per-locale Laravel validation rules from a single trait method call.
- Provides locale-aware search and sort helpers for Datatable queries.

## What it does NOT do

- Migrate existing plain-string columns to JSON automatically — see [Migration tips](#migration-tips).
- Generate per-locale slugs (`spatie/laravel-sluggable` integration is out of scope).
- Support translatable select / enum options (text, textarea, and editor only).
- Modify the locale fallback chain — `config/translatable.php` defaults apply.
- Provide bulk translation import/export or AI auto-translate tooling.

---

## Quick start

The five steps below are all you need for a new translatable resource.

```php
// 1. Migration — store translatable columns as JSON
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->json('name');
    $table->json('description')->nullable();
    $table->unsignedDecimal('price', 10, 2)->default(0);
    $table->timestamps();
});
```

```php
// 2. Model — add Spatie HasTranslations
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];

    protected $fillable = ['name', 'description', 'price'];
}
```

```php
// 3. FormRequest — use HasTranslatableRules trait
use App\Support\HasTranslatableRules;

class StoreProductRequest extends FormRequest
{
    use HasTranslatableRules;

    public function rules(): array
    {
        return [
            ...$this->translatableRules('name', ['required', 'string', 'max:255']),
            ...$this->translatableRules('description', ['nullable', 'string']),
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return $this->translatableAttributes([
            'name'        => __('product.name'),
            'description' => __('product.description'),
        ]);
    }
}
```

```typescript
// 4. Form — FB translatable field builders
import { FB } from '@lvntr/components/FormBuilder';

const fields = [
    FB.translatableText()
        .key('name')
        .label('Product Name')
        .required(),

    FB.translatableTextarea()
        .key('description')
        .label('Description')
        .rows(4),

    FB.inputNumber()
        .key('price')
        .label('Price')
        .required(),
];
```

```php
// 5. API Resource — return translations for edit forms, current locale for lists
public function toArray(Request $request): array
{
    return [
        'id'          => $this->id,
        'name'        => $this->getTranslations('name'),   // edit form: {tr:…, en:…}
        'price'       => $this->price,
    ];
}
```

---

## API Reference

### FB facade — translatable field builders

All three builders share a common set of chain methods inherited from `TranslatableBaseBuilder`. Each builder is started from the `FB` facade and finalized when passed to a `SkForm` `fields` prop.

#### `FB.translatableText()`

Renders `InputText` per locale.

| Chain method | Type | Description |
|---|---|---|
| `.key(k)` | `string` | Form field key — must match the model attribute name. No dots. |
| `.label(l)` | `string` | Field label shown above the input group. |
| `.required()` | — | Marks the field required visually. |
| `.placeholder(p)` | `string` | Placeholder text for every locale input. |
| `.inputType(t)` | `'text' \| 'email' \| 'url'` | HTML input type. Default: `'text'`. |
| `.maxLength(n)` | `number` | `maxlength` attribute on each input. |
| `.onlyLocales(l[])` | `string[]` | Render only the given locale codes, even if more are active. |
| `.exceptLocales(l[])` | `string[]` | Skip the given locale codes. |
| `.translatableLayout(l)` | `'inline' \| 'tabs'` | `'inline'` stacks inputs vertically (default); `'tabs'` wraps them in PrimeVue Tabs. |
| `.localeLabelStyle(s)` | `'badge' \| 'name' \| 'flag'` | How the locale label is shown. `'badge'` → `TR`; `'name'` → `Türkçe`; `'flag'` → flag emoji. Default: `'badge'`. |

#### `FB.translatableTextarea()`

Renders `Textarea` per locale. All `TranslatableBaseBuilder` methods apply, plus:

| Chain method | Type | Description |
|---|---|---|
| `.placeholder(p)` | `string` | Textarea placeholder. |
| `.rows(n)` | `number` | Number of visible rows. |
| `.autoResize()` | — | Enables PrimeVue auto-resize behaviour. |

#### `FB.translatableEditor()`

Renders a rich-text editor per locale. All `TranslatableBaseBuilder` methods apply, plus:

| Chain method | Type | Description |
|---|---|---|
| `.minHeight(h)` | `string` | CSS min-height of the editor area, e.g. `'200px'`. |
| `.toolbar(t)` | `'minimal' \| 'full'` | Toolbar preset. |

---

### `HasTranslatableRules` trait

Used in `FormRequest` classes. Import with `use App\Support\HasTranslatableRules;`.

#### `translatableRules(string $attribute, array $rules, array $options = []): array`

Generates per-locale validation rules for a translatable attribute.

```php
$this->translatableRules('name', ['required', 'string', 'max:255'])
// With two active locales (tr, en) returns:
// ['name.tr' => ['required', 'string', 'max:255'], 'name.en' => ['nullable', 'string', 'max:255']]
```

The primary locale (first in `app.languages`) keeps the original rules. All other locales have `'required'` replaced with `'nullable'`.

**`$options` keys:**

| Key | Type | Description |
|---|---|---|
| `primary` | `array` | Override rules for the primary locale only. |
| `optional` | `array` | Override rules for non-primary locales. |
| `only` | `string[]` | Apply rules only to these locale codes. |
| `except` | `string[]` | Skip these locale codes entirely. |

#### `translatableAttributes(array $attributes): array`

Generates per-locale attribute labels for validation error messages.

```php
$this->translatableAttributes(['name' => __('product.name')])
// Returns: ['name.tr' => 'Ürün Adı (Türkçe)', 'name.en' => 'Ürün Adı (English)']
```

---

### `TranslatableQueryHelpers` static class

Used in controller index methods or `DatatableQueryBuilder` setups. Import with `use App\Support\TranslatableQueryHelpers;`.

#### `searchFilter(string $column, ?string $locale = null): AllowedFilter`

Returns a `spatie/laravel-query-builder` `AllowedFilter` that performs a `LIKE` search on the JSON column.

- When `$locale` is `null` (default), all active locales are ORed together.
- When `$locale` is given (e.g. `'tr'`), only that locale column is searched.

```php
QueryBuilder::for(Product::class)
    ->allowedFilters([
        TranslatableQueryHelpers::searchFilter('name'),
    ]);
```

#### `localeSort(string $column, ?string $locale = null): AllowedSort`

Returns an `AllowedSort` that orders by the JSON sub-key for the given locale.

- When `$locale` is `null`, uses `app()->getLocale()` (the request locale).

```php
->allowedSorts([
    TranslatableQueryHelpers::localeSort('name'),
])
```

#### `resourceShape(Model $model, string $attribute): array`

Returns a dual-shape array for use in API Resources when both the datatable display (current locale) and the edit form (all translations) are needed.

```php
'name' => TranslatableQueryHelpers::resourceShape($this->resource, 'name'),
// ['translations' => ['tr' => '…', 'en' => '…'], 'current' => '…']
```

---

### Global helpers

| Helper | Return | Description |
|---|---|---|
| `sk_locale_keys()` | `list<string>` | Active locale codes in order, e.g. `['tr', 'en']`. Reads `app.languages` config. |
| `sk_default_locale()` | `string` | The first active locale code. Falls back to `app.fallback_locale`. |

---

## Interaction with Settings

Translatable fields read the active language list from `general.languages` (Settings → General). The compiled list is cached by the Settings system; when a user saves the Settings page the cache is cleared automatically — no additional action is needed.

**Adding a language:** On the next form render, a new input slot appears for the new locale. Existing records have an empty value for that locale until you backfill them manually.

**Removing a language:** The input slot disappears on the next render. The JSON column still contains the removed locale's data; it is not deleted. If you later re-add the language, the stored value reappears.

**Single-language mode:** When only one language is active, the locale badge is hidden and only a single input is shown. The stored value is still `{"tr": "…"}` — not a plain string — so switching to multi-language later works without a migration.

---

## Full example — Product domain

### Migration

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->json('name');
    $table->json('description')->nullable();
    $table->unsignedDecimal('price', 10, 2)->default(0);
    $table->softDeletes();
    $table->timestamps();
});
```

### Model

```php
<?php

namespace App\Domain\Products\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    use HasTranslations, SoftDeletes;

    public array $translatable = ['name', 'description'];

    protected $fillable = ['name', 'description', 'price'];
}
```

### StoreProductRequest

```php
<?php

namespace App\Domain\Products\Requests;

use App\Support\HasTranslatableRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    use HasTranslatableRules;

    public function rules(): array
    {
        return [
            ...$this->translatableRules('name', ['required', 'string', 'max:255']),
            ...$this->translatableRules('description', ['nullable', 'string']),
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return $this->translatableAttributes([
            'name'        => __('product.name'),
            'description' => __('product.description'),
        ]);
    }
}
```

### UpdateProductRequest

```php
<?php

namespace App\Domain\Products\Requests;

use App\Support\HasTranslatableRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    use HasTranslatableRules;

    public function rules(): array
    {
        return [
            ...$this->translatableRules('name', ['required', 'string', 'max:255']),
            ...$this->translatableRules('description', ['nullable', 'string']),
            'price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return $this->translatableAttributes([
            'name'        => __('product.name'),
            'description' => __('product.description'),
        ]);
    }
}
```

### Controller (index + store + update)

```php
<?php

namespace App\Domain\Products\Controllers;

use App\Domain\Products\Models\Product;
use App\Domain\Products\Requests\StoreProductRequest;
use App\Domain\Products\Requests\UpdateProductRequest;
use App\Support\TranslatableQueryHelpers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\QueryBuilder;

class ProductController
{
    public function index(Request $request)
    {
        $products = QueryBuilder::for(Product::class)
            ->allowedFilters([
                TranslatableQueryHelpers::searchFilter('name'),
            ])
            ->allowedSorts([
                TranslatableQueryHelpers::localeSort('name'),
            ])
            ->paginate(25);

        return Inertia::render('Products/Index', compact('products'));
    }

    public function store(StoreProductRequest $request)
    {
        Product::create($request->validated());

        return to_route('products.index');
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        return to_route('products.index');
    }
}
```

### ProductForm.vue

```typescript
import { FB } from '@lvntr/components/FormBuilder';

const fields = [
    FB.translatableText()
        .key('name')
        .label('Product Name')
        .required(),

    FB.translatableTextarea()
        .key('description')
        .label('Description')
        .rows(4),

    FB.inputNumber()
        .key('price')
        .label('Price')
        .required(),
];
```

### ProductResource (list vs. edit)

```php
// List / datatable — send current locale only
public function toArray(Request $request): array
{
    return [
        'id'    => $this->id,
        'name'  => $this->getTranslation('name', app()->getLocale()),
        'price' => $this->price,
    ];
}

// Edit form — send all translations so the form can pre-fill every locale
public function toArray(Request $request): array
{
    return [
        'id'          => $this->id,
        'name'        => $this->getTranslations('name'),
        'description' => $this->getTranslations('description'),
        'price'       => $this->price,
    ];
}
```

---

## Limitations & FAQ

**What happens to existing records when I add a new language?**
The JSON column keeps whatever keys it already has. New locale keys are simply absent for old records — Spatie returns an empty string for missing keys. You must backfill old records manually if you need them translated.

**What happens to stored data when I remove a language from Settings?**
Nothing. The data stays in the JSON column. The input is hidden in forms, but the value is not deleted.

**Which locales does `searchFilter` search by default?**
All active locales are OR-joined in the `LIKE` query. Pass a specific locale to restrict: `TranslatableQueryHelpers::searchFilter('name', 'tr')`.

**Which locale does `localeSort` sort by?**
The request locale (`app()->getLocale()`) by default. Override: `TranslatableQueryHelpers::localeSort('name', 'tr')`.

**Is a single active language any different from multi-language internally?**
No. The value is always stored as a JSON object (`{"tr": "…"}`). The UI hides the locale badge when only one language is active, but the backend behaviour is identical.

**What is the minimum height for `translatableEditor`?**
Set it with `.minHeight('200px')`. There is no enforced minimum — it defaults to the editor component's own CSS if not set.

**When should I use `onlyLocales` or `exceptLocales`?**
Use them when a specific field should not be translated into all active languages. For example, a legal field that is only valid in one language: `.onlyLocales(['tr'])`. These filters apply to both rendering and the default value initialisation inside `SkForm`.

**Can I use `.groupPrefix()` / `.groupSuffix()` on a translatable field?**
No. Translatable fields don't support `.groupPrefix()` / `.groupSuffix()` chain methods. The translatable component already renders an `InputGroup` per locale, and combining it with a wrapper InputGroup will produce nested groups that break PrimeVue layout.

---

## Migration tips

### Converting an existing `string` column to JSON (two-step pattern)

Never alter a live column in a single destructive step. Use this two-migration approach:

**Step 1 — add the new JSON column and backfill**

```php
// Migration 1: add json column, copy data
Schema::table('products', function (Blueprint $table) {
    $table->json('name_translatable')->nullable()->after('name');
});

DB::table('products')->orderBy('id')->chunk(200, function ($rows) {
    foreach ($rows as $row) {
        DB::table('products')->where('id', $row->id)->update([
            'name_translatable' => json_encode(['tr' => $row->name]),
        ]);
    }
});
```

Deploy this migration. Let it run on production. Verify the new column.

**Step 2 — rename and drop the old column**

```php
// Migration 2: rename new column, drop old column
Schema::table('products', function (Blueprint $table) {
    $table->renameColumn('name_translatable', 'name_json');
    $table->dropColumn('name');
});

Schema::table('products', function (Blueprint $table) {
    $table->renameColumn('name_json', 'name');
});
```

Update the model's `$translatable` array and `$fillable` after both migrations are deployed.

> Do not run both migrations in the same deployment if the table is large or in active use. The two-step pattern ensures zero data loss and allows rollback after each step.
