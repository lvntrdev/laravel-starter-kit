# Tabs

Starter kit, çok bölümlü ekranları temiz tutmak için `SkTabs` ve fluent `TB` builder yapısını kullanır. Ayarlar, profil ve benzeri ekranlar zamanla çok parçalı hale gelir — tabs yapısı, sayfayı birçok farklı route'a bölmeden tek route içinde düzenli bir arayüz kurar.

## İmportlar

```ts
import { TB } from '@lvntr/components/TabBuilder/core';
import SkTabs from '@lvntr/components/TabBuilder/SkTabs.vue';
```

## Temel Örnek

```vue
<script setup lang="ts">
import { TB } from '@lvntr/components/TabBuilder/core';
import SkTabs from '@lvntr/components/TabBuilder/SkTabs.vue';

const tabConfig = TB.tabs()
    .queryParam('tab')
    .addTabs(
        TB.item().key('general').label('Genel').icon('pi pi-user'),
        TB.item().key('security').label('Güvenlik').icon('pi pi-shield'),
        TB.item().key('sessions').label('Oturumlar').icon('pi pi-desktop'),
    )
    .build();
</script>

<template>
    <SkTabs :config="tabConfig">
        <template #general>
            <p>Genel içerik</p>
        </template>

        <template #security>
            <p>Güvenlik içeriği</p>
        </template>

        <template #sessions>
            <p>Oturum içeriği</p>
        </template>
    </SkTabs>
</template>
```

## Tabs Builder API

- `layout('horizontal' | 'vertical')`
- `vertical()`
- `horizontal()`
- `queryParam(string)`
- `class(string)`
- `cardTitle(string)`
- `cardSubtitle(string)`
- `isCard(boolean)`
- `addTabs(...tabs)`

## Tab Item API

- `key(string)`
- `label(string)`
- `icon(string)`
- `description(string)` — label altında ikincil satır (yalnızca dikey düzen)
- `iconColor(color)` — renkli icon tile preset'i (yalnızca dikey düzen); varsayılan `slate`. Seçenekler: `blue`, `amber`, `emerald`, `purple`, `teal`, `red`, `rose`, `indigo`, `slate`, `pink`, `orange`, `cyan`, `green`, `yellow`
- `badge(value, severity?)` — sağ tarafta badge (metin veya sayı). Severity: `success` / `warn` / `info` / `danger` / `secondary` (varsayılan)
- `checked(value = true)` — sağ tarafta yeşil check işareti; `badge` üzerinde önceliklidir
- `permission(...permissions)` — kullanıcı verilen yetkilerden en az birine sahip değilse sekmeyi gizler (variadic; birden çok değerde OR — `canAny()` ile aynı mantık)
- `role(...roles)` — kullanıcı verilen rollerden en az birine sahip değilse sekmeyi gizler (variadic; birden çok değerde OR)
- `visible(boolean | () => boolean)`
- `disabled(boolean | () => boolean)`
- `isCard(boolean)`
- `cardTitle(string)`
- `cardSubtitle(string)`

```ts
TB.item().key('billing').label('Faturalama').permission('billing.view', 'billing.manage'),
TB.item().key('admin-tools').label('Yönetici Araçları').role('admin', 'superadmin'),
```

## Zengin Dikey Tab Görünümü

Dikey tab'lar daha zengin bir sidebar sunabilir — renkli icon tile, description satırı, trailing badge veya check işareti. Tab'lar seviyesindeki `.isCard(true)` ile sidebar PrimeVue Card içine sarılır:

```vue
<script setup lang="ts">
const tabConfig = TB.tabs()
    .vertical()
    .isCard(true)
    .addTabs(
        TB.item()
            .key('general')
            .label('Genel')
            .description('Uygulama adı, dil ve logo')
            .icon('pi pi-cog')
            .iconColor('blue'),
        TB.item()
            .key('mail')
            .label('E-posta')
            .description('SMTP ve gönderici ayarları')
            .icon('pi pi-envelope')
            .iconColor('emerald')
            .badge(3, 'warn'),
        TB.item()
            .key('storage')
            .label('Depolama')
            .description('S3, Spaces ve yerel disk')
            .icon('pi pi-database')
            .iconColor('purple')
            .checked(),
    )
    .build();
</script>
```

`description`, `iconColor`, `badge` ve `checked` yatay düzende yok sayılır.

## Yararlı Özellikler

- dikey veya yatay düzen
- icon tile, description, badge ve check işareti ile zengin dikey sidebar
- `useUrlTab()` ile query string senkronizasyonu
- role ve permission bazlı görünürlük
- sekme bazlı disabled mantığı
- hem sekme hem de konteyner seviyesinde başlık ve alt başlıkla opsiyonel card sarmalayıcı

## Dahili Davranışlar

`SkTabs` şu özellikleri hazır getirir:

- `useUrlTab()` ile query string senkronizasyonu
- dikey sidebar modu
- dikey düzende `sidebar-header` ve `sidebar-footer` slot'ları
- sekme anahtarına göre slot tabanlı içerik

Gerektiğinde parent bileşenler aktif sekmeye `defineExpose` üzerinden erişebilir.

## En Uygun Kullanım

- ayarlar ekranları
- profil ekranları
- mantıksal bölümlere ayrılmış uzun create/edit görünümleri
