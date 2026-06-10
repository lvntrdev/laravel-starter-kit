# Çevrilebilir Alanlar

FormBuilder'ın çevrilebilir alan tipleri, çok dilli metinleri tek bir JSON veritabanı kolonunda düzenlemenizi sağlar. Aktif diller `general.languages` ayarından okunur; Ayarlar'da dil ekleyip çıkardığınızda tüm çevrilebilir formlar bir sonraki sayfa yüklemede buna göre güncellenir.

## Ne Sağlar?

- Aktif her dil için ayrı input render eder ve bunları locale rozetiyle (`TR`, `EN`, ...) gruplar.
- Değerleri `spatie/laravel-translatable` üzerinden `{"tr": "...", "en": "..."}` şeklinde JSON olarak saklar.
- Tek bir FormRequest trait çağrısından locale bazlı Laravel validation kuralları üretir.
- Datatable yanıtları için locale-aware arama, sıralama ve resource yardımcıları sağlar.

## Ne Sağlamaz?

- Mevcut düz string kolonları otomatik JSON'a taşımaz.
- Locale bazlı slug üretmez.
- Çevrilebilir select, enum veya checkbox değerlerini desteklemez.
- Spatie Translatable fallback davranışını değiştirmez.
- Toplu çeviri import/export veya otomatik çeviri aracı sağlamaz.

## Hızlı Başlangıç

Çevrilebilir alanları JSON kolonlarda saklayın:

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->json('name');
    $table->json('description')->nullable();
    $table->timestamps();
});
```

Modelde Spatie `HasTranslations` trait'ini kullanın:

```php
use Spatie\Translatable\HasTranslations;

class Product extends Model
{
    use HasTranslations;

    public array $translatable = ['name', 'description'];

    protected $fillable = ['name', 'description'];
}
```

Validation kurallarını `HasTranslatableRules` ile üretin:

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

FormBuilder ile alanları render edin:

```ts
FB.form().addFields(
    FB.translatableText().key('name').label('Product Name').required(),
    FB.translatableTextarea().key('description').label('Description').rows(4),
    FB.translatableEditor().key('content').label('Content'),
);
```

Resource içinde çeviri-aware değer döndürün:

```php
use Lvntr\StarterKit\Support\TranslatableQueryHelpers;

