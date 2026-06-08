# UI Bileşenleri

Starter kit, PrimeVue üzerine kurulu tekrar kullanılabilir bir UI yardımcı seti sunar. Tutarlı modal davranışı, avatar yükleme, dosya önizleme, tag gösterimi, merkezi toast render yapısı ve loading state'lerini kapsar.

## Dahil Bileşenler

- `AppDialog`
- `AvatarUpload`
- `ImageLightbox`
- `FilePreviewModal`
- `SkCard`
- PrimeVue `Tag` (SK temalı)
- `ConfirmDialogComponent`
- `ToastComponent`
- skeleton yardımcıları: `PageLoading`, `SkeletonBox`, `SkeletonCard`, `SkeletonTable`, `SkeletonText`
- medya odaklı akışlar için `FileManager` bileşeni

## Global Overlay'ler

`AdminLayout.vue`, admin alanı için şu bileşenleri bir kez render eder:

- `@lvntr/components/ui/ConfirmDialogComponent.vue`
- `@lvntr/components/ui/ToastComponent.vue`
- `@lvntr/components/ui/AppDialog.vue`
- `@lvntr/components/ui/ImageLightbox.vue`

Bu bileşenler her sayfada yeniden yazılmak yerine composable ve sayfa mantığı üzerinden tetiklenir.

## ImageLightbox ve FilePreviewModal

Dosya önizlemeleri artık iki farklı UI yoluyla açılır:

- tam ekran görsel görüntüleme için `ImageLightbox`
- PDF, video, ses, metin ve diğer resim olmayan önizlemeler için `FilePreviewModal`

`ImageLightbox`, `useImageLightbox()` üzerinden; `FilePreviewModal` ise `useDialog()` üzerinden açılır.

## AppDialog ve useDialog()

`AppDialog`, `useDialog()` ile birlikte çalışarak dinamik Vue bileşenlerini tek bir ortak dialog içinde gösteren yapıdır.

```ts
import { useDialog } from '@/composables/useDialog';
import UserForm from '@/pages/Admin/Users/components/UserForm.vue';

const dialog = useDialog();

dialog.open(UserForm, { inDialog: true }, 'Kullanıcıyı düzenle', {
    refreshKey: 'users-table',
    width: '640px',
});
```

### Async mod

Bileşenin sunucudan veri çekildikten sonra render edilmesi gerektiğinde `openAsync` kullanılır:

```ts
await dialog.openAsync(UserForm, '/roles/1/data', 'Kaydı düzenle', {
    refreshKey: 'users-table',
    mapResponse: (data) => ({ role: data }),
});
```

## ConfirmDialogComponent ve useConfirm()

Onay dialog'u global olarak bir kez mount edilir ve sayfalar sadece `useConfirm()` çağırır.

```ts
import { router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';

const { confirmDelete } = useConfirm();

confirmDelete(() => {
    router.delete('/users/1');
});
```

## ToastComponent

`ToastComponent`, ortak PrimeVue toast container'ıdır. Bu projede genellikle şu kaynaklardan beslenir:

- `AdminLayout.vue` içindeki flash mesajları
- `useApi()` hata yönetimi

## SkCard

