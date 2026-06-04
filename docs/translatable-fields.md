# Translatable Fields

FormBuilder's translatable field types let you store and edit multi-language text in a single JSON database column. Active languages are driven by the `general.languages` setting; adding or removing a language in Settings updates every translatable form on the next page load.

## What It Does

- Renders one input per active language, grouped with a locale badge (`TR`, `EN`, ...).
- Stores values as `{"tr": "...", "en": "..."}` in JSON columns through `spatie/laravel-translatable`.
- Generates per-locale Laravel validation rules from one FormRequest trait call.
- Provides locale-aware search, sort and resource helpers for datatable responses.

## What It Does Not Do

- It does not migrate existing plain string columns to JSON automatically.
- It does not generate per-locale slugs.
- It does not support translatable select, enum or checkbox values.
- It does not change Spatie Translatable's fallback behaviour.
- It does not provide bulk translation import/export or automatic translation tooling.

## Quick Start

Store translated attributes in JSON columns:

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->json('name');
    $table->json('description')->nullable();
    $table->timestamps();
});
```

Use Spatie's `HasTranslations` trait on the model:

```php
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];

    protected $fillable = ['name', 'description'];
}
```

Generate validation rules with `HasTranslatableRules`:

```php
use Lvntr\StarterKit\Support\HasTranslatableRules;

class StoreProductRequest extends FormRequest
{
    use HasTranslatableRules;

    public function rules(): array
    {
        return [
            ...$this->translatableRules('name', ['required', 'string', 'max:255']),
            ...$this->translatableRules('description', ['nullable', 'string']),
        ];
    }

    public function attributes(): array
    {
        return $this->translatableAttributes([
            'name' => __('product.name'),
            'description' => __('product.description'),
        ]);
    }
}
```

Render fields with FormBuilder:

```ts
FB.form().addFields(
    FB.translatableText().key('name').label('Product Name').required(),
    FB.translatableTextarea().key('description').label('Description').rows(4),
    FB.translatableEditor().key('content').label('Content'),
);
```

Return translation-aware values from resources:

```php
use Lvntr\StarterKit\Support\TranslatableQueryHelpers;

