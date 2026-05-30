---
name: lvntr-kit-frontend
description: "Bu skill'i şu durumlarda aktive et: resources/js/pages/Admin/** altında Vue sayfası yazarken veya değiştirirken; @lvntr/components/* (SkForm, SkDatatable, SkTabs, AppDialog, AvatarUpload, PageLoading) kullanırken; FormBuilder / DatatableBuilder / TabBuilder (FB / DB / TB) ile form, tablo veya tab yapılandırması oluştururken; useDialog, useConfirm, useApi, useDefinition, useRefreshBus, useCan, useFlash, useSidebar, useDarkMode composable'larını çağırırken; dialog açarken, tablo refresh'i yaparken, permission gating eklerken. Türkçe tetikleyiciler: tablo ekle, form ekle, dialog aç, vue sayfası, bileşen, composable. Use when building Vue/Inertia/PrimeVue UI (forms, tables, tabs, dialogs) in a Lvntr Starter Kit app."
---

# Lvntr Kit — Frontend Skill

Frontend bileşenleri, builder API'leri ve composable'lar için referans skill.
Backend katmanı (Action/DTO/API/route) → `lvntr-kit-domain`.
Tüm hard rule'lar, proje şekli ve komut referansı → `lvntr-starter-kit`.

---

## Iron Law (Frontend)

Bu dört kural ihlal edildiğinde kit bozulur; hiçbir gerekçe geçerli değildir.

- **SkForm / SkDatatable / SkTabs kullan — raw PrimeVue kullanma.** `DataTable`, `TabView`, ham `<form>` kullanmak kit'in builder zincirini kırar.
- **`useDialog()` / `useConfirm()` bypass etme.** Doğrudan PrimeVue `Dialog` import etme, native `confirm()` / `alert()` çağırma. `AppDialog` ve `ConfirmDialog group="app"` zaten `AdminLayout.vue`'da monte edilmiştir.
- **`useApi()` kullan — `import axios` yok.** API çağrıları kit'in CSRF-aware HTTP client'ından geçmelidir.
- **Vue'da URL hardcode etme.** `@/routes/**` veya `@/actions/**` import et, `.url()` çağır. Route değişikliğinden sonra `php artisan wayfinder:generate` çalıştır.

> Numaralı kanonik hard rule'lar (1-8) yalnızca `lvntr-starter-kit` core'da; yukarıdakiler bu skill'in frontend özet kuralları (core #4/#5 + SkForm/useApi frontend eklemeleri).

---

## Triggers

Bu path veya sembollerden herhangi biri görünüyorsa skill aktiftir:

- `resources/js/pages/Admin/**`
- `@lvntr/components/*` — `SkForm`, `SkDatatable`, `SkTabs`, `AppDialog`, `AvatarUpload`, `PageLoading`
- `FB`, `DB`, `TB` builder nesneleri
- `useDialog`, `useConfirm`, `useApi`, `useDefinition`, `useRefreshBus`, `useCan`, `useFlash`, `useSidebar`, `useDarkMode`, `usePageLoading`
- `refreshKey`, `dtApi`, `definitionOptions`, `inDialog`

---

## §1 — FormBuilder (FB)

```ts
import { FB } from '@lvntr/components/FormBuilder/core';
import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';

const formConfig = computed(() =>
  FB.form()
    .cols(2)
    .submit({ url: products.store.url(), method: 'post' })   // OR .resource({ store, update, data, key, id? })
    .inDialog(true)                                          // dialog mode → cancel emit zorlar
    .addFields(
      FB.inputText().key('name'),
      FB.inputNumber().key('price').min(0).fractionDigits(2, 2).suffix('₺'),
      FB.select().key('status').definitionOptions('productStatus'),
      FB.textarea().key('description').optional().class('col-span-full'),
      FB.fileUpload().key('image').accept('image/*').existingMediaKey('image'),
    )
    .build()
);
```

