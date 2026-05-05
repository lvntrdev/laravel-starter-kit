# UI Bileşenleri

Starter kit, PrimeVue üzerine kurulu tekrar kullanılabilir bir UI yardımcı seti sunar. Tutarlı modal davranışı, avatar yükleme, dosya önizleme, tag gösterimi, merkezi toast render yapısı ve loading state'lerini kapsar.

## Dahil Bileşenler

- `AppDialog`
- `AvatarUpload`
- `ImageLightbox`
- `FilePreviewModal`
- `SkTag`
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

## SkTag

`SkTag`, `SkDatatable` tarafında kullanılan ortak badge/tag bileşenidir. PrimeVue `Tag` yetersiz kaldığında özel hücrelerde de doğrudan kullanılabilir.

```vue
<script setup lang="ts">
    import SkTag from '@lvntr/components/ui/SkTag.vue';
</script>

<template>
    <SkTag value="Active" severity="success" />
    <SkTag value="Pending" color="amber" soft rounded />
    <SkTag value="Verified" icon="pi pi-check" color="emerald" outlined />
    <SkTag value="External" icon="pi pi-arrow-right" icon-pos="right" color="sky" />
</template>
```

Desteklenen prop'lar:

- `value`
- `icon`
- `iconPos` — `'left'` (varsayılan) veya `'right'`
- `severity` — PrimeVue benzeri severity veya Tailwind palette anahtarı
- `color` — Tailwind palette anahtarı, severity eşlemesini override eder
- `soft`
- `rounded`
- `outlined`

Davranış notları:

- `severity`, PrimeVue benzeri değerleri (`success`, `warn`, `danger`, `info`, `secondary`, `contrast`) ve `emerald`, `violet` gibi Tailwind renk anahtarlarını kabul eder
- tam bir palette seçmek istiyorsan `color`, severity eşlemesini override eder
- `soft`, `rounded` ve `outlined` serbestçe birlikte kullanılabilir
- ortak tema, solid, soft ve outlined varyantları birlikte yükler ve light/dark modda tutarlı görünüm sağlar

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