`SkCard`, `SkForm` tarafından kullanılan (ve `SkDatatable` ile sayfa düzeyi card'lar için de düşünülen) PrimeVue Card etrafındaki paylaşımlı wrapper'dır. Tek ve tutarlı bir caption başlığı garanti eder: başlık metni solda, opsiyonel `#title-end` slot'u sağda (action button, badge, durum göstergesi için), alt başlık hemen altta ve tüm caption bloğunu içerikten ayıran alt çizgi.

```vue
<script setup lang="ts">
    import SkCard from '@lvntr/components/ui/SkCard.vue';
</script>

<template>
    <SkCard title="Kullanıcılar" subtitle="Aktif hesaplar">
        <template #title-end>
            <Button icon="pi pi-plus" :label="$t('users.create')" @click="open" />
        </template>

        <SkDatatable :config="config" />
    </SkCard>

    <!-- şeffaf kabuk — arka plan, gölge, padding veya divider görseli yok -->
    <SkCard transparent>
        <p>İçerik buraya gelir.</p>
    </SkCard>

    <!-- caption sırf dekoratifse divider'ı kapat -->
    <SkCard title="Notlar" :divider="false">
        <p>Kısa not.</p>
    </SkCard>
</template>
```

Props:

- `title?: string` — `#title` slot'u için kısa yol. Çevrilmiş string verin (içeride `$t` çağrılmaz).
- `subtitle?: string` — `#subtitle` slot'u için kısa yol.
- `transparent?: boolean` (varsayılan `false`) — `true` olduğunda card arka plan, kenarlık, gölge veya padding olmadan render edilir. Dialog içinde veya görünmez gruplama wrapper'ı olarak kullanışlıdır.
- `divider?: boolean` (varsayılan `true`) — caption bloğunun (title + subtitle) altına alt çizgi çizer; başlık içerikten net olarak ayrılır. Tema: `--p-surface-200` (light) / `--p-surface-700` (dark).
- `pt?: Record<string, any>` — ek PrimeVue Card passthrough. Dahili pt ile merge edilir; çakışmada consumer key'leri kazanır.

Slot'lar:

- `header`, `title`, `subtitle`, `content`, `footer` — PrimeVue Card'a passthrough. Default slot da `content`'e map'lenir, yani `<SkCard>…</SkCard>` `<SkCard><template #content>…</template></SkCard>` ile aynıdır.
- `title-end` — başlığın sağına, aynı flex satırında render edilir.

Notlar:

- `SkCard` içeride `inheritAttrs: false`'dir ama `useAttrs` ile dış `class` fallthrough'unu Card root'una iletir; böylece `<SkCard class="my-cls">` beklendiği gibi çalışır (aksi halde PrimeVue Card'ın kendi `inheritAttrs: false`'i class düşmesini engellerdi).
- Divider yalnızca caption mevcutsa ve `divider` `true` ise (varsayılan) çizilir. Title ve subtitle olmayan bir card'da çizgi olmaz.

## Tag (PrimeVue)

Kit, PrimeVue'nun `<Tag>` bileşenini standart alır ve SK paletine göre yeniden boyar. Auto-import'tur (ayrıca import gerekmez) ve `severity` hem 6 PrimeVue severity'sini hem de desteklenen SK palet adlarını kabul eder — yani bileşeni patch'lemeden tüm palete erişirsin (tema, PrimeVue'nun yaydığı `data-p` attribute'unu hedefler). `SkDatatable` tag kolonları da aynı `<Tag>` üzerinden render edilir.

```vue
<template>
    <Tag value="Active" severity="success" />
    <Tag value="Pending" severity="amber" rounded class="p-tag-soft" />
    <Tag value="Verified" icon="pi pi-check" severity="emerald" class="p-tag-outlined" />
    <Tag value="Indigo" severity="indigo" />
</template>
```

Severity değerleri:

- 6 yerleşik: `success`, `info`, `warn`, `danger`, `secondary`, `contrast`
- Tailwind aileleri: `red`, `orange`, `amber`, `yellow`, `lime`, `green`, `emerald`, `teal`, `cyan`, `sky`, `blue`, `indigo`, `violet`, `purple`, `fuchsia`, `pink`, `rose`, `slate`, `gray`, `zinc`, `neutral`, `stone`
- SK özel aileleri: `mauve`, `olive`, `mist`, `taupe`

Varyantlar — PrimeVue Tag'in varyant prop'u olmadığından `class` ile opt-in:

- `p-tag-soft` — daha açık, tonlu dolgu
- `p-tag-outlined` — yalnızca kenarlık
- `p-tag-dot` — baştaki durum noktası
- `p-tag-sm` / `p-tag-lg` — boyutlar
- `rounded` (native prop) — hap (pill) şekli

Tüm varyantlar light ve dark mod için temalıdır. Her varyant ve renk için **Bileşenler → Tag** showcase sayfasına (`/components`) bak.

## AvatarUpload

Avatar yükleme için hazır bir görsel seçici/yükleyici bileşenidir.

```vue
<AvatarUpload :avatar-url="user.avatar_url" upload-url="/user/avatar" delete-url="/user/avatar" />
```

Dahili davranışlar:

- `FileReader` ile anlık önizleme
- `fetch` ile yükleme
- `useConfirm()` ile silme onayı
- başarılı yükleme veya silme sonrası Inertia reload

## PageLoading

`PageLoading.vue`, Inertia geçişleri sırasında skeleton overlay gösterir.

```vue
<PageLoading>
    <template #skeleton>
        <SkeletonTable :rows="6" :columns="4" />
    </template>

    <YourPageContent />
</PageLoading>
```

`@lvntr/components/Skeleton/` altında kullanılabilen diğer skeleton yardımcıları:

- `SkeletonBox` — genel amaçlı placeholder kutu
- `SkeletonCard` — kart şeklinde yüklenme bloğu
- `SkeletonTable` — yapılandırılabilir satır ve kolon sayısıyla tablo yüklenme bloğu
- `SkeletonText` — metin satırı placeholder'ları

## Öneri

Yeni yerel varyantlar üretmeden önce bu bileşenleri tekrar kullanmayı tercih edin. Bu yaklaşım admin panelini görsel olarak tutarlı tutar ve ileride kit güncellemelerini kolaylaştırır.