```vue
<SkForm :config="formConfig" @success="onSuccess" @cancel="onCancel" />
```

### Field tipleri

`inputText`, `inputNumber`, `inputOtp`, `inputMask`, `datePicker`,
`select`, `multiselect`, `radio`, `selectButton`, `checkbox`, `checkboxGroup`,
`password`, `textarea`, `toggleButton`, `toggleSwitch`, `fileUpload`,
`colorSelector`, `title`, `slot`

### Her field için ortak chainable'lar

`.key(req)`, `.label(s|false)`, `.required(b)`, `.optional()`, `.hint(s)`,
`.visible(fn)`, `.disabled(fn)`, `.default(v)`, `.props({...})`, `.class(css)`,
`.hidden(b)`, `.groupPrefix(s)`, `.groupSuffix(s)`, `.controlPosition('left'|'right')`

Seçenek barındıran field'lar: `.optionsUrl(url|fn)` (fn formu cascading select'te reaktif çalışır)
veya `.definitionOptions('key', { only?, except? })`.

`.label()` atlanırsa label `sk-attribute.attributes.{key}`'den otomatik çözümlenir —
hardcode string yerine `lang/{locale}/sk-attribute.php`'ye ekle.

---

## §2 — DatatableBuilder (DB)

```ts
import { DB } from '@lvntr/components/DatatableBuilder/core';
import SkDatatable from '@lvntr/components/DatatableBuilder/SkDatatable.vue';

const tableConfig = DB.table<Product>()
  .route(products.dtApi.url())
  .addColumns(
    DB.column<Product>().key('name').label('sk-product.name'),
    DB.column<Product>().key('price').render((row, escape) =>
      `<b>${escape(String(row.price))} ₺</b>`),          // escape: XSS önlemi
    DB.column<Product>().key('status').tag('definition').tagKey('productStatus').tagOutlined(),
  )
  .addFilters(
    DB.filter().key('status').definitionOptions('productStatus'),
  )
  .addActions(
    DB.action<Product>()
      .icon('pi pi-pencil').severity('warn').label('sk-button.edit')
      .visible(() => can('products.update'))
      .handle((p) => openEdit(p.id)),
    DB.action<Product>()
      .icon('pi pi-trash').severity('danger').label('sk-button.delete')
      .visible(() => can('products.delete'))
      .handle((p) => deleteProduct(p)),
  )
  .build();
```

```vue
<SkDatatable :config="tableConfig" refreshKey="products-table" />
```

Backend endpoint `ProductDatatableQuery::response()` → `DatatableQueryBuilder` zincirinden dönmeli.
`SkDatatable` tam olarak `DataTableResponse<T>` şeklini bekler — başka shape çalışmaz.

`tag('definition')` eşleşen definition'ın `severity`'siyle otomatik renklendirir.
`.render()` içinde **daima** `escape` callback kullan — XSS önlemi.

---

## §3 — TabBuilder (TB)

```ts
import { TB } from '@lvntr/components/TabBuilder/core';
import SkTabs from '@lvntr/components/TabBuilder/SkTabs.vue';

const tabConfig = TB.tabs()
  .vertical()
  .addTabs(
    TB.item().key('general').label('sk-setting.tabs.general').icon('pi pi-cog'),
    TB.item().key('auth').label('sk-setting.tabs.auth').icon('pi pi-shield')
      .permission('settings.update', 'settings.read'),
  )
  .build();
```

```vue
<SkTabs :config="tabConfig">
  <template #general><GeneralTab /></template>
  <template #auth><AuthTab /></template>
</SkTabs>
```

Slot adı `tab.key` ile birebir eşleşmelidir. Aktif tab URL'ye `queryParam` (default `tab`) üzerinden senkronize edilir. Permission/role gating tab'ı tamamen gizler — disabled değil.

---

## §4 — Composables

`resources/js/composables/` altında, `@/composables`'dan re-export edilir.

