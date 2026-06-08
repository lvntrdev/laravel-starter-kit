# Datatable

Starter kit, tekrar kullanılabilir bir datatable yapısını iki parçalı sunar:

- frontend `SkDatatable` bileşeni
- backend `DatatableQueryBuilder`

## İmportlar

```ts
import { DB } from '@lvntr/components/DatatableBuilder/core';
import SkDatatable from '@lvntr/components/DatatableBuilder/SkDatatable.vue';
```

## Frontend Builder

Tabloyu fluent `DB` API ile yapılandırın:

```vue
<script setup lang="ts">
    import { DB } from '@lvntr/components/DatatableBuilder/core';
    import SkDatatable from '@lvntr/components/DatatableBuilder/SkDatatable.vue';
    import users from '@/routes/users';

    interface UserRow {
        id: string;
        full_name: string;
        email: string;
        role: string;
        status: string;
        created_at: string;
    }

    const tableConfig = DB.table<UserRow>()
        .route(users.dtApi.url())
        .addColumns(
            DB.column<UserRow>().label('sk-common.full_name').key('full_name'),
            DB.column<UserRow>().key('email'),
            DB.column<UserRow>().label('sk-common.role').key('role'),
            DB.column<UserRow>().key('status').tag('definition').tagKey('userStatus').tagOutlined(),
        )
        .addFilters(DB.filter().key('status').definitionOptions('userStatus'))
        .addActions(
            DB.action<UserRow>()
                .icon('pi pi-pencil')
                .severity('warn')
                .label('sk-button.edit')
                .handle((row) => console.log(row.id)),
        )
        .build();
</script>

<template>
    <SkDatatable :config="tableConfig" refresh-key="users-table" />
</template>
```

Temel yetenekler:

- sunucu taraflı sayfalama, arama, sıralama ve filtreleme
- inline veya panel filtreleri
- satır aksiyonları ve menü aksiyonları
- definition tabanlı tag kullanımı
- sticky kolon desteği

## Table Builder API

- `route(url)` — string, Wayfinder sonucu ya da `{ url }` döndüren callback kabul eder
- `sortable(enabled)`
- `pagination(enabled)`
- `searchable(enabled)`
- `isCard(enabled)`
- `cardTitle(title)`
- `cardSubtitle(subtitle)`
- `perPage(count)`
- `idColumn(config | false)`
- `addColumns(...columns)`
- `addFilters(...filters)`
- `addActions(...actions)`
- `addMenuActions(...menuActions)`
- `menuButton(config)`
- `create(config)`

## Column Builder

- `key(string)`
- `label(string)`
- `sortable(boolean)`
- `render((row, escape) => string)`
- `tag('definition', tagKey?)`
- `tagKey(key)`
- `colors(map)`
- `icons(map)`
- `tagIconPos('left' | 'right')`
- `tagSoft(enabled = true)`
- `tagRounded(enabled = true)`
- `tagOutlined(enabled = true)`
- `sticky()`

Tag gösterimi artık definition tabanlıdır. `tag('definition')`, `userStatus` gibi bir definition key'i ile eşleşen kolonlarda kullanılır. `SkDatatable`, label, severity ve icon bilgisini definitions payload'undan çözer; istersen `colors({...})`, `icons({...})`, `tagSoft()`, `tagRounded()`, `tagOutlined()` ve `tagIconPos()` ile görünümü override edebilirsin.

```ts
DB.column<UserRow>()
    .key('status')
    .tag('definition', 'userStatus')
    .colors({
        active: 'emerald',
        inactive: 'rose',
    })
    .icons({
        active: 'pi pi-check-circle',
        inactive: 'pi pi-times-circle',
    })
    .tagIconPos('right')
    .tagOutlined()
    .tagRounded();
```

Notlar:

- `tagKey()`, `userStatus` gibi definition grup anahtarını belirtir
- `colors()` ve `icons()` mevcut satır değeri ile eşleşir
- override verilmezse `SkDatatable`, `useDefinition()` üzerinden gelen severity ve icon bilgisini kullanır

## Filter Builder

- `key(string)`
- `label(string)`
- `type('select' | 'select-button' | 'date' | 'daterange')`
- `options([...])`
- `definitionOptions(key)`
- `optionsUrl(url)`
- `placeholder(string)`
- `inline()`
- `placement('inline' | 'panel')`