'name' => TranslatableQueryHelpers::resourceShape($this->resource, 'name'),
// ['translations' => ['tr' => '...', 'en' => '...'], 'current' => '...']
```

## Field Builders

### `FB.translatableText()`

Renders a PrimeVue `InputText` per locale.

| Method | Type | Description |
| --- | --- | --- |
| `.placeholder(value)` | `string` | Placeholder for every locale input. |
| `.inputType(value)` | `'text' \| 'email' \| 'url'` | HTML input type. |
| `.maxLength(value)` | `number` | `maxlength` attribute on each input. |
| `.onlyLocales(locales)` | `string[]` | Render only the listed locale codes. |
| `.exceptLocales(locales)` | `string[]` | Hide the listed locale codes. |
| `.translatableLayout(layout)` | `'inline' \| 'tabs'` | Inline stacked fields or tabbed locale panels. |
| `.localeLabelStyle(style)` | `'badge' \| 'name' \| 'flag'` | Locale label style. |

### `FB.translatableTextarea()`

Renders a PrimeVue `Textarea` per locale.

| Method | Type | Description |
| --- | --- | --- |
| `.placeholder(value)` | `string` | Placeholder for every locale textarea. |
| `.rows(value)` | `number` | Visible row count. |
| `.autoResize(enabled = true)` | `boolean` | Enables PrimeVue auto-resize. |

It also supports `.onlyLocales()`, `.exceptLocales()`, `.translatableLayout()` and `.localeLabelStyle()`.

### `FB.translatableEditor()`

Renders the kit editor per locale.

| Method | Type | Description |
| --- | --- | --- |
| `.minHeight(value)` | `string` | Editor minimum height, for example `'220px'`. |
| `.toolbar(value)` | `'minimal' \| 'full'` | Toolbar preset. |

It also supports `.onlyLocales()`, `.exceptLocales()`, `.translatableLayout()` and `.localeLabelStyle()`.

## Validation Helpers

`HasTranslatableRules` is a trait that runs from vendor (`Lvntr\StarterKit\Support\HasTranslatableRules`) since v13.6.0, intended for Laravel FormRequests. Import it directly from the vendor namespace. PHP traits cannot be aliased like classes, so there is no `App\Support\HasTranslatableRules` fallback — if a project upgraded from an older version still has a local copy at `app/Support/HasTranslatableRules.php`, update the `use` statement to the vendor namespace before deleting that copy.

```php
$this->translatableRules('title', ['required', 'string', 'max:255']);
```

With `tr` and `en` active, the primary locale keeps `required`; optional locales replace `required` with `nullable`:

```php
[
    'title.tr' => ['required', 'string', 'max:255'],
    'title.en' => ['nullable', 'string', 'max:255'],
]
```

Options:

| Option | Type | Description |
| --- | --- | --- |
| `primary` | `array` | Override rules for the primary locale. |
| `optional` | `array` | Override rules for non-primary locales. |
| `only` | `list<string>` | Apply rules only to these locales. |
| `except` | `list<string>` | Skip these locales. |

Use `translatableAttributes()` for readable validation labels:

```php
$this->translatableAttributes(['title' => __('articles.fields.title')]);
// title.tr => Title (Turkish), title.en => Title (English)
```

## Query Helpers

`TranslatableQueryHelpers` runs from vendor (`Lvntr\StarterKit\Support\TranslatableQueryHelpers`) since v13.6.0. The `App\Support\TranslatableQueryHelpers` import keeps working transparently via a `class_alias`, so existing code is unaffected.

```php
return DatatableQueryBuilder::for(Product::class)
    ->filterable([
        TranslatableQueryHelpers::searchFilter('name'),
    ])
    ->sortable([
        TranslatableQueryHelpers::localeSort('name'),
        'created_at',
    ])
    ->response();
```

- `searchFilter('name')` searches all active locales with `LIKE`.
- `searchFilter('name', 'tr')` searches only one locale.
- `localeSort('name')` sorts by `app()->getLocale()`.
- `localeSort('name', 'en')` sorts by a fixed locale.
- `resourceShape($model, 'name')` returns `translations` plus the current locale value.

## Settings Interaction

Translatable fields read active languages from Settings -> General (`general.languages`). When a user updates Settings, the Settings cache is cleared automatically.

Adding a language shows a new locale input on the next render. Existing records keep their old JSON shape until you backfill the new locale manually.

Removing a language hides that locale in forms, but it does not delete the stored JSON key. Re-adding the language makes the stored value visible again.

Single-language mode still stores JSON, for example `{"tr": "..."}`. This keeps future multi-language expansion migration-free.

## Migration Tips

Do not convert a busy plain string column to JSON in a single destructive migration. Use a two-step deployment:

1. Add a new nullable JSON column and backfill old values into the default locale.
2. Deploy code that reads the new column.
3. Rename/drop the old column in a later release after verifying data.

Example backfill:

```php
Schema::table('products', function (Blueprint $table) {
    $table->json('name_translatable')->nullable()->after('name');
});

DB::table('products')->orderBy('id')->chunk(200, function ($rows) {
    foreach ($rows as $row) {
        DB::table('products')->where('id', $row->id)->update([
            'name_translatable' => json_encode([sk_default_locale() => $row->name]),
        ]);
    }
});
```

## Notes

- Translatable fields do not support `.groupPrefix()` or `.groupSuffix()` because each locale already renders its own input group.
- Use `.onlyLocales(['tr'])` or `.exceptLocales(['en'])` only for fields whose business meaning is language-specific.
- For rich HTML, keep the same sanitizer discipline used by `FB.editor()`: sanitize on write and again before rendering with `v-html`.