| Composable | Kısa açıklama |
|---|---|
| `useDialog()` | `dialog.open(Component, props, title, opts)`, `dialog.openAsync(Component, dataUrl, title, opts)`, `dialog.close()`. Opts içinde `refreshKey` geçilirse kayıt başarısında tablo otomatik refresh olur. **PrimeVue Dialog doğrudan import edilmez.** |
| `useConfirm()` | `confirmDelete(cb, message?, icon?)` ve `confirmAction({...})` — `AdminLayout.vue`'da `<ConfirmDialog group="app" />` zaten monte. **Native `confirm()`/`alert()` kullanılmaz.** |
| `useApi()` | CSRF-aware HTTP client. `api.get<T>(url)`, `.post`, `.put`, `.patch`, `.delete`. Inertia visit'in yetmediği durumlarda kullan (autocomplete, file upload). `useApi({ toast: false })` hata toast'ını kapatır. |
| `useDefinition()` | `/definitions` endpoint'inden cache'li enum'lar. `await load(['userStatus'])`, sonra `list(key)`, `options(key)`, `find(key, value)`. `.definitionOptions(key)` dolaylı olarak kullanır. |
| `useRefreshBus()` | Bileşenler arası refresh. Tablolar `refreshKey` ile register olur; mutasyonlar `bus.refresh('o-key')` çağırır. `useDialog({ refreshKey })` bunu otomatik wire eder. |
| `useCan()` | `can(perm)`, `canAny([perms])`, `hasRole(role)` — Inertia shared props'tan gelir. |
| `useFlash()` | Reaktif Inertia flash: `flash.value = { success?, error?, warning?, info?, status? }`. |
| `useSidebar()` | `{ isCollapsed, isMobileOpen, isMobile, toggle, openMobile, closeMobile }`. |
| `useDarkMode()` | `{ isDark, toggleDark }` — `<html>`'e `.dark` toggle, localStorage'a persist. |
| `usePageLoading(delay = 150)` | `{ isLoading, isNavigating }` — anti-flicker gecikmeli Inertia navigasyon flag'leri. |
| `useUrlTab(tabs, paramName?)` | Manuel URL↔tab senkronu. TabBuilder bunu içeride yapar; yalnızca özel (custom) tab'lar için gerekir. |

---

## §5 — Frontend recipe (entity eklerken adımlar 14-16)

Tam adım sırası `lvntr-starter-kit` §4'tedir. Aşağıdakiler yalnızca frontend kısmıdır.

**Adım 14 — Index.vue**

```vue
<!-- resources/js/pages/Admin/Products/Index.vue -->
<script setup lang="ts">
import { DB } from '@lvntr/components/DatatableBuilder/core';
import SkDatatable from '@lvntr/components/DatatableBuilder/SkDatatable.vue';
import { useDialog } from '@/composables/useDialog';
import { useCan } from '@/composables/useCan';
import { products } from '@/routes/products';
import ProductForm from './components/ProductForm.vue';

const { can } = useCan();
const dialog = useDialog();

const tableConfig = DB.table<Product>()
  .route(products.dtApi.url())
  .addColumns(/* ... */)
  .addActions(
    DB.action<Product>()
      .icon('pi pi-pencil').severity('warn').label('sk-button.edit')
      .visible(() => can('products.update'))
      .handle((p) => dialog.open(ProductForm, { id: p.id, inDialog: true }, 'Ürün Düzenle', { refreshKey: 'products-table' })),
  )
  .build();
</script>

<template>
  <SkDatatable :config="tableConfig" refreshKey="products-table" />
  <Button v-can="'products.create'" @click="dialog.open(ProductForm, { inDialog: true }, 'Yeni Ürün', { refreshKey: 'products-table' })" />
</template>
```

**Adım 15 — ProductForm.vue**