Serbest metin arama, ayrı bir text filter type yerine tablo seviyesindeki `searchable(true)` arama kutusu ile yönetilir.

## Satır Aksiyonları

### Inline actions

Satırın içinde doğrudan görünen butonlar için `DB.action()` kullanılır.

- `icon`
- `severity`
- `size`
- `variant`
- `rounded`
- `raised`
- `text`
- `outlined`
- `label`
- `tooltip`
- `visible(fn)`
- `handle(fn)`

### Menu actions

Üç nokta menüsündeki aksiyonlar için `DB.menuAction()` kullanılır.

- `label`
- `icon`
- `separator`
- `visible(fn)`
- `handle(fn)`

## Toplu Aksiyonlar (Bulk Actions)

Toplu aksiyonlar, kullanıcının birden fazla satırı — sayfa değişse de — seçip tek bir backend işlemi çalıştırmasına olanak tanır. Seçim, belirli bir ID listesini ya da mevcut filtre durumuna uyan tüm satırları kapsayabilir.

### Frontend

`SkDatatable`'a `bulk-actions` prop'u ile aksiyon tanımları dizisi verilir. Her tanımda en az `label`, `action` anahtarı ve `icon` bulunmalıdır:

```vue
<template>
    <SkDatatable
        :config="tableConfig"
        :bulk-actions="[
            { label: 'sk-button.delete', action: 'delete', icon: 'pi pi-trash', severity: 'danger' },
        ]"
        bulk-action-url="/admin/users/bulk"
        refresh-key="users-table"
    />
</template>
```

Kullanıcı bir aksiyon tetiklediğinde `SkDatatable` şu payload'u gönderir:

```json
{
    "action": "delete",
    "ids": ["uuid-1", "uuid-2"],
    "select_all_filtered": false,
    "filter_snapshot": {}
}
```

`select_all_filtered` `true` olduğunda `ids` boş gelir; `filter_snapshot` mevcut filtre durumunu taşır ve backend bu değerden filtrelenmiş kümeyi yeniden oluşturur.

Seçim, sayfa değişikliklerinde korunur. Backend yanıt verdikten sonra `onSuccess` ve `onError` Inertia router callback'leri tetiklenir.

### Request Doğrulama

`ids.*` alanı `string|min:1|max:64` kuralıyla doğrulanır. Bu kural; integer auto-increment anahtarları, UUID (36 karakter) ve ULID (26 karakter) formatlarını tipe özgü ayrı bir kural gerektirmeden karşılar.

### Backend

`BulkAction` interface'ini implemente edin:

```php
interface BulkAction
{
    public function handle(Collection $models, array $meta): BulkActionResult;
}
```

`BulkActionDispatcher`, `action` anahtarından doğru action sınıfını çözer; `ids` doluysa belirtilen model kümesini, `select_all_filtered` `true` ise tüm filtrelenmiş kümeyi aktarır.

`BulkActionResult` şu alanları taşır:

```php
new BulkActionResult(
    processed: 12,
    skipped: 1,
    failed: 0,
    message: '12 kullanıcı silindi.',
);
```

Controller, JSON response değil Inertia flash response döner:

```php
return back()->with('success', $result->message);
// veya
return back()->with('error', $result->message);
```

### Stub Örnekleri

**BulkDeleteUserAction** — aktif kullanıcının rank'ına eşit veya üstündeki kullanıcıları atlar:

```php
final class BulkDeleteUserAction implements BulkAction
{
    public function __construct(private readonly User $actor) {}

    public function handle(Collection $models, array $meta): BulkActionResult
    {
        $processed = 0;
        $skipped   = 0;

        foreach ($models as $user) {
            if ($user->rank >= $this->actor->rank) {
                $skipped++;
                continue;
            }
            $user->delete();
            $processed++;
        }

        return new BulkActionResult($processed, $skipped, 0);
    }
}
```

**BulkDeleteRoleAction** — sistem rollerini silme işlemine karşı korur:

```php
final class BulkDeleteRoleAction implements BulkAction
{
    public function handle(Collection $models, array $meta): BulkActionResult
    {
        $systemRoles = config('permission-resources.system_roles', []);
        $processed   = 0;
        $skipped     = 0;

        foreach ($models as $role) {
            if (in_array($role->name, $systemRoles, true)) {
                $skipped++;
                continue;
            }
            $role->delete();
            $processed++;
        }

        return new BulkActionResult($processed, $skipped, 0);
    }
}
```

