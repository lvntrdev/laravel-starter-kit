# UI Bileşenleri

Starter kit, PrimeVue üzerine kurulu tekrar kullanılabilir bir UI yardımcı seti sunar. Tutarlı modal davranışı, avatar yükleme, dosya önizleme, tag gösterimi, merkezi toast render yapısı ve loading state'lerini kapsar.

## Dahil Bileşenler

- `AppDialog`
- `AvatarUpload`
- `SkImageUpload`
- `ImageLightbox`
- `FilePreviewModal`
- `SkCard`
- PrimeVue `Tag` (SK temalı)
- PrimeVue `Button` (SK temalı, genişletilmiş severity)
- PrimeVue `Message` / `InlineMessage` (SK temalı, genişletilmiş severity)
- `ToastComponent` (SK özel toast renderer'ı)
- `ConfirmDialogComponent`
- `MimePickerField`
- `ToggleFeatureCard`
- `SkPageLoader`
- `TurnstileWidget`
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

Tüm varyantlar light ve dark mod için temalıdır. Her varyant ve renk için **Bileşenler → Tag** showcase sayfasına (`/sk-components`) bak.

## Button (PrimeVue)

PrimeVue `Button` auto-import'tur ve `severity` prop'u 8 yerleşik PrimeVue değerini, Tailwind renk ailelerini ve SK özel renklerini — Tag ile aynı genişletilmiş paleti — kabul eder. Tema, PrimeVue'nun button root'una eklediği `data-p-severity` attribute'unu hedefler.

Yerleşik severity'ler (8): `primary` (prop yok, varsayılan), `secondary`, `success`, `info`, `warn`, `help`, `danger`, `contrast`.

Genişletilmiş renk değerleri (Tag ile aynı Tailwind + SK aileleri): `red`, `orange`, `amber`, `yellow`, `lime`, `green`, `emerald`, `teal`, `cyan`, `sky`, `blue`, `indigo`, `violet`, `purple`, `fuchsia`, `pink`, `rose`, `slate`, `gray`, `zinc`, `neutral`, `stone`, `mauve`, `olive`, `mist`, `taupe`.

```vue
<template>
    <Button label="Kaydet" />
    <Button label="Sil" severity="danger" outlined />
    <Button label="Onayla" severity="emerald" />
    <Button label="Etiket" severity="indigo" rounded />
    <Button icon="pi pi-cog" severity="secondary" text />
</template>
```

Varyantlar standart PrimeVue button prop'larıdır: `outlined`, `text`, `raised`, `rounded`, `size`, `loading`, `disabled`. Renk varyantları için ek `class` gerekmez — severity tek başına tüm rengi yönetir.

Yıkıcı aksiyonlar için her zaman `severity="danger"` kullanılır (önem derecesine göre `outlined` veya dolu). Renk başına tam matris için **Bileşenler → Button** showcase sayfasına (`/sk-components`) bak.

## Message ve InlineMessage (PrimeVue)

PrimeVue `Message` ve `InlineMessage` auto-import'tur ve SK paletine göre yeniden boyalıdır. `severity` prop'u Button ve Tag ile aynı genişletilmiş renk setini kabul eder. Tema, `Message`'da `data-p` attribute'unu, `InlineMessage`'da ise `p-inlinemessage-<severity>` sınıfını hedefler.

Yerleşik severity'ler (6): `success`, `info`, `warn`, `danger`, `secondary`, `contrast`.

Genişletilmiş renk değerleri: tüm Tailwind aileleri ve SK özel aileleri (Button/Tag listesiyle aynı).

`Message` dört düzen varyantını destekler:

- **accent** (varsayılan) — tonlu sol-kenarlı banner; kalıcı bildirimler için `:closable="false"` kullan
- **fill** — düz renk dolu banner; `class="p-message-fill"` ile opt-in
- **outlined** — yalnızca kenarlık; `variant="outlined"` ile opt-in
- **simple** — minimal, kenarlık veya arka plan yok; `variant="simple"` ile opt-in

```vue
<template>
    <!-- accent (varsayılan) -->
    <Message severity="info" :closable="false">Değişiklikler kaydedildi.</Message>

    <!-- fill varyantı -->
    <Message severity="success" icon="pi pi-check-circle" class="p-message-fill">
        <div class="font-semibold">Yayınlandı</div>
        <div class="text-[12.5px] opacity-80">Tüm kullanıcılar bu gönderiyi görebilir.</div>
    </Message>

    <!-- outlined varyantı -->
    <Message severity="warn" variant="outlined" :closable="false">İnceleme gerekli.</Message>

    <!-- InlineMessage — satır içi, kapatma yok -->
    <InlineMessage severity="danger">Alan zorunludur.</InlineMessage>

    <!-- Genişletilmiş renk -->
    <Message severity="indigo">Özel severity.</Message>
</template>
```

Her varyant ve renk için **Bileşenler → Message** showcase sayfasına (`/sk-components`) bak.

## Toast (SK özel renderer)

`ToastComponent`, PrimeVue'nun `<Toast>`'ını tamamen özel bir `#container` template'iyle sarar. Template özel olduğu için PrimeVue'nun native severity markup'ı uygulanmaz — stil tümüyle `theme/main/components/toast.css` içindeki `.sk-toast-<severity>` CSS sınıfları tarafından yönetilir. Tüm `toast.add()` çağrıları PrimeVue'dan `useToast()` üzerinden yapılır.

Yerleşik severity'ler: `success`, `info`, `warn`, `error`, `secondary`, `contrast`.

Genişletilmiş renk değerleri: tüm Tailwind aileleri ve SK özel aileleri (Button/Tag listesiyle aynı). Herhangi bir aile adını `severity` olarak ver; toast eşleşen `sk-toast-<name>` renk token'ını alır.

Varyantlar (`styleClass` ile opt-in):

- Varsayılan (accent) — tonlu arka plan, renkli accent çizgisi, progress bar
- `sk-toast-outlined` — yalnızca kenarlıklı kabuk
- `sk-toast-solid` — tamamen dolu, ters metin

Standart `ToastMessageOptions`'a ek seçenekler:

- `icon` — PrimeVue ikon sınıfı (ör. `'pi pi-check-circle'`); atlanırsa severity'ye göre varsayılan
- `styleClass` — varyant sınıfı (`'sk-toast-solid'` / `'sk-toast-outlined'`)
- `actions` — hap buton dizisi: `{ label: string; command?: () => void; primary?: boolean; dismiss?: boolean }`

```ts
import { useToast } from 'primevue/usetoast';

const toast = useToast();

// Basit info toast
toast.add({
    severity: 'info',
    summary: 'Kaydedildi',
    detail: 'Değişiklikler uygulandı.',
    group: 'bc',
    life: 4000,
});

// Özel ikon + solid varyant
toast.add({
    severity: 'success',
    summary: 'Yayınlandı',
    icon: 'pi pi-globe',
    styleClass: 'sk-toast-solid',
    group: 'bc',
    life: 3000,
});

// Aksiyonlu butonlar (yapışkan — life yok)
toast.add({
    severity: 'warn',
    summary: 'Öğe silinsin mi?',
    detail: 'Bu işlem geri alınamaz.',
    icon: 'pi pi-exclamation-triangle',
    group: 'bc',
    actions: [
        { label: 'Sil', primary: true, command: () => doDelete() },
        { label: 'İptal' },
    ],
});
```

`group: 'bc'` zorunludur — `ToastComponent` `bc` grubuna kayıtlıdır. Canlı örnekler için **Bileşenler → Toast** showcase sayfasına (`/sk-components`) bak.

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

## SkImageUpload

`SkImageUpload`, ayarlar ekranı tarzı marka görselleri (logo, favicon) için genel amaçlı bir görsel yükleme alanıdır. `AvatarUpload`'un iyimser önizleme desenini — anlık `FileReader` önizleme, `fetch` yükleme ve başarı sonrası Inertia kısmi reload — yansıtır; dikdörtgen ve avatar dışı bağlamlar için tasarlanmıştır.

```vue
<SkImageUpload
    :preview-url="settings.logo_url"
    upload-url="/admin/settings/logo"
    field-name="logo"
    response-key="logo_url"
    accept="image/png,image/svg+xml"
    label="Açık tema logosu"
    hint="Önerilen: 300×80 px, PNG veya SVG"
    upload-label="Yükle"
    remove-label="Kaldır"
    remove-confirm="Logo kaldırılsın mı?"
    variant="logo-light"
    layout="stacked"
    :reload-only="['settings']"
/>
```

Props:

- `previewUrl?: string | null` — sunucudan gelen URL; `null` olduğunda yer tutucu ikon gösterilir
- `uploadUrl: string` — hem POST (yükleme) hem DELETE (kaldırma) uç noktası
- `fieldName: string` — dosya için FormData anahtarı (ör. `logo`, `favicon`)
- `responseKey: string` — yükleme sonrasında `json.data` altında yeni URL'yi taşıyan anahtar
- `accept: string` — `<input accept>` değeri
- `label: string`, `hint: string` — önceden çevrilmiş görüntü metinleri
- `uploadLabel: string`, `removeLabel: string`, `removeConfirm: string` — önceden çevrilmiş buton/onay metinleri
- `variant?: 'logo-light' | 'logo-dark' | 'favicon'` (varsayılan `'logo-light'`) — önizleme kutusu stili; `logo-dark` koyu arka plan kullanır; `favicon` kare kutu render eder
- `layout?: 'stacked' | 'row'` (varsayılan `'row'`) — `stacked` önizleme ve butonları dikey yığar (logo grid'leri için); `row` her şeyi aynı satıra koyar
- `reloadOnly?: string[]` (varsayılan `['settings']`) — Inertia kısmi reload prop anahtarları

## MimePickerField

`MimePickerField`, File Manager ayarlarında kullanılan onay-kutusu tabanlı MIME türü seçicisidir. Kategorileri (Görseller, Belgeler, Arşiv) etiketli onay kutularıyla render eder ve seçili MIME dizgilerinin `string[]` olarak emit eder.

```vue
<MimePickerField v-model="settings.allowed_mimes" />
```

Props:

- `modelValue?: string[] | null` — halihazırda seçili MIME türleri
- `categories?: MimeCategory[]` — varsayılan kategori listesini geçersiz kıl (Görseller / Belgeler / Arşiv). Her giriş: `{ titleKey: string; options: { label: string; value: string; icon: string }[] }`

Varsayılan MIME grupları: JPEG, PNG, GIF, WebP (görseller); PDF, DOC/DOCX, XLS/XLSX, düz metin, CSV (belgeler); ZIP (arşiv).

## ToggleFeatureCard

`ToggleFeatureCard`, entegre bir toggle switch ile birlikte gelen stillendirilmiş bir kart satırıdır; özellik bayrağı ayar ekranlarında kullanılır (ör. File Manager modüllerini etkinleştirme/devre dışı bırakma).

```vue
<ToggleFeatureCard
    v-model="settings.share_enabled"
    label="Paylaşım linkleri"
    description="Kullanıcıların dosyalar için herkese açık paylaşım linki oluşturmasına izin ver."
    icon="pi-share-alt"
/>
```

Props:

- `modelValue?: boolean` (varsayılan `false`) — bağlı toggle durumu
- `label: string` — özellik adı, kalın yazı
- `description?: string` — etikein altında ikincil metin
- `icon?: string` — PrimeVue ikon adı (`pi ` öneki olmadan, ör. `'pi-share-alt'`); verildiğinde solda tonlu bir ikon rozeti render edilir

Toggle açık olduğunda kart kenarlığı ve arka planı primary-tonlu renge geçer.

## SkPageLoader

`SkPageLoader`, Inertia sayfa geçişleri sırasında görünen tam ekran animasyonlu bir loading overlay'idir. Varsayılan NProgress çubuğunun yerini animasyonlu radyal ızgara arka planı ve aşamalı harf-dalgası sözcüğüyle alır. Animasyon ve temalama `theme/main/components/page-loader.css` içinde tanımlıdır.

**Opt-in'dir** — gelen scaffold onu mount etmez. Açmak için `AdminLayout.vue`'nun `overlays` slot'una, diğer global overlay'lerin yanına `<SkPageLoader/>` ekleyin ve bileşen kütüphanesinden import edin:

```vue
import SkPageLoader from '@lvntr/components/ui/SkPageLoader.vue';

<template #overlays>
    <ConfirmDialogComponent />
    <ToastComponent />
    <AppDialog />
    <ImageLightbox />
    <SkPageLoader :delay="250" />
</template>
```

Props:

- `delay?: number` (varsayılan `250`) — overlay'in görünmeden önceki bekleme süresi (ms); anlık geçişlerde yanıp sönmeyi önler

`SkPageLoader` içeride `usePageLoading()` kullanır ve animasyonlu sözcük için `sk-layout.loading` çevirisini okur.

## TurnstileWidget

`TurnstileWidget`, bir Cloudflare Turnstile doğrulama widget'ı gömer. Kit'in auth sayfaları (giriş, kayıt, şifre sıfırlama) tarafından kullanılır; bot koruması gerektiren herhangi bir forma da eklenebilir.

Tam yapılandırma ve backend kurulumu için [Kimlik Doğrulama dökümanı](auth.md)'na bakın.

```vue
<TurnstileWidget v-model="form.turnstile_token" />
```

Props:

- `modelValue: string` — başarılı doğrulama sonrası emit edilen Turnstile token'ı. Formunuzun token alanına bağlayın ve gönderim yüküne ekleyin.

`defineExpose` ile açıklanır:

- `reset()` — widget'ı sıfırlar (başarısız form gönderimi sonrası yararlıdır)
- `loadFailed: Ref<boolean>` — Cloudflare scripti yüklenemezse `true`

Widget yalnızca `page.props.turnstile.enabled` `true` olduğunda render edilir. Turnstile uygulama ayarlarından devre dışıysa bileşen hiçbir şey render etmez. Cloudflare scripti mount'ta tembel yüklenir ve aynı sayfadaki widget örnekleri arasında paylaşılır.

## Öneri

Yeni yerel varyantlar üretmeden önce bu bileşenleri tekrar kullanmayı tercih edin. Bu yaklaşım admin panelini görsel olarak tutarlı tutar ve ileride kit güncellemelerini kolaylaştırır.