'name' => TranslatableQueryHelpers::resourceShape($this->resource, 'name'),
// ['translations' => ['tr' => '...', 'en' => '...'], 'current' => '...']
```

## Field Builder'lar

### `FB.translatableText()`

Her locale için PrimeVue `InputText` render eder.

| Metot | Tip | Açıklama |
| --- | --- | --- |
| `.placeholder(value)` | `string` | Her locale input'u için placeholder. |
| `.inputType(value)` | `'text' \| 'email' \| 'url'` | HTML input tipi. |
| `.maxLength(value)` | `number` | Her input için `maxlength` attribute'u. |
| `.onlyLocales(locales)` | `string[]` | Yalnız listelenen locale kodlarını render eder. |
| `.exceptLocales(locales)` | `string[]` | Listelenen locale kodlarını gizler. |
| `.localeLabelStyle(style)` | `'badge' \| 'name' \| 'flag'` | Locale label görünümü. |

### `FB.translatableTextarea()`

Her locale için PrimeVue `Textarea` render eder.

| Metot | Tip | Açıklama |
| --- | --- | --- |
| `.placeholder(value)` | `string` | Her locale textarea'sı için placeholder. |
| `.rows(value)` | `number` | Görünür satır sayısı. |
| `.autoResize(enabled = true)` | `boolean` | PrimeVue auto-resize davranışını açar. |

Ayrıca `.onlyLocales()`, `.exceptLocales()` ve `.localeLabelStyle()` destekler.

### `FB.translatableEditor()`

Her locale için kit editörünü render eder.

| Metot | Tip | Açıklama |
| --- | --- | --- |
| `.minHeight(value)` | `string` | Editör minimum yüksekliği, örn. `'220px'`. |
| `.toolbar(value)` | `'minimal' \| 'full'` | Toolbar preset'i. |

Ayrıca `.onlyLocales()`, `.exceptLocales()` ve `.localeLabelStyle()` destekler.

## Validation Yardımcıları

`HasTranslatableRules`, v13.5.12'den itibaren vendor'dan çalışan bir trait'tir (`Lvntr\StarterKit\Support\HasTranslatableRules`); Laravel FormRequest sınıfları için kullanılır. Doğrudan vendor namespace'inden import edin. PHP trait'leri sınıflar gibi alias'lanamaz, bu yüzden `App\Support\HasTranslatableRules` fallback'i yoktur — eski sürümden yükselten bir projede hâlâ `app/Support/HasTranslatableRules.php` yerel kopyası varsa, o kopyayı silmeden önce `use` ifadesini vendor namespace'ine güncelleyin.

```php
$this->translatableRules('title', ['required', 'string', 'max:255']);
```

`tr` ve `en` aktifken primary locale `required` kuralını korur; opsiyonel locale'lerde `required`, `nullable` olur:

```php
[
    'title.tr' => ['required', 'string', 'max:255'],
    'title.en' => ['nullable', 'string', 'max:255'],
]
```

Opsiyonlar:

| Opsiyon | Tip | Açıklama |
| --- | --- | --- |
| `primary` | `array` | Primary locale kurallarını override eder. |
| `optional` | `array` | Primary olmayan locale kurallarını override eder. |
| `only` | `list<string>` | Kuralları yalnız bu locale'lere uygular. |
| `except` | `list<string>` | Bu locale'leri atlar. |

Okunabilir validation label'ları için `translatableAttributes()` kullanın:

```php
$this->translatableAttributes(['title' => __('articles.fields.title')]);
// title.tr => Başlık (Türkçe), title.en => Başlık (English)
```

## Query Yardımcıları

`TranslatableQueryHelpers`, v13.5.12'den itibaren vendor'dan çalışır (`Lvntr\StarterKit\Support\TranslatableQueryHelpers`). `App\Support\TranslatableQueryHelpers` import'u `class_alias` ile şeffaf şekilde çalışmaya devam eder, mevcut kod etkilenmez.

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

- `searchFilter('name')` tüm aktif locale'lerde `LIKE` araması yapar.
- `searchFilter('name', 'tr')` yalnız tek locale'de arar.
- `localeSort('name')` `app()->getLocale()` değerine göre sıralar.
- `localeSort('name', 'en')` sabit locale'e göre sıralar.
- `resourceShape($model, 'name')`, `translations` ile birlikte mevcut locale değerini döndürür.

## Ayarlar ile Etkileşim

Çevrilebilir alanlar aktif dilleri Ayarlar -> Genel (`general.languages`) üzerinden okur. Kullanıcı Ayarlar'ı kaydettiğinde Settings cache'i otomatik temizlenir.

Dil eklendiğinde bir sonraki render'da yeni locale input'u görünür. Mevcut kayıtlar yeni locale için siz backfill yapana kadar eski JSON yapısını korur.

Dil kaldırıldığında o locale formda gizlenir, fakat saklanan JSON key'i silinmez. Dili tekrar eklerseniz eski değer yeniden görünür.

Tek dilli modda bile değer JSON saklanır, örn. `{"tr": "..."}`. Böylece ileride çok dilli yapıya geçmek için migration gerekmez.

## Migration İpuçları

Yoğun kullanılan düz string kolonu tek bir destructive migration ile JSON'a çevirmeyin. İki aşamalı deployment kullanın:

1. Yeni nullable JSON kolon ekleyin ve eski değerleri default locale'e backfill edin.
2. Yeni kolonu okuyan kodu deploy edin.
3. Veriyi doğruladıktan sonraki bir sürümde eski kolonu rename/drop edin.

Backfill örneği:

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

## Notlar

- Çevrilebilir alanlar `.groupPrefix()` veya `.groupSuffix()` desteklemez; her locale zaten kendi input group'u içinde render edilir.
- `.onlyLocales(['tr'])` veya `.exceptLocales(['en'])` yalnız iş anlamı dil bazlı farklılaşan alanlarda kullanılmalı.
- Zengin HTML için `FB.editor()` ile aynı sanitizer disiplinini koruyun: yazarken sanitize edin, `v-html` ile render etmeden önce tekrar sanitize edin.
