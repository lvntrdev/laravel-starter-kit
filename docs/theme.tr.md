# Tema Sistemi — Lvntr Starter Kit

Bu belge şunları kapsar:

- `AppShell` / `AdminLayout` kompozisyon modeli ve yeni layout kurma
- `main` + `custom` CSS yapısı, build-zamanı resolver ve tam-değiştirme + fallback modeli
- `VITE_SK_THEME` ile aktivasyon
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

Tema sistemi iki bağımsız, tamamlayıcı katmana sahiptir. Her ikisi de aynı `VITE_SK_THEME` ortam değişkenine bağlıdır ve build zamanında çözümlenir — runtime tema geçişi yoktur.

| Katman | Ne kapsar | Resolver | Artefakt |
|---|---|---|---|
| **CSS tema override'ı** | Layout ölçüleri, renkler, gölgeler, bileşen CSS sınıfları | `sk-theme-build.mjs` (vendor-resident), `_active.css`'i üretir | Üretilen dosya (`_active.css`), gitignore'da |
| **PrimeVue preset** | Birincil palet, yüzey renkleri, border radius, bileşen token'ları (`--p-*` değişkenleri) | `vite.config.ts`'teki alias `customResolver`'ı (`resolveActivePreset`) | Yok — saf JS modül çözümlemesi |

İki katman birbirinden bağımsızdır: preset'e dokunmadan CSS'i override edebilir, ya da tersi. Bir `tokens.css` override'ı genellikle preset'in emit ettiği `--p-*` token'larını okur — aşağıdaki [Bağımlılık zinciri](#bağımlılık-zinciri--tokenlar-ve-preset) bölümüne bakın.

---

## CSS tema sistemi

### Dizin yapısı

```
resources/css/theme/
├── theme.css              # Giriş noktası: yalnızca _active.css'i import eder
├── _active.css            # OLUŞTURULAN — elle düzenleme; gitignore'da
├── main/                  # Dahili ana tema (tüm slot'lar için kaynak)
│   ├── tokens.css         # CSS custom property'leri: layout ölçüleri, renkler, gölgeler
│   ├── fonts.css          # Web font bildirimleri
│   ├── _base.scss         # Base reset / tipografi
│   ├── layout/
│   │   ├── shell.css        # .admin-layout, .admin-main, Vue geçişleri
│   │   ├── sidebar.css      # .admin-sidebar*, .admin-overlay
│   │   ├── header.css       # .admin-header*
│   │   ├── page-header.css  # .admin-page-header*
│   │   └── footer.css       # .admin-footer*
│   ├── components/
│   │   ├── card.css
│   │   ├── confirm.css
│   │   ├── datatable.css
│   │   ├── dialog.css
│   │   ├── editor.css
│   │   ├── formbuilder.css
│   │   ├── menus.css
│   │   ├── navigation.css
│   │   ├── primevue.css
│   │   ├── tabs.css
│   │   ├── tag.css
│   │   └── toast.css
│   ├── _auth.scss         # Auth layout stilleri
│   └── utilities.css      # Tailwind utility override'ları
└── custom/                # Override tema — kit ile gönderilmez; siz oluşturursunuz
    └── (gerektiğinde oluşturun — aşağıdaki reçetelere bakın)
```

> Kit artık boş bir `custom/` dizini göndermez. Herhangi bir slot'u override etmek istiyorsanız dizini önce kendiniz oluşturun (aşağıdaki reçetelere bakın).

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

Aktif temayı `.env`'de belirtin:

```dotenv
VITE_SK_THEME=custom
```

Varsayılan `main`'dir. Değişken yoksa veya boşsa `main` kullanılır. `npm run dev` veya `npm run build` çalıştırın — `skTheme()` Vite plugin'i, Vite herhangi bir asset'i işlemeden önce resolver'ı çalıştırarak `_active.css`'i yeniden üretir. Bu yaklaşım, npm lifecycle hook'larına dayanmadığı için `ignore-scripts=true` altında da güvenle çalışır.

Tam build yapmadan çözümlenen manifesti önizlemek için `theme:build` npm script'ini kullanın:

```bash
npm run theme:build
# [sk-theme-build] theme="custom" → resources/css/theme/_active.css (22 slot, 1 override)
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
| components/card | `components/card.css` | |
| components/confirm | `components/confirm.css` | |
| components/datatable | `components/datatable.css` | |
| components/dialog | `components/dialog.css` | |
| components/editor | `components/editor.css` | |
| components/formbuilder | `components/formbuilder.css` | |
| components/menus | `components/menus.css` | |
| components/navigation | `components/navigation.css` | |
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
   import Aura from '@primevue/themes/aura';
   import AppPreset from '../preset';

   export default definePreset(Aura, {
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

## Karanlık mod

Karanlık mod için CSS custom property'leri `main/tokens.css` içindeki `.dark { … }` bloklarında (ve layout-özel karanlık mod override'larının bulunduğu layout dosyalarında) tanımlanır. `tokens.css`'i override ederseniz, `.dark` bloklarını da kopyalayın.

Karanlık mod `useDarkMode` ile değiştirilir — `<html>` üzerine `dark` sınıfı ekler / kaldırır. Ayrı bir build adımı veya CSS dosyası gerekmez.

---

## İlgili belgeler

- `docs/install.md` — ilk kurulum
- `docs/update.md` — `sk:update` ve hash-takipli stub'lar
- `docs/UPGRADE.md` — mevcut projeler için geçiş rehberi