## Özel Hücre Slot'ları

`SkDatatable`, kolon bazlı slot'ları `cell-{column.key}` isim kalıbı ile dışarı açar. Her slot şu verileri alır:

- `row`: satırın tüm objesi
- `value`: ilgili kolon anahtarı için çözülen değer

Slot içeriğinin dahili badge görünümü ile aynı olmasını istiyorsan PrimeVue'nun `<Tag>` bileşenini kullan (auto-import, ayrıca import gerekmez). `severity` hem 6 PrimeVue severity'sini hem de desteklenen SK palet adlarını (ör. `indigo`, `emerald`) kabul eder; soft/outlined `p-tag-soft` / `p-tag-outlined` sınıflarıyla opt-in'dir:

```vue
<template>
    <SkDatatable :config="tableConfig">
        <template #cell-status="{ row, value }">
            <Tag :value="String(value)" :severity="row.is_active ? 'success' : 'danger'" rounded class="p-tag-soft" />
        </template>
    </SkDatatable>
</template>
```

Eşleşen bir `cell-*` slot'u varsa o kolonun dahili görünümü, definition tag'leri dahil olmak üzere, bununla override edilir.

## Backend Builder

Controller içinde ya da özel query sınıflarında `DatatableQueryBuilder` kullanın:

```php
return DatatableQueryBuilder::for(User::query())
    ->searchable(['name', 'email'])
    ->sortable(['id', 'name', 'email', 'created_at'])
    ->filterable(['status'])
    ->defaultSort('-created_at')
    ->response();
```

### Arama semantiği

`searchable()` gelen `filter[search]` değerini boşluklara göre kelimelere
böler. Her kelime listelenen her kolona `LIKE '%kelime%'` ile eşlenir
(kolonlar arası OR) ve **tüm kelimelerin eşleşmesi gerekir** (kelimeler
arası AND). Yani `['name', 'email']` üzerinde `filter[search]=ali veli`
sorgusu; hem `ali` hem `veli`'nin name veya email alanlarından birinde
geçtiği satırları döner. Arama değerindeki `%` ve `_` karakterleri
escape'lenerek literal olarak aranır.

Çağıran tarafın `perPage()` kullanmadığı ve `?per_page=` parametresinin
bulunmadığı durumda varsayılan sayfa büyüklüğü
`config('starter-kit.datatable.default_per_page')` üzerinden okunur ve
tanımlı değilse `10`'a düşer.

`?per_page=` değerinin üst sınırı `config('starter-kit.datatable.max_per_page')`
(veya `STARTER_KIT_DATATABLE_MAX_PER_PAGE` env var'ı) ile belirlenir; bu
anahtar tanımlı değilse `100`'e düşer. Üst sınırın üstündeki istekler
sessizce bu tavana çekilir — meşru çağrıları kırmadan sunucuyu kazara
veya kötü niyetli büyük-payload taleplerinden korur.

## Önerilen Kullanım

Büyük modüllerde datatable mantığını `app/Domain/*/Queries/*DatatableQuery.php` altında tutup controller içine o query sınıfını enjekte edin.

## Beklenen Yanıt Yapısı

`SkDatatable` şu tipte bir payload bekler:

```json
{
    "data": [],
    "total": 0,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1,
    "from": null,
    "to": null
}
```

## Dahili Davranışlar

`SkDatatable` şunları hazır olarak getirir:

- server-side arama, sıralama, sayfalama ve filtreleme
- tag kolonları ve definition tabanlı filtreler için otomatik definition yükleme
- PrimeVue `<Tag>` üzerinden definition tabanlı label, severity ve icon gösterimi
- paylaşılabilir tablo URL'leri için query string senkronizasyonu
- sayfa yenilemelerinde `sessionStorage` kalıcılığı
- `refresh-key` ile opsiyonel refresh bus entegrasyonu
- dahili per-page kontrolleri
- `cell-{column.key}` slot'ları ile kolon bazlı özel render override'ı
- çekilen satırları dışarı veren `load` eventi

## İyi Kullanım Alanları

- admin kullanıcı listeleri
- rol listeleri
- işlem kayıtları
- filtre, aksiyon ve sunucu taraflı sayfalama gerektiren tüm kaynaklar
