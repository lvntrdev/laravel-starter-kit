# Tema Sistemi — Lvntr Starter Kit

Bu belge şunları kapsar:

- `AppShell` / `AdminLayout` kompozisyon modeli ve yeni layout kurma
- İki katmanlı tema modeli: runtime kit temaları (`main`, `aura`) ve build-zamanı custom slot-override'ları
- Custom temalar için build-zamanı resolver ve tam-değiştirme + fallback modeli
- Custom temalar için `VITE_SK_THEME` ile aktivasyon
- Özel tema ekleme veya tek bir bileşenin stilini değiştirme adım adım reçetesi

---

## Layout mimarisi

### AppShell — yapısal kabuk

`resources/js/layouts/AppShell.vue`, admin panelinin yapısal iskeletidir. Şunları sahiplenir:

- `.admin-layout` kökü ve `.admin-main` / `.admin-content` bölgeleri
- `useSidebar` aracılığıyla sidebar collapse ve mobil-açık durumu (tek sahip — alt bileşenlerde `useSidebar` tekrar import etmeyin)
- `.admin-main` üzerindeki responsive sınıf değiştiricileri (`admin-main--expanded`, `admin-main--collapsed`, `admin-main--mobile`)

`AppShell` beş adlandırılmış slot sunar:

| Slot | Alınan (scoped) | Amaç |
|---|---|---|
| `#sidebar` | `{ collapsed, mobileOpen, isMobile, closeMobile }` | Sidebar bölgesi |
| `#header` | `{ collapsed, isMobile, toggle }` | Üst başlık çubuğu |
| `default` | — | Sayfa içeriği (`.admin-content` sarmalı) |
| `#footer` | — | Alt çubuk |
| `#overlays` | — | Global overlay'ler (dialog, toast, confirm) |

`AppShell` sayfa prop'u, flash-to-toast köprüsü ve `<Head>` taşımaz. Yalnızca yapısal sarmalayıcıdır.

### AdminLayout — ince kompozisyon

`resources/js/layouts/AdminLayout.vue`, `AppShell`'i sarar ve her bölgeyi standart admin bileşenleriyle doldurur:

- `#sidebar` → `AdminSidebar`
- `#header` → `AdminHeader` (karanlık mod toggle'ını iletir)
- default → `AdminPageHeader` + `<slot />` (sayfa içeriği) + `<slot name="page-actions" />`
- `#footer` → `AdminFooter`
- `#overlays` → `ConfirmDialogComponent`, `ToastComponent`, `AppDialog`, `ImageLightbox`

Flash-to-toast köprüsünü (`router.on('finish', …)`) ve `<Head :title="title" />`'ı da yönetir.

**Dış kontrat (değişmedi):**

```vue
<AdminLayout title="Kullanıcılar" subtitle="Kullanıcı yönetimi" :back-url="route('admin.users.index')">
    <template #page-actions>
        <Button label="Yeni Kullanıcı" />
    </template>

    <!-- sayfa içeriği -->
</AdminLayout>
```

Prop'lar: `title?: string`, `subtitle?: string`, `backUrl?: string | boolean`.
Slot'lar: `default` (sayfa gövdesi), `page-actions` (`AdminPageHeader`'ın `#actions` slot'una iletilir).

Mevcut tüm sayfalar `@/layouts/AdminLayout.vue`'yu import etmeye devam eder — public kontrat birebir aynıdır.

### Yeni layout kurma

Kabuk yapısını paylaşan ama bölgeleri farklı düzenleyen bir layout oluşturmak için `AppShell`'i doğrudan kompozisyon yapın:

```vue
<!-- resources/js/layouts/MinimalLayout.vue -->
<script setup lang="ts">
    import AppShell from '@/layouts/AppShell.vue';
    import MinimalSidebar from '@/layouts/components/MinimalSidebar.vue';
    import MinimalHeader from '@/layouts/components/MinimalHeader.vue';
</script>

<template>
    <AppShell>
        <template #sidebar="{ collapsed, mobileOpen, isMobile, closeMobile }">
            <MinimalSidebar
                :collapsed="collapsed"
                :mobile-open="mobileOpen"
                :is-mobile="isMobile"
                @close-mobile="closeMobile"
            />
        </template>
        <template #header="{ collapsed, isMobile, toggle }">
            <MinimalHeader :collapsed="collapsed" :is-mobile="isMobile" @toggle-sidebar="toggle" />
        </template>
        <slot />
    </AppShell>
</template>
```

Yeni layout'u kullanan sayfalar onu yoluna göre import eder — `AdminLayout` sayfaları etkilenmez.

---

## İki stil katmanı

Tema sistemi iki bağımsız, tamamlayıcı katmana sahiptir.

| Katman | Temalar | Nasıl etkinleşir | Resolver | Artefakt |
|---|---|---|---|---|
| **Runtime kit temaları** | `main`, `aura` | `appearance.theme` DB ayarı (Ayarlar → Görünüm) — anında etkin, derleme gerekmez | `useTheme` composable'ı runtime'da `<html data-sk-theme>` yazar | Yok — CSS her zaman bundle'da |
| **Build-zamanı custom temalar** | Consumer'ın oluşturduğu `theme/<custom>/` dizinleri | `.env`'de `VITE_SK_THEME=custom` + `npm run build` | `sk-theme-build.mjs` (vendor-resident), `_active.css`'i üretir | Üretilen dosya (`_active.css`), gitignore'da |

Ek olarak, **PrimeVue preset katmanı** build zamanında `VITE_SK_THEME`'e bağlıdır:

| Katman | Ne kapsar | Resolver | Artefakt |
|---|---|---|---|
| **PrimeVue preset** | Birincil palet, yüzey renkleri, border radius, bileşen token'ları (`--p-*` değişkenleri) | `vite.config.ts`'teki alias `customResolver`'ı (`resolveActivePreset`) | Yok — saf JS modül çözümlemesi |

### Runtime kit temaları — `main` ve `aura`

`main` ve `aura` temaları her build'de **her zaman bundle'a dahil edilir**. Aralarında geçiş yapmak için derleme gerekmez; `useTheme` composable'ı `appearance.theme`'i paylaşılan Inertia prop'larından okur ve mount'ta ve her prop değişiminde `<html data-sk-theme="aura">` yazar (ya da `main` için attribute'u kaldırır).

- **`main`** — varsayılan. `<html>` üzerinde `data-sk-theme` attribute'u yoktur; tüm base kurallar geçerlidir.
- **`aura`** — `<html>` üzerinde `data-sk-theme="aura"` ayarlandığında etkinleşir. Aura'nın tüm kuralları `html[data-sk-theme='aura']`'ya scope'ludur; attribute yokken etkinsizdirler.

Admin panelindeki **Ayarlar → Görünüm** ekranından geçiş yapın; değişiklik anında geçerli olur ve admin genelinde kalıcıdır. Kullanıcı başına override yoktur.

### Build-zamanı custom temalar

Custom temalar `resources/css/theme/<isim>/` dizinlerinde yaşar ve tam-değiştirme + fallback slot modelini kullanır — aşağıdaki [CSS tema sistemi](#css-tema-sistemi) bölümüne bakın. Etkin olabilmeleri için `.env`'de `VITE_SK_THEME=<isim>` ve bir `npm run build` / `npm run dev` gereklidir. Ayarlar → Görünüm'de bir custom tema seçildiğinde arayüzde "derleme gerekir" notu gösterilir.

İki katman bağımsızdır: `VITE_SK_THEME=custom` (build-zamanı custom taban) kullanırken runtime attribute `main` ve `aura` görsel stilleri arasında geçiş yapabilir. Bir `tokens.css` override'ı genellikle preset'in emit ettiği `--p-*` token'larını okur — aşağıdaki [Bağımlılık zinciri](#bağımlılık-zinciri--tokenlar-ve-preset) bölümüne bakın.

---

## CSS tema sistemi

### Dizin yapısı

```
resources/css/
├── theme/
│   ├── theme.css              # Giriş noktası: _active.css'i ardından theme-runtime/aura/aura.css'i import eder
│   ├── _active.css            # OLUŞTURULAN — elle düzenleme; gitignore'da
│   ├── main/                  # Dahili ana tema (tüm slot'lar için kaynak)
│   │   ├── tokens.css         # CSS custom property'leri: layout ölçüleri, renkler, gölgeler
│   │   ├── fonts.css          # Web font bildirimleri
│   │   ├── _base.scss         # Base reset / tipografi
│   │   ├── layout/
│   │   │   ├── shell.css        # .admin-layout, .admin-main, Vue geçişleri
│   │   │   ├── sidebar.css      # .admin-sidebar*, .admin-overlay
│   │   │   ├── header.css       # .admin-header*
│   │   │   ├── page-header.css  # .admin-page-header*
│   │   │   └── footer.css       # .admin-footer*
│   │   ├── components/
│   │   │   ├── card.css
│   │   │   ├── button.css
│   │   │   ├── confirm.css
│   │   │   ├── datatable.css
│   │   │   ├── dialog.css
│   │   │   ├── editor.css
│   │   │   ├── formbuilder.css
│   │   │   ├── menus.css
│   │   │   ├── message.css
│   │   │   ├── navigation.css
│   │   │   ├── primevue.css
│   │   │   ├── tabs.css
│   │   │   ├── tag.css
│   │   │   └── toast.css
│   │   ├── _auth.scss         # Auth layout stilleri
│   │   └── utilities.css      # Tailwind utility override'ları
│   └── custom/                # Override tema — kit ile gönderilmez; siz oluşturursunuz
│       └── (gerektiğinde oluşturun — aşağıdaki reçetelere bakın)
└── theme-runtime/
    └── aura/                  # Runtime kit teması — her zaman bundle'da, html[data-sk-theme='aura']'ya scope'lu
        ├── aura.css           # Index: aşağıdaki dört dosyayı sırayla import eder
        ├── tokens.css         # Çerçeve/panel/sidebar token'ları (scope'lu + koyu + sidebar varyantları)
        └── layout/
            ├── shell.css        # Marka renkli çerçeve + gömülü yuvarlatılmış panel
            ├── sidebar.css      # Çerçeve üzerinde statik sidebar, sürüm çipi, mobil drawer
            └── header.css       # 70px panel header'ı, solid dev/debug etiketleri
```

> Kit artık boş bir `custom/` dizini göndermez. Herhangi bir slot'u override etmek istiyorsanız dizini önce kendiniz oluşturun (aşağıdaki reçetelere bakın).

> `theme-runtime/aura/`, build-zamanı slot dizini değildir. Resolver (`sk-theme-build.mjs`) tarafından okunmaz; `VITE_SK_THEME=aura` ayarının hiçbir etkisi yoktur — `aura` yalnızca `data-sk-theme` attribute'u üzerinden runtime'da etkinleşir.

### Kit ile gelen `aura` teması

`aura`, kit'in ikinci yerleşik temasıdır. Admin shell'i **marka renkli bir çerçeve içine gömülü, yuvarlatılmış bir panel** olarak yeniden biçimlendirir: sidebar doğrudan çerçevenin üzerinde durur (renkli stilde beyaz aktif pill), header/içerik/footer panel yüzeyini paylaşır. Yalnızca dört görsel alanı override eder — `tokens.css`, `layout/shell.css`, `layout/sidebar.css`, `layout/header.css` — geri kalan her şey (navigation, footer, page-header, tüm bileşenler) `main`'e düşer ve sadece token'lar üzerinden yeniden boyanır.

Çerçeve rengi aktif PrimeVue primary'sini izler (`--p-primary-800`); mevcut accent seçici tüm çerçeveyi yeniden renklendirir. Sidebar stil anahtarı (`colored` / `light`) ve koyu mod tam desteklidir.

**`aura`'yı etkinleştirme:** Admin **Ayarlar → Görünüm** ekranını açın ve Aura tema kartını seçin. Değişiklik anında uygulanır — derleme adımı gerekmez. Ayar admin genelinde geçerlidir (`appearance.theme` olarak DB'ye kaydedilir).

`aura`'nın CSS'i `resources/css/theme-runtime/aura/` içinde yer alır ve `main` ile birlikte **her zaman bundle'a dahil edilir**. Tüm kuralları `html[data-sk-theme='aura']`'ya scope'ludur; attribute yokken tamamen etkisizdirler. `useTheme` composable'ı (`AdminLayout` içinde çağrılır), Inertia paylaşılan `appearance.theme` prop'una yanıt olarak bu attribute'u yazar ve kaldırır.

> `VITE_SK_THEME=aura` kullanmayın — bu değişken yalnızca build-zamanı custom-tema resolver'ını denetler; `aura` slot tabanlı bir tema değildir. Bu ayarı yapmak `aura`'nın runtime davranışını etkilemez ve resolver'dan beklenmedik sonuçlara yol açabilir.

### Resolver nasıl çalışır

`sk-theme-build.mjs`, kit tarafından yönetilen ve paketin içinde gelen bir build scriptidir (vendor-resident; `vendor/lvntr/laravel-starter-kit/resources/js/theme/sk-theme-build.mjs`). Bu dosya sizin sahiplendiğiniz ya da düzenlediğiniz bir dosya değildir — yalnızca `resources/` dizininizdeki tema/slot/preset dosyaları sizin özelleştirme alanınızdır. Script, `dev` ve `build` sırasında `skTheme()` Vite plugin'i tarafından otomatik çağrılır ve `theme/_active.css`'i üretir. Model **tam-değiştirme + fallback**'tir:

1. `main/` slot listesi ve import sırası için kanoniktir.
2. Her slot için resolver, `<aktif>/<slot>` **dosyası varsa** onu yükler; yoksa `main/<slot>`'a döner.
3. Sonuç, doğru cascade sırasında her slot için bir `@import` içeren tek bir `_active.css`'tir.

`custom/` dizini hiç yoksa bile build tamamlanır — her slot `main`'e düşer.

Import sırası (orijinal cascade'den korunur):

```
tokens → fonts → _base → layout/* → components/* → _auth → utilities
```

Her katman artık bir slot'tur. Yalnızca `components/datatable.css` gönderen bir custom tema yalnızca datatable'ı ezer. Diğer her şey `main`'e döner. `custom/`'da `main/`'deki hiçbir slot yoluna uymayan bir dosya yok sayılır — resolver `main`'in slot listesini dolaşır, custom dizini değil.

Not: `_base.scss` ve `_auth.scss` `.scss` dosyalarıdır — uzantıları korunur. Resolver uzantı-farkındadır; Sass derlemesi eskisiyle aynı pipeline ile yönetilir.

### `VITE_SK_THEME` ile aktivasyon

`VITE_SK_THEME` yalnızca **build-zamanı custom tema resolver'ını** denetler. Runtime kit temaları (`main`, `aura`) üzerinde hiçbir etkisi yoktur — bunlar her zaman bundle'da bulunur ve runtime'da `data-sk-theme` attribute'u aracılığıyla etkinleşir.

Aktif custom temayı `.env`'de belirtin:

```dotenv
VITE_SK_THEME=custom
```

Varsayılan `main`'dir. Değişken yoksa veya boşsa `main` kullanılır; yani tüm slot'lar `main/`'e çözümlenir ve hiçbir custom dosya yüklenmez. `npm run dev` veya `npm run build` çalıştırın — `skTheme()` Vite plugin'i, Vite herhangi bir asset'i işlemeden önce resolver'ı çalıştırarak `_active.css`'i yeniden üretir. Bu yaklaşım, npm lifecycle hook'larına dayanmadığı için `ignore-scripts=true` altında da güvenle çalışır.

Tam build yapmadan çözümlenen manifesti önizlemek için `theme:build` npm script'ini kullanın:

```bash
npm run theme:build
# [sk-theme-build] → resources/css/theme/_active.css (25 slot, 1 override)
```

Ya da vendor script'ini doğrudan çağırın:

```bash
node vendor/lvntr/laravel-starter-kit/resources/js/theme/sk-theme-build.mjs
```

`resources/css/theme/_active.css`'i açarak her slot'un hangi dosyaya çözümlendiğini görebilirsiniz. Override'lanan slot'lar `/* override */` ile işaretlenir.

### `_active.css` — üretilen artefakt

`_active.css` bir **build artefaktı**dır:

- `.gitignore`'da listelenmiştir — asla commit etmeyin.
- `sk:update` tarafından hash-takip edilmez — her `npm run dev` / `npm run build`'de yeniden üretilir.
- `skTheme()` plugin'i resolver'ı Vite asset'leri işlemeden önce çalıştırdığı için Vite başlamadan önce her zaman mevcuttur.

---

## Özel tema reçetesi

### Tek bir bileşeni override etme

Datatable'ı başka hiçbir şeye dokunmadan yeniden stillemek için:

1. Custom dizinini oluşturun ve override etmek istediğiniz slot'u kopyalayın:

   ```bash
   mkdir -p resources/css/theme/custom/components
   cp resources/css/theme/main/components/datatable.css \
      resources/css/theme/custom/components/datatable.css
   ```

2. `custom/components/datatable.css`'i düzenleyin. Aynı class adlarını koruyun — Vue bileşenleri onları hedefler.

3. `.env`'de `VITE_SK_THEME=custom` olarak ayarlayın.

4. `npm run dev` çalıştırın. `skTheme()` plugin'i Vite başlamadan önce `_active.css`'i yeniden üretir; `custom/components/datatable.css`, import listesinde `main/components/datatable.css`'in yerini alır. Diğer tüm slot'lar `main`'den gelmeye devam eder.

Çözümlenen manifesti doğrulayın:

```
@import './main/tokens.css';
@import './main/fonts.css';
@import './main/_base.scss';
@import './main/layout/footer.css';
…
@import './custom/components/datatable.css'; /* override */
@import './main/components/dialog.css';
…
@import './main/_auth.scss';
@import './main/utilities.css';
```

### Token setini override etme

Layout ölçülerini, renkleri veya gölgeleri genel olarak değiştirmek için `tokens.css`'i override edin:

```bash
mkdir -p resources/css/theme/custom
cp resources/css/theme/main/tokens.css \
   resources/css/theme/custom/tokens.css
```

Custom property'leri düzenleyin. Tüm layout bölgeleri ve bileşenler bu token'ları okur; dolayısıyla bir token değişikliği bağımsız bileşen dosyalarına dokunmadan her yere yayılır.

### Bir layout bölgesini override etme

```bash
mkdir -p resources/css/theme/custom/layout
cp resources/css/theme/main/layout/sidebar.css \
   resources/css/theme/custom/layout/sidebar.css
```

İstediğiniz gibi düzenleyin. Yalnızca sidebar slot'u değiştirilir; layout'un geri kalanı `main`'den gelir.

### Font veya utilities'i override etme

Font bildirimleri ve Tailwind utility override'ları artık diğer her slot gibi birer slot'tur. Layout veya bileşenlere dokunmadan kendi fontlarınızı kullanmak için:

```bash
mkdir -p resources/css/theme/custom
cp resources/css/theme/main/fonts.css \
   resources/css/theme/custom/fonts.css
```

`custom/fonts.css` içindeki `@font-face` bildirimlerini düzenleyin. Sonraki build'de `_active.css`, `main` sürümü yerine sizin font dosyanızı kullanır; diğer tüm slot'lar `main`'den gelmeye devam eder.

Benzer şekilde Tailwind utility override'ları için:

```bash
mkdir -p resources/css/theme/custom
cp resources/css/theme/main/utilities.css \
   resources/css/theme/custom/utilities.css
```

`utilities.css` unlayered olup cascade'de en son emit edildiğinden her katmanlı kurala galip gelir — öncekiyle aynı öncelik davranışı. Serbestçe düzenleyebilirsiniz.

### Auth stillerini override etme

```bash
mkdir -p resources/css/theme/custom
cp resources/css/theme/main/_auth.scss \
   resources/css/theme/custom/_auth.scss
```

`custom/_auth.scss`'i düzenleyin. `.scss` uzantısı zorunludur — resolver ve Sass derleyicisi uzantı-farkındadır.

### Tam slot referansı

| Slot | Dosya | Notlar |
|---|---|---|
| tokens | `tokens.css` | CSS custom property'leri (aydınlık + karanlık) |
| fonts | `fonts.css` | `@font-face` bildirimleri |
| base | `_base.scss` | Reset / tipografi; `.scss` uzantısı zorunlu |
| layout/footer | `layout/footer.css` | `.admin-footer*` |
| layout/header | `layout/header.css` | `.admin-header*` |
| layout/page-header | `layout/page-header.css` | `.admin-page-header*` |
| layout/shell | `layout/shell.css` | `.admin-layout`, `.admin-main`, Vue geçişleri |
| layout/sidebar | `layout/sidebar.css` | `.admin-sidebar*`, `.admin-overlay` |
| components/button | `components/button.css` | Button severity paleti (Tailwind renkleri) |
| components/card | `components/card.css` | |
| components/confirm | `components/confirm.css` | |
| components/datatable | `components/datatable.css` | |
| components/dialog | `components/dialog.css` | |
| components/editor | `components/editor.css` | |
| components/formbuilder | `components/formbuilder.css` | |
| components/menus | `components/menus.css` | |
| components/navigation | `components/navigation.css` | |
| components/page-loader | `components/page-loader.css` | Tam ekran sayfa-geçiş yükleme overlay'i |
| components/primevue | `components/primevue.css` | |
| components/tabs | `components/tabs.css` | |
| components/tag | `components/tag.css` | |
| components/toast | `components/toast.css` | |
| auth | `_auth.scss` | Auth layout stilleri; `.scss` uzantısı zorunlu |
| utilities | `utilities.css` | Tailwind utility override'ları; unlayered, en son emit edilir |

### Notlar

- Özel tema slot'ları **dosya bütünüyle** değiştirir — cascade diff yoktur. Başlangıç noktası olarak `main` dosyasını kopyalayın.
- Özel temalar yeni slot ekleyemez. `custom/`'daki `main/`'de eşleşen yolu olmayan bir dosya hiçbir zaman import edilmez.
- `VITE_SK_THEME=main` (varsayılan) stok panel ile byte-identical bir build üretir — hiçbir custom dosya yüklenmez.
- Kit `custom/` dizini göndermez. Override eklemeden önce dizini kendiniz oluşturun; dizin yoksa her slot `main`'e çözümlenir.
- Artık ihtiyaç duymadığınız `custom/` dosyalarını kaldırın; resolver otomatik olarak `main`'e döner.
- `.scss` uzantılı slot'lar (`_base.scss`, `_auth.scss`) `custom/` içinde de aynı uzantıyı taşımalıdır.

---

## PrimeVue preset katmanı

### Nasıl çalışır

`resources/js/theme/preset.ts`, PrimeVue styled-mode preset'in tabanıdır — birincil palet, yüzey renkleri, border radius ve bileşen başına token'ları tanımlar. `app.ts` bunu `@/theme/preset` olarak import eder. Import yolu hiçbir zaman değişmez.

`vite.config.ts`'teki alias `customResolver` — kit'in vendor-resident `vite-plugin-sk-theme.mjs`'inden export edilen `resolveActivePreset` helper'ı — `@/theme/preset` specifier'ını build zamanında yakalar:

- `VITE_SK_THEME` değeri `main` **değilse** ve `resources/js/theme/<aktif>/preset.ts` **mevcutsa** → override dosyasına çözümlenir.
- Aksi takdirde → taban `resources/js/theme/preset.ts`'e çözümlenir.

Taban dosya daima yerinde kalır — kit onu asla taşımaz çünkü consumer projesinde en sık özelleştirilen dosyadır.

### Dizin düzeni

```
resources/js/theme/
├── preset.ts              # Taban preset (consumer tarafından özelleştirilebilir; yerinde kalır)
└── custom/                # Kit ile gönderilmez — gerektiğinde kendiniz oluşturun
    └── preset.ts          # (siz oluşturursunuz — aşağıdaki reçeteye bakın)
```

`custom/` dizini kit tarafından gönderilmez. `custom` temasının preset'i varsayılan olarak yoktur; bu nedenle taban preset kullanılır ve PrimeVue görünümü stok panelle byte-identical kalır. Temaya özel preset override'ı ihtiyaç duyduğunuzda dizini kendiniz oluşturun.

### Custom temaya kendi PrimeVue paletini verme

1. `resources/js/theme/custom/preset.ts` oluşturun (`custom/` dizini yoksa önce oluşturun). En basit yaklaşım, taban preset'i import edip yalnızca paleti override etmektir:

   ```bash
   mkdir -p resources/js/theme/custom
   ```

   ```ts
   import { definePreset } from '@primevue/themes';
   import Material from '@primevue/themes/material';
   import AppPreset from '../preset';

   export default definePreset(Material, {
       ...AppPreset,
       semantic: {
           ...AppPreset.semantic,
           primary: {
               50:  '{emerald.50}',
               100: '{emerald.100}',
               200: '{emerald.200}',
               300: '{emerald.300}',
               400: '{emerald.400}',
               500: '{emerald.500}',
               600: '{emerald.600}',
               700: '{emerald.700}',
               800: '{emerald.800}',
               900: '{emerald.900}',
               950: '{emerald.950}',
           },
       },
   });
   ```

2. `.env`'de `VITE_SK_THEME=custom` olarak ayarlayın.

3. `npm run dev` veya `npm run build` çalıştırın. Plugin, `@/theme/preset`'i override dosyanıza çözümler; override yoksa tabana döner.

### Notlar

- Preset nesnesini **default export** olarak dışa aktarın — `app.ts` bunu PrimeVue'nun `preset` seçeneğine geçirir.
- Override yalnızca `VITE_SK_THEME=custom` olduğunda ve `resources/js/theme/custom/preset.ts` mevcut olduğunda uygulanır. Diğer her değerde tabana döner.
- Kit `resources/js/theme/` altında `custom/` dizini göndermez. Temaya özel preset ihtiyacınızda kendiniz oluşturun.
- Üretilen artefakt yoktur. Bu saf modül çözümlemesidir — gitignore'a alınacak `_active` dosyası yok, bakılacak npm zinciri yok.

### Bağımlılık zinciri — token'lar ve preset

`resources/css/theme/main/tokens.css` (ve varsa `custom/tokens.css` override'ı), layout ve bileşenlerin kullandığı `--admin-*` custom property'lerini tanımlar. Bunlar, PrimeVue preset'inin çalışma-zamanında emit ettiği `--p-*` değişkenlerine **canlı referanslardır**:

```css
/* tokens.css — admin rolleri PrimeVue preset çıktısına bağlanır */
--admin-sidebar-bg: var(--p-surface-900);
--admin-sidebar-item-active-bg: var(--p-primary-color);
```

Canlı `var()` referansı oldukları için, preset override'ınızda birincil/yüzey paletini değiştirmek **otomatik olarak akar** — `--admin-*` rolleri yeni `--p-*` değerlerine yeniden çözülür; "senkronize edilecek" bir şey yoktur.

`tokens.css`'i yalnızca bir admin rolünün hangi PrimeVue token'ına bağlandığını yeniden eşlemek isterseniz override edin (örn. sidebar'ı farklı bir surface adımından okutmak):

```bash
mkdir -p resources/css/theme/custom
cp resources/css/theme/main/tokens.css \
   resources/css/theme/custom/tokens.css
```

Ardından `--admin-*` property'lerini seçtiğiniz `--p-*` token'larına göre düzenleyin.

---

## Accent renk sistemi

Admin paneli, PrimeVue birincil paletini ve sidebar yüzeyini derleme gerektirmeden çalışma zamanında yeniden boyayan bir accent rengi destekler. Sistem iki katmana sahiptir: **admin global varsayılanı** (Ayarlar → Görünüm'den ayarlanır) ve isteğe bağlı **kullanıcı başına override** (header popover'dan ayarlanır). Kullanıcının kişisel bir seçimi yoksa admin global varsayılanı uygulanır; admin global varsayılanı da `'default'` ise kit birincil rengi kullanılır (`main` altında mavi, `aura` altında indigo).

### Nasıl çalışır

`useAccentColor` (vendor-resident composable, `resources/js/composables/useAccentColor.ts`) accent rengini yönetir. `onMounted`'da ve watch içinde şunları çağırır:

1. **`updatePrimaryPalette(palette)`** — PrimeVue'nun çalışma zamanı palet değişimi. Aktif `--p-primary-*` CSS custom property'lerini seçilen Tailwind v4 oklch ölçeğiyle değiştirir; düğmeler, bağlantılar, odak halkaları, aktif durumlar ve her `--p-primary-color` referansı derleme gerektirmeden anında güncellenir.
2. **`<html>` üzerinde `data-sk-accent`** — `tokens.css` tarafından aydınlık modda sidebar yüzeyine derin accent tonunu uygulamak için kullanılan bir işaretçidir. Koyu modda sidebar her zaman nötr koyu yüzeyde kalır; yalnızca aktif öğeler ve düğmeler accent rengini taşır. Accent `'default'` olduğunda işaretçi yoktur.

Sidebar yüzey işlemi accent'ten bağımsızdır: `sidebarStyle` değeri (`'colored'` | `'light'`), `<html>` üzerindeki `data-sk-sidebar` işaretçisini denetler. `data-sk-sidebar="light"` mevcutsa sidebar koyu metinle beyaz/açık yüzey gösterir; yoksa (varsayılan `'colored'`), derin accent tonu (koyu modda nötr koyu) uygulanır.

### Mevcut renkler

`ACCENT_COLORS`, seçilebilir 26 ismi listeler: 22 standart Tailwind v4 paleti (`slate`, `gray`, `zinc`, `neutral`, `stone`, `red`, `orange`, `amber`, `yellow`, `lime`, `green`, `emerald`, `teal`, `cyan`, `sky`, `blue`, `indigo`, `violet`, `purple`, `fuchsia`, `pink`, `rose`) ve dört özel sönük ton (`taupe`, `mauve`, `mist`, `olive`). `'default'` özel değeri "admin global varsayılanını kullan" (global da `'default'` ise kit primary'sine dön) anlamına gelir.

Tüm palet değerleri composable içinde satır içi Tailwind v4 oklch ölçekleridir — Tailwind, kullanılmayan `--color-*` değişkenlerini tree-shake ettiğinden çalışma zamanında CSS değişkenlerinden okunmazlar.

### Kalıcılık

Hem accent seçimi hem de sidebar stili `localStorage`'a kaydedilir:

| Anahtar | Varsayılan | Anlam |
|---|---|---|
| `admin-accent-color` | `'default'` | Kullanıcı başına accent seçimi; `writeDefaults: false` (kullanıcı açık bir seçim yapana kadar seed değer yazılmaz) |
| `admin-sidebar-style` | `'colored'` | Kullanıcı başına sidebar yüzey işlemi |

`writeDefaults: false` seçeneği, ilk kez giren kullanıcı için `localStorage`'ın boş kalmasını sağlar; böylece kullanıcı açıkça bir renk seçene kadar admin global varsayılanı her zaman geçerlidir. Tek seferlik eski storage temizliği (`migrateLegacyAppearanceStorage`), eski derlemelerdeki isteksiz `writeDefaults: true` tarafından yazılmış `'default'` / `false` / `'colored'` seed'leri kaldırır.

### Admin global varsayılanı ve kullanıcı override'ı

`appearance` paylaşılan prop'u (her Inertia yanıtında `HandleInertiaRequests` tarafından sağlanır) şunları taşır:

| Alan | Tür | Açıklama |
|---|---|---|
| `accent_color` | `string` | Admin global varsayılan accent adı veya `'default'` |
| `sidebar_style` | `'colored' \| 'light'` | Admin global varsayılan sidebar stili |
| `dark_mode_default` | `boolean` | Admin global koyu mod varsayılanı |
| `theme` | `string` | Aktif runtime teması (`'main'` veya `'aura'`) |

`useAppearanceDefaults` bu prop'u okur. `useAccentColor`, kullanıcının kişisel seçimi olmadığında başlangıç değerlerini oluşturmak için `defaultAccent` ve `defaultSidebarStyle`'ı ondan alır.

Global varsayılanı **Ayarlar → Görünüm → Varsayılan Renk**'ten yapılandırın. Admin tarafı seçici canlı önizleme gösterir: bir renk seçmek `applyAccent(color, { followGlobal: false })` çağırır; bu, `'default'`'u kit primary olarak değerlendirir (admin varsayılanı tanımladığı için kit mavisinin nasıl göründüğünü görmelidir). Sekmeden kaydetmeden çıkıldığında kullanıcının kendi oturum accent'i geri yüklenir.

Her admin kullanıcısına açık header popover, aynı renk ızgarasını ve bir "Varsayılan" renk kutusu gösterir. Renk seçmek `setAccent(color)` çağırır ve `localStorage`'a kaydeder; `accent` üzerindeki watch otomatik olarak `applyAccent`'i tetikler. "Varsayılan"ı seçmek kişisel override'ı temizler ve admin global varsayılanı tekrar geçerli olur.

### Tema etkileşimi

- **`main` varsayılan accent:** mavi (`{blue.x}` token referansları, Material preset'e göre çözümlenir).
- **`aura` varsayılan accent:** indigo (Tailwind v4 oklch ölçeği). Runtime tema `main` ve `aura` arasında geçiş yaptığında, `useAccentColor` paleti otomatik olarak yeniden uygular (`appearance.theme` üzerindeki `watch`, `applyAccent`'i tetikler); böylece `'default'` accent aktif tema için doğru imza rengini seçer.
- Aura çerçeve rengi (`--p-primary-800`) aktif primary'yi takip eder; `aura` altında accent seçici tüm çerçeveyi yeniden renklendirir.

### Sayfa yükleyici ve accent

`SkPageLoader` — Inertia sayfa geçişlerinde gösterilen tam ekran animasyonlu overlay — marka öğeleri için `--p-primary-color` kullanır (ışınlar, damlacıklar, sekme noktaları, dalga tepesi harf rengi). `updatePrimaryPalette` çalışma zamanında `--p-primary-color`'ı güncellediğinden, sayfa yükleyici otomatik olarak aktif accent rengini kullanır. Overlay arka planı tema-güdümlüdür:

- **`main`:** `--admin-sidebar-bg` (koyu sidebar yüzeyi).
- **`aura`:** `--p-surface-900` (aydınlık) / `--p-surface-950` (koyu) — `aura`'nın `--admin-sidebar-bg`'si şeffaf olduğundan nötr koyu yüzey kullanılır.

Stiller `components/page-loader.css` tema slotunda yaşar (custom tema ile override edilebilir). Overlay SSR-güvenlidir ve `prefers-reduced-motion`'ı destekler.

---

## Karanlık mod

Karanlık mod için CSS custom property'leri `main/tokens.css` içindeki `.dark { … }` bloklarında (ve layout-özel karanlık mod override'larının bulunduğu layout dosyalarında) tanımlanır. `tokens.css`'i override ederseniz, `.dark` bloklarını da kopyalayın.

Karanlık mod `useDarkMode` ile değiştirilir — `<html>` üzerine `dark` sınıfı ekler / kaldırır. Ayrı bir build adımı veya CSS dosyası gerekmez.

`aura` temasının karanlık mod kuralları `theme-runtime/aura/tokens.css` içindeki `html.dark[data-sk-theme='aura'] { … }` blokları olarak tanımlanır. Her iki attribute bağımsızdır — karanlık mod ve tema geçişi dört kombinasyonun tamamında doğru şekilde birleşir (`aydınlık/koyu` × `main/aura`).

---

## İlgili belgeler

- `docs/install.md` — ilk kurulum
- `docs/update.md` — `sk:update` ve hash-takipli stub'lar
- `docs/UPGRADE.md` — mevcut projeler için geçiş rehberi