```vue
<!-- resources/js/pages/Admin/Products/components/ProductForm.vue -->
<script setup lang="ts">
import { FB } from '@lvntr/components/FormBuilder/core';
import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
import { products } from '@/routes/products';

const props = defineProps<{ id?: string; inDialog?: boolean }>();

const formConfig = computed(() =>
  FB.form()
    .cols(2)
    .inDialog(props.inDialog ?? false)
    .resource({ store: products.store.url(), update: products.update.url(), data: products.show.url(), key: 'product', id: props.id })
    .addFields(
      FB.inputText().key('name'),
      FB.inputNumber().key('price').min(0).fractionDigits(2, 2).suffix('₺'),
      FB.select().key('status').definitionOptions('productStatus'),
    )
    .build()
);
</script>

<template>
  <SkForm :config="formConfig" @success="$emit('success')" @cancel="$emit('cancel')" />
</template>
```

**Adım 16 — Dialog wiring ve refresh**

```ts
// Dialog açmak
dialog.open(ProductForm, { inDialog: true }, 'Yeni Ürün', { refreshKey: 'products-table' });

// Async data ile dialog (edit flow)
dialog.openAsync(ProductForm, products.show.url(id), 'Ürün Düzenle', { refreshKey: 'products-table' });

// Manuel refresh (dialog dışı mutasyon sonrası)
const bus = useRefreshBus();
bus.refresh('products-table');
```

---

## §6 — Frontend pitfall'lar

1. **URL hardcode** — `fetch('/api/products')` Wayfinder typing'i kırar. `@/routes/products`'tan import et, `.url()` çağır. Route değişince `php artisan wayfinder:generate` unut.
2. **Custom datatable shape** — `SkDatatable` tam olarak `DataTableResponse<T>` bekler. Backend endpoint her zaman `DatatableQueryBuilder` zincirinden geçmelidir; elle şekillendirilmiş response çalışmaz.
3. **Hardcode label** — `.label('Ürün Adı')` yerine çeviri anahtarı kullan (`'sk-product.name'`). Eksik anahtar `sk-attribute.attributes.{key}`'den otomatik çözümlenir.
4. **Eksik `refreshKey`** — Mutasyon dialog'u olan her tabloda `<SkDatatable refreshKey="...">` VE `useDialog({ refreshKey: '...' })` birlikte set edilmeli. Birini atlarsan tablo kayıt sonrası refresh olmaz.
5. **`wayfinder:generate` atlamak** — Route eklendikten veya değiştirildikten sonra generate çalıştırılmazsa Vue import'ları bozulur ya da eski URL'ye düşer.

---

## Hard rule hatırlatma (bu skill doğrudan tetiklendiğinde)

Tam liste `lvntr-starter-kit` §1'de (8 kural). Frontend için kritik olan #1, #2, #4, #5:

- **#1** `vendor/lvntr/laravel-starter-kit/` düzenleme yok
- **#2** Auto-generated düzenleme yok (`wayfinder/routes/actions`, `*.d.ts`, `_ide_helper*`, `.phpstorm.meta.php`)
- **#4** `useDialog()`/`useConfirm()` bypass yok (doğrudan PrimeVue `Dialog`, native `confirm()/alert()` yok)
- **#5** Vue'da URL hardcode yok → `@/routes`/`@/actions` `.url()` + `wayfinder:generate`

---

## Cross-ref

`lvntr-starter-kit` ile birlikte çalışır (core: tüm 8 hard rule, proje şekli, komut referansı, permissions, i18n).
Aynı entity'nin backend'i (Action / DTO / API / route) → `lvntr-kit-domain`.

---

## Bottom Line

Form için `FB` + `SkForm`, tablo için `DB` + `SkDatatable`, tab için `TB` + `SkTabs`.
Dialog `useDialog()`, onay `useConfirm()`, HTTP `useApi()`, refresh `useRefreshBus()`.
URL hardcode yok; `@/routes/**` + `.url()`.
