# Yenilikler

Starter kit'e yeni eklenen özellikler ve iyileştirmeler burada listelenir.

## 2026-05-26 — v13.5.10

### Yama sürüm — SkCard primitive, card başlığı sağ slot ve caption alt çizgisi

v13.5.10 ile PrimeVue Card'ı sarmalayan paylaşımlı bir wrapper olan `SkCard` eklendi; kit'in tüm card yüzeyleri için tek doğru kaynak. Aynı sürümde tüketiciye iki yeni slot açılıyor: `SkForm`'un root card'ında `#title-end` ve her `FB.section()` card'ında per-section `#section-${key}-title-end`. İkisi de başlığın **sağına**, aynı satırda render olur — action button, durum badge'i veya kontekste duyarlı gösterge için. Section slot'u scoped'tur ve `{ values }` (mevcut form değerlerinin reaktif snapshot'ı) verir; tüketici koşullu render yapabilir. `SkCard`'ın kendi API'si: `title`, `subtitle`, `transparent`, `divider` ve `pt` prop'ları + `header`/`title`/`subtitle`/`content`/`footer`/`title-end` slot'ları; `inheritAttrs: false` + `useAttrs` kombinasyonu sayesinde dış `class` fallthrough'u Card root'una düşer (PrimeVue Card kendi root'unda `inheritAttrs: false` yaptığı için aksi halde class kaybolur). `SkForm.vue` ve `SkFormFieldRenderer.vue` `<Card>` yerine `<SkCard>` kullanacak şekilde refactor edildi — `cardPt`/`transparentCard`/`sectionCardPt` helper'ları kaldırıldı, title flex wrapper ve caption alt çizgi stilleri `_formbuilder.scss`'ten `_card.scss`'e taşındı (`.sk-card--divider .p-card-caption`). Artık `SkCard` ile sarmalanan herhangi bir tüketici aynı caption davranışını alır: başlık metni solda, `#title-end` sağda, alt başlık altta, caption bloğunun altına ayırıcı çizgi.

#### Eklendi

- **`SkCard` UI primitive** — `resources/js/components/Lvntr-Starter-Kit/ui/SkCard.vue`. `SkForm` (ve gelecekte `SkDatatable` / sayfa düzeyi card'lar) tarafından kullanılması amaçlanan, PrimeVue Card etrafındaki paylaşımlı wrapper. Caption davranışı, `#title-end` slot'u ve alt çizgi tek implementasyondan geliyor.
  - Props: `title?: string`, `subtitle?: string`, `transparent?: boolean` (varsayılan `false` — `true` arka plan/shadow/padding'i kaldırır; dialog veya nested card için), `divider?: boolean` (varsayılan `true` — caption bloğunun altına alt çizgi), `pt?: Record<string, any>` (PrimeVue Card pt'sine merge edilir; consumer key'leri çakışmada kazanır).
  - Slot'lar: `header`, `title`, `subtitle`, `content` (default slot da content'e map'lenir), `footer`, **`title-end`** (sağa hizalı action/badge/durum slot'u).
  - `inheritAttrs: false` + `useAttrs` ile dış `class` fallthrough'u Card root'una geçer (PrimeVue Card kendi root'unda `inheritAttrs: false` yaptığı için aksi halde class düşmüyordu).
  - `index.ts`'ten `SkCard` olarak export edildi.
- **`SkForm.vue` — `#title-end` slot** — form-level card başlığının sağına render edilen yeni slot. Başlık metniyle aynı satırda action button, badge veya durum göstergesi yerleştirmek için kullanılır. Slot yalnızca içerik verildiğinde render edilir.
- **`SkFormFieldRenderer.vue` — per-section `#section-${key}-title-end` slot** — her section card başlığının sağına render edilen scoped slot. `SkForm.vue` zaten generic `v-for $slots` forwarding yaptığı için tüketici doğrudan `<SkForm>` üzerinden `<template #section-address-title-end="{ values }">` şeklinde kullanır. Slot scope: `{ values }` — mevcut form değerlerinin reaktif snapshot'ı, koşullu render için kullanışlı.
- **Docs** — `docs/ui-components.md` ve `docs/ui-components.tr.md`'ye yeni "SkCard" bölümü; `docs/formbuilder.md` ve `docs/formbuilder.tr.md`'ye "Card Title Actions Slot" / "Card Başlık Sağ Slot" bölümü.

#### Değiştirildi

- **`SkForm.vue` — root `<Card>` → `<SkCard>` refactor** — kendi içindeki `cardPt` computed'i ve `transparentCard` style sabiti kaldırıldı; `:transparent="isTransparentCard"` prop'u ile SkCard'a devredildi. Form card'ının başlık ve alt başlığı `:title` / `:subtitle` prop'larıyla geçirilir; flex title wrapper ve caption alt çizgisi SkCard içinde tek noktadan üretilir.
- **`SkFormFieldRenderer.vue` — section render'ı `<SkCard>`'a geçirildi** — `sectionCardPt` yerine `sectionIsTransparent` helper'ı + `:transparent` prop'u kullanılır. Section title flex wrapper ve `title-end` slot'u SkCard'a delege edildi; icon'lu title (`SkIcon` + metin) doğrudan SkCard'ın `#title` slot'unda render edilir.
- **`RenderCtx` (SkFormFieldRenderer.vue) — `transparentCard` alanı kaldırıldı** — SkCard'ın `transparent` prop'u tek doğru kaynak; ctx üzerinde dolaşan style sabiti gerek bırakmıyor.
- **`stubs/resources/css/theme/_card.scss`** — SkCard stilleri eklendi:
  - `.sk-card__title-row` (flex w-full justify-between, başlık satırı)
  - `.sk-card__title-text` (başlık metni, ikon hizalama için inline-flex)
  - `.sk-card__title-end` (sağ slot kapsayıcısı, shrink-0)
  - `.sk-card--divider .p-card-caption` — caption bloğunun altına `pb-3 mb-1 border-b` + `--p-surface-200` / `--p-surface-700` dark varyant. Yalnız SkCard içinde tetiklenir, diğer PrimeVue Card kullanımlarını etkilemez.
- **`stubs/resources/css/theme/_formbuilder.scss`** — bu çalışmanın ilk turunda eklenen geçici selektörler (`.sk-fb__card*`, `.sk-fb__section-title-wrapper`, `.sk-fb__section-title-end`, `.sk-fb__card .p-card-caption`, `.sk-fb__section .p-card-caption`) kaldırıldı. Yerine `_card.scss`'e işaret eden kısa bir not eklendi.

## 2026-05-21 — v13.5.9

### Yama sürüm — SkIcon primitive, section/card gruplama ve icon API'leri

v13.5.9 ile `SkIcon` eklendi: tek `icon: string` prop'tan üç formatı otomatik algılayan paket-bağımsız bir icon renderer. Ham SVG → `v-html`, URL → `<img>`, diğer → `<i :class>` (PrimeIcons, FontAwesome, MDI, Lucide, Iconify ve diğer class tabanlı icon set'leri). `BaseFieldConfig`'e eklenen birleşik icon API tüm field tiplerine yayıldı: `labelIcon` / `labelIconPosition` her layout'ta label yanına icon koyuyor, `icon` / `iconPosition` input içine koyuyor (`input-text`, `input-number`, `input-mask`, `password` destekli). Başlık field'larına da kendi `icon` / `iconPosition` çifti eklendi. Öne çıkan özellik ise `SectionFieldConfig` (`type: 'section'`) ve `FB.section()` fluent builder'ı — field'lar artık başlık, alt başlık, ikon ve ayarlanabilir kolon sayısıyla bir PrimeVue Card içinde görsel olarak gruplandırılabiliyor; form payload'ı flat kalmaya devam ediyor (section key'leri hiçbir zaman emit edilmiyor). `SkForm.vue`'ya `iterateAllFields` generator destekli `flatFields` computed eklendi; bu sayede section'lar tüm mevcut field işleme mantığı için (dosya upload, tarih dönüşümleri, definition preload, dynamic select) şeffaf. `SkFormFieldRenderer.vue` ayrıştırılarak recursive render ve slot forwarding bu bileşene taşındı. `InputTextFieldConfig.icon` / `iconPosition` artık deprecated; `BaseFieldConfig` seviyesindeki API kullanılmalı.

#### Eklendi

- **`SkIcon` UI primitive** — paket-bağımsız icon renderer. Tek `icon: string` prop'tan otomatik algılama: `<svg…` → ham SVG (`v-html`), `^(https?:|data:)` → `<img>`, diğer → `<i :class>` (PrimeIcons, FontAwesome, MDI, Lucide, Iconify ve diğer class tabanlı icon set'ler). **Güvenlik:** `icon` yalnızca builder config'ten (geliştirici kontrollü) geçirilmeli — kullanıcı kaynaklı string XSS riskidir (`<svg…` path'i `v-html` ile render eder).
- **`BaseFieldConfig` icon alanları** — tüm field tipleri için ortak icon API'si:
  - `labelIcon?: string` + `labelIconPosition?: 'left' | 'right'` (varsayılan: `'left'`) — tüm layout path'lerinde label yanına icon.
  - `icon?: string` + `iconPosition?: 'left' | 'right'` (varsayılan: `'left'`) — input içine icon. Desteklenen tipler: `input-text`, `input-number`, `input-mask`, `password` (custom path — `feedback: true` ise icon yok). `groupPrefix`/`groupSuffix` önceliklidir; varsa input icon devre dışı kalır.
- **`TitleFieldConfig` icon alanları** — `icon?: string` + `iconPosition?: 'left' | 'right'`. Örnek: `FB.title('Genel').icon('pi pi-info-circle')`.
- **`SectionFieldConfig` (yeni field tipi `type: 'section'`)** — form içinde Card ile görsel field gruplama:
  - `title?` (translation key, label fallback), `subtitle?`, `icon?`, `iconPosition?`
  - `cols?: number` (varsayılan: parent formun `cols` değeri)
  - `fields: FieldConfig[]` (tek seviye nested — iç içe section desteklenmez)
  - `isCard?: boolean` (varsayılan: card görünür; `false` → şeffaf Card)
  - **Form veri yapısı flat kalır** — section'ın `key`'i payload'a girmez; section yalnızca görsel gruplama primitive'idir.
- **`SectionBuilder` ve `FB.section(title?)` factory** — fluent API: `.title(t)`, `.subtitle(s)`, `.icon(str)`, `.iconPosition(p)`, `.cols(c)`, `.isCard(enabled)`, `.addFields(...)`.
- **`BaseFieldBuilder` fluent metotları** — `.labelIcon(str)`, `.labelIconPosition(p)`, `.icon(str)`, `.iconPosition(p)` artık tüm field builder'larında mevcut (`InputTextBuilder`'dan base'e taşındı — imza aynı, davranış değişmedi).
- **`TitleBuilder.icon()` ve `.iconPosition()`** metotları eklendi.
- **`SkFormFieldRenderer.vue`** — ayrıştırılmış recursive field renderer. Section render, slot forwarding ve label/title icon render bu bileşene taşındı; `SkForm.vue` template'i basitleşti.
- **Docs** — `docs/formbuilder.md` ve `docs/formbuilder.tr.md`'ye 5 yeni bölüm: İkonlar (Paket-Bağımsız), Label İkonları, Input İkonları, Başlık İkonları, Section / Card Gruplama. XSS güvenlik notu her iki dilde mevcut.

#### Değiştirildi

- **`AppDialog.vue` — `confirmSeverity` artık varsayılan `'primary'` kullanmıyor** — `state.footer?.severity ?? 'primary'` → `state.footer?.severity`. Onay düğmesi artık `severity` belirtilmediğinde PrimeVue Button'ın kendi varsayılan görünümünü kullanır (tema preset'inden gelir). `DialogFooter.severity` açıkça set edilmemiş mevcut dialog'lar görsel değişiklik yaşayabilir.
- **`useDialog.ts` — `DialogFooterSeverity` tipi genişletildi** — `'primary'` kaldırıldı (PrimeVue Button'da geçerli değil); `'info'`, `'help'`, `'contrast'` eklendi. Tam liste: `'secondary' | 'success' | 'info' | 'warn' | 'help' | 'danger' | 'contrast'`.
- **`SkForm.vue` — flat field iterasyonu** — `derivedDefaults`, `currentValues`, `definitionKeys`, `dynamicSelectFields`, `hasFileFields`, `dateOnlyFields` computed'leri artık yeni `flatFields` computed'i (iteratif `iterateAllFields` generator) üzerinden çalışıyor. Section içindeki field'lar otomatik olarak doğru kategorize ediliyor (dosya upload existingMediaKey çözümü, tarih dönüşümleri, definition preload, dynamic optionsUrl fetch). Section içermeyen mevcut formlar birebir aynı render ediliyor (regression yok).
- **`SkFormInput.vue` — generic input icon** — daha önce yalnızca `input-text`'te aktif olan `IconField` wrapping pattern'i artık `input-number`, `input-mask` ve `password` için de (custom path) aktif. Icon descriptor'lar `SkIcon` üzerinden render ediliyor; PrimeIcons dışında MDI/FA/Lucide/Iconify/SVG/img URL de çalışıyor. `BaseFieldConfig.icon` önceliklidir, `InputTextFieldConfig.icon` legacy fallback olarak korunur.
- **`stubs/resources/css/theme/_formbuilder.scss`** — `.sk-fb__title` ve `.sk-fb__label` selector'larına icon hizalaması için minimal `inline-flex items-center gap` eklendi (line-height ve padding değişmedi). Yeni bölümler: `SKICON & LABEL/TITLE ICONS` (`.sk-icon`, `.sk-icon--svg svg`, `.sk-icon--img`, `.sk-fb__label-icon`, `.sk-fb__title-icon`, `.sk-fb__section-icon` + `--left/--right` modifier hook'ları), `SECTION CARD` (`.sk-fb__section`, `.sk-fb__section-title`, `.sk-fb__section-field`).

#### Deprecated

- **`InputTextFieldConfig.icon` ve `InputTextFieldConfig.iconPosition`** — yeni `BaseFieldConfig.icon` ve `BaseFieldConfig.iconPosition` kullanın. Legacy alanlar geriye uyumluluk için korundu (`SkFormInput.vue` `base ?? legacy` fallback ile aynı render üretiyor), gelecek major versiyonda kaldırılacak.

#### Yükseltme

```bash
composer update lvntr/laravel-starter-kit

# Etkilenen stub'ları yeniden yayınla (DİKKAT: özelleştirilmiş stub'lar override edilir — önce diff alın)
# stubs/resources/css/theme/_formbuilder.scss
# stubs/resources/js/composables/useDialog.ts  ← DialogFooterSeverity tipi değişti
php artisan vendor:publish --tag=starter-kit-stubs --force
```

**`DialogFooterSeverity` breaking change:** `'primary'` artık geçerli bir değer değil. `useDialog().open(...)` çağrılarında `severity: 'primary'` kullandıysanız kaldırın (Button kendi tema varsayılanını uygular) ya da `'secondary'` / `'contrast'` gibi geçerli bir değerle değiştirin. TypeScript bu satırları zaten hata olarak işaretleyecektir.

**Migration:** legacy `InputTextFieldConfig.icon` çağrılarınız çalışmaya devam eder (deprecated, kaldırılana kadar fallback). Yeni özellikleri kullanmak için:

```ts
// Label icon — her field tipinde
FB.inputText().key('email').label('E-posta').labelIcon('pi pi-envelope')

// Input icon — input-text/number/mask/password
FB.inputText().key('search').icon('pi pi-search')                    // PrimeIcons
FB.inputText().key('user').icon('mdi mdi-account')                   // Material Design Icons
FB.inputText().key('star').icon('fa fa-star').iconPosition('right')  // FontAwesome
FB.inputText().key('logo').icon('https://cdn.example.com/icon.svg')  // URL

// Başlık icon'u
FB.title('Genel Bilgiler').icon('pi pi-info-circle')

// Section / Card gruplama
FB.form()
    .isCard(false)
    .addFields(
        FB.section('Kişisel Bilgiler').icon('pi pi-user').cols(2).addFields(
            FB.inputText().key('first_name').label('Ad'),
            FB.inputText().key('last_name').label('Soyad'),
        ),
        FB.section('Adres').icon('pi pi-map-marker').addFields(/* ... */),
    )
    .build();
```

---

## 2026-05-20 — v13.5.8

### Yama sürüm — AppDialog Material Flat shell, zengin header & footer API, scrollbar-gap düzeltmesi

`AppDialog`, PrimeVue Dialog'un `#container` template'i etrafında bağımsız bir "Material Flat" shell olarak yeniden tasarlandı: header'da gradient ikon lozenge + başlık + alt başlık, opsiyonel slate-100 sticky footer (solda hint ikon/metin, sağda İptal/Onay butonları), daha yumuşak iki katmanlı drop shadow ve özel "rise" enter/leave animasyonu. Shell tamamen `sk-dlg` PT class'ı ile scope'lanmış durumda; `ConfirmDialog` ve diğer Dialog kullanımları etkilenmiyor. `useDialog` composable'ı `subtitle`, `icon` ve `footer` open option'ları, yeni `DialogFooter` interface'i ve `setFooter()` / `patchFooter()` metotları ile genişletildi — dialog içinde render olan bileşenler artık footer'ı (örneğin onay butonunu loading state'e geçirme) dialog'u yeniden açmadan değiştirebiliyor. v13.5.7'den kalan son sticky-bar sorunu — form scroll ettiğinde gri footer'ın sağında kalan ~10 px beyaz boşluk — dialog body'sinin scrollbar'ını görsel olarak gizleyerek çözüldü; scroll hâlâ wheel / trackpad / klavye ile çalışıyor, slate-100 bar artık dialog'un sağ kenarına temiz biçimde dayanıyor.

#### Eklendi

- **`AppDialog` Material Flat shell** — header artık ikon lozenge (`state.icon`), başlık (`state.header`) ve alt başlık (`state.subtitle`) basıyor; PrimeVue'nun default close butonu yerine slate temalı bir close butonu kondu. Opt-in footer slate-100 sticky action bar üretiyor (hint ikon/metin + İptal/Onay).
- **`useDialog` zengin header & footer API** — `OpenOptions.subtitle`, `OpenOptions.icon`, `OpenOptions.footer` eklendi. Yeni `DialogFooter` tipi: `icon`, `text`, `cancelLabel`, `confirmLabel`, `confirmIcon`, `severity`, `onConfirm`, `hideCancel`, `disabled`, `loading`. Yeni `setFooter()` ve `patchFooter()` metotları eklendi.
- **`_dialog.scss`** — `theme.css`'ten import edilen yeni stylesheet. Shell parçalarını (mask, root, head/lead/title-block, body, foot/info/actions) tanımlıyor ve `sk-dlg` PT class'ı ile scope'lanmış durumda.

#### Değiştirildi

- **`preset.ts` modal token** — `borderRadius.xl` → `borderRadius.md` (6 px), `padding: 1.25rem` → `padding: 0` (shell-level padding artık `AppDialog` içinde), drop shadow daha yumuşak iki katmanlı versiyona güncellendi (`0 24px 60px -20px ...`, `0 6px 20px -6px ...`).

#### Düzeltildi

- **Form scrollbar boşluğu (footer'ın sağında)** — `AppDialog` içindeki uzun formlarda slate-100 action bar'ın sağ kenarı ile dialog'un sağ kenarı arasında ~10 px beyaz boşluk kalıyordu (body'nin scrollbar'ı içerik genişliğini yiyordu, bar'ın `-mx-8` uzatması yalnızca body'nin content kenarına dayanıyordu). `.sk-dlg__body:has(.sk-fb--dialog)` artık scrollbar'ı görsel olarak gizliyor (`scrollbar-width: none` + `::-webkit-scrollbar { width: 0 }`); scroll wheel / trackpad / yön tuşları / Page Up–Down / Home–End ile çalışmaya devam ediyor.

#### Yükseltme

```bash
composer update lvntr/laravel-starter-kit

# Etkilenen stub'ları yeniden yayınla (DİKKAT: özelleştirilmiş stub'lar override edilir — önce diff alın)
# stubs/resources/css/theme/{_dialog.scss,_formbuilder.scss,theme.css}
# stubs/resources/js/composables/useDialog.ts
# stubs/resources/js/theme/preset.ts
php artisan vendor:publish --tag=starter-kit-stubs --force
```

**Davranış notu:** Form dialog'ları içinde Dialog body'sinin scrollbar'ı bilinçli olarak görünmez. Görünür track gizlendi ki slate-100 action bar dialog kenarına boşluksuz dayansın; scroll wheel, trackpad, yön tuşları, Page Up/Down, Home/End ile çalışmaya devam ediyor.

---

## 2026-05-19 — v13.5.7

### Yama sürüm — Dialog sticky bar sızıntısı düzeltildi, AvatarUpload yenilendi, 14px root tipografi

`AppDialog` içindeki form sticky action bar'ı (`Cancel` / `Update`), uzun formlarda alttan akan içeriği gizleyemiyordu — temel sorun, PrimeVue Dialog content'inin default `padding: 1.25rem` değerinin sticky barın altında transparan bir boşluk bırakmasıydı. Dialog content `padding-bottom` PT API ile sıfırlandı, `SkForm` artık dialog mode'da `sk-fb--dialog` marker class ekliyor ve `_formbuilder.scss` sticky barı dialog kenarına yapıştırıp `rounded-b-xl` ile alt köşelerini Dialog'un yuvarlatmasına eşliyor. `AvatarUpload` dikey kart düzeninden tek satır row layout'a (avatar · başlık/hint · butonlar) geçirildi; 56px küçültülmüş avatar, primary border vurgusu ve yeni `initials` prop'u eklendi. `title` ve `subtitle` prop'larının davranışı netleştirildi: verilmezse default i18n, dolu string → o metin, **boş string `''` → satır tamamen gizlenir** (eski geçici davranış yeniden bozmadan eski API uyumu sağlanıyor). Tipografi 14px root tabanlı rem sistemine geri çevrildi (kullanıcı tarayıcı font-size ayarları ve a11y zoom orantılı çalışıyor); önceki geçici mutlak-px override'ı kaldırıldı. Profil dikey sekmelerine description metni ve sekme başına ikon rengi eklendi.

#### Eklendi

- **Profil sekmeleri** — `Profile/Index.vue` artık her tab için `description()` ve `iconColor()` çağırıyor; `sk-profile.tab_descriptions.{general,password,security,sessions}` i18n key'leri tanıtıldı (TR/EN).
- **`AvatarUpload :initials`** — `avatarUrl` yokken avatar kutusunda kullanıcının baş harflerini gösterir; verilmezse mevcut `pi-user` fallback'i korunur.

#### Değiştirildi

- **`AvatarUpload` row layout** — avatar `size-14` boyutuna küçültüldü, primary-200 border + primary-50 zemin, "Kaldır" `severity-secondary text`, "Değiştir" `outlined`. Başlık ve hint inline basılır; `:title=""` ve/veya `:subtitle=""` ile başlık bloğu komple gizlenebilir.
- **`AvatarUpload` `title` / `subtitle` semantiği** — `undefined` → default i18n key, dolu string → birebir metin, `''` → element `v-if` ile gizli. Etiketleri tamamen kapatma yetkisi geri geldi.
- **`sk-avatar.hint`** — metni teknik formata güncellendi: `"JPG · PNG · GIF — en fazla 2 MB · 512×512 önerilir"` (EN: `"JPG · PNG · GIF — max 2 MB · 512×512 recommended"`).
- **Tipografi (14px root, rem)** — `_base.scss` artık `html { font-size: 0.875rem }` ile root'u 14px'e sabitliyor (browser default 16px'ten ölçeklenir); `utilities.css` tüm `--text-*` token'larını bu root'a göre rem cinsinden tanımlıyor (`--text-base: 1rem`, `--text-xs: 0.857rem` vb.). Geçici mutlak-px override'ı kaldırıldı, a11y zoom yine orantılı çalışıyor.
- **FileManager metin dengeleme** — favoriler/çöp boş durum başlık/altyazıları ve dosya tipi filtre pill'leri `text-lg` → `text-base`. `sk-user-menu__item` ise `text-sm` → `text-base` ile büyütüldü, yeni base ile hizalandı.

#### Düzeltildi

- **Sticky action bar sızıntısı (`AppDialog`/`SkForm`)** — Dialog içindeki uzun formlar, sticky alt barın altından scroll içeriği akıtıyordu. Çözüm üç parçalı:
  1. `AppDialog.vue` — Dialog `content` PT'sine `padding-bottom: 0` + flex column layout
  2. `SkForm.vue` — dialog mode'da `sk-fb--dialog` marker class
  3. `_formbuilder.scss` — `.sk-fb__actions` opak `var(--p-content-background)` zemin; dialog mode'da `-mx-5 px-5` ile edge-to-edge, alt köşeler `rounded-b-xl` ile Dialog'un `borderRadius.xl`'sine eşitlendi.

#### Yükseltme

```bash
composer update lvntr/laravel-starter-kit

# Etkilenen stub'ları yeniden yayınla (DİKKAT: özelleştirilmiş stub'lar override edilir — önce diff alın)
# stubs/resources/css/theme/{_base.scss,utilities.css,_formbuilder.scss,_tabs.scss,_menus.scss}
# stubs/resources/js/pages/Profile/Index.vue
# stubs/resources/js/pages/Profile/components/ProfileInfoTab.vue
# stubs/lang/{tr,en}/{sk-avatar.php,sk-profile.php}
php artisan vendor:publish --tag=starter-kit-stubs --force
```

**`AvatarUpload` davranış notu:** Eğer kodunuzda `:subtitle=""` geçip default hint'in yine de basılmasını bekliyorduysanız, artık `''` hint satırını **gizler**. Default i18n davranışını geri getirmek için prop'u tamamen kaldırın veya dolu bir string verin.

---

## 2026-05-10 — v13.5.6

### Yama sürüm — SystemHealthTab'dan axios kaldırıldı, API envelope uyumu, FileManager tip düzeltmesi

`SystemHealthController`, zorunlu `to_api()` yardımcısı yerine `response()->json()` kullanıyordu; bu, `useApi` composable'ının parse edemediği standart dışı bir JSON gövdesi (`success` envelope yok) üretiyordu. Düzeltildi. `SystemHealthTab.vue`, tüm API çağrılarının `useApi` composable üzerinden yapılması gerektiği SK kuralını ihlal ederek doğrudan axios import edip çağırıyordu; `useApi({ toast: false })` ile değiştirildi. `FileManager.vue`'daki TypeScript hatası giderildi: `busy` tipi `BusyState | null` ve vue-tsc, event handler içinde `v-if` üzerinden daraltma yapamıyor; çift isteğe bağlı zincirleme `busy?.onCancel?.()` her iki null durumunu çözüyor.

#### Düzeltmeler

- **`SystemHealthController@run`** — `response()->json()` → `to_api([...], $message)` ile değiştirildi; return tipi `ApiResponse|RedirectResponse` olarak güncellendi. Ham JSON yanıtı, `useApi`'nin beklediği standart `{ success, data, message }` envelope'unu atlıyordu ve frontend'de parse hatasına neden oluyordu.
- **`SystemHealthTab.vue`** — `import axios from 'axios'` kaldırıldı; `useApi({ toast: false })` composable eklendi. `axios.post<...>(url)` → `api.post<...>(url)` ile değiştirildi. Axios'u doğrudan kullanmak SK kuralını ihlal ediyor; tüm API çağrıları `useApi` üzerinden yapılmalı.
- **`FileManager.vue`** — `@click="busy.onCancel"` → `@click="() => busy?.onCancel?.()"` olarak düzeltildi. `busy` tipi `BusyState | null`, `onCancel` tipi `(() => void) | null`; vue-tsc event handler içinde `v-if` üzerinden hiçbirini daraltamıyor; çift isteğe bağlı zincirleme gerekli.

#### Yükseltme

```bash
composer update lvntr/laravel-starter-kit

# Etkilenen stub'ları yeniden yayınla (DİKKAT: özelleştirilmiş stub'lar override edilir — önce diff alın)
# SystemHealthController.php, SystemHealthTab.vue
php artisan vendor:publish --tag=starter-kit-stubs --force
```

---

## 2026-05-07 — v13.5.5

### Yama sürüm — System Health Settings'e taşındı, paylaşım iptali UUID düzeltmesi, kurulum güvenilirliği

System Health artık bağımsız bir admin sayfası değil; içeriği `SystemHealthTab.vue` içinde PrimeVue `Card` ile sarılmış bir Settings sekmesi olarak sunuluyor. Admin kenar menüsü girişi kaldırıldı. Bir migration hatası düzeltildi: `file_manager_share_revocations` tablosundaki `revoked_by_user_id` kolonu `unsignedBigInteger` olarak tanımlanmıştı ancak users tablosu UUID primary key kullanıyor; kolon artık doğru şekilde `uuid` olarak beyan ediliyor. `InstallCommand`'a `composer dump-autoload` çalışmadan önce `app/Helpers/custom.php` dosyasını otomatik oluşturan bir guard eklendi; bu dosyanın yokluğu temiz kurulumda her artisan çağrısını kırıyordu. `ApiClientsManageTab` ve `ApiTokensManageTab` stub'ları el yapımı `<header>` bloklarını `DatatableBuilder` kart API'si (`isCard`, `cardTitle`, `cardSubtitle`, `create()`) lehine bıraktı. `SkDatatable` kart başlığı artık doğru body padding'ine sahip; başlık ve altyazı standart Card body ritmiyle hizalanıyor.

#### Değişiklikler

- **System Health Settings sekmesine taşındı.** `/admin/system-health` bağımsız sayfası, bir Settings sekmesiyle değiştirildi. `useAdminMenu.ts`'teki kenar menüsü girişi ve `system-health` route import'u kaldırıldı. `SystemHealthTab.vue` artık PrimeVue `Card` içinde title, subtitle ve content slot'larıyla sarılı; yenile butonu `#title` slot'una `size="small"` ile yerleştirildi.
- **`SystemHealthController@run`** — `back()` kullanımına geri döndürüldü. v13.5.4'te eklenen `redirect()->route('admin.system-health.index')`, System Health artık Settings sayfasının içinde olduğu için anlamsız hale geldi.
- **`ApiClientsManageTab.vue` / `ApiTokensManageTab.vue`** — özel `<header>` bloğu ve bağımsız `Button` import'u, tablo builder üzerindeki `isCard(true).cardTitle(...).cardSubtitle(...)` ile değiştirildi; create action `tableBuilder.create({ label, onClick })` ile kaydediliyor, tam kart düzeni artık `DatatableBuilder` tarafından yönetiliyor.

#### Düzeltmeler

- **`file_manager_share_revocations` migration** — `revoked_by_user_id` kolonu, `users` tablosundaki UUID primary key ile uyumlu olması için `unsignedBigInteger`'dan `uuid`'e değiştirildi. v13.5.3'ten yükseltiyorsanız aşağıdaki migration notuna bakın.
- **`ShareRevocation` modeli** — `$revoked_by_user_id` PHPDoc tipi `int|null`'dan `string|null`'a düzeltildi.
- **`InstallCommand`** — `app/Helpers/custom.php` yoksa `composer dump-autoload`'dan önce otomatik oluşturuluyor (minimal `<?php` stub). Bu dosyanın yokluğu temiz kurulumda sonraki her artisan çağrısını kırıyordu.
- **`DatabaseTestCase`** — bellek içi `file_manager_share_revocations` şeması, düzeltilen migration ile uyumlu olması için `revoked_by_user_id` alanında `uuid` kullanacak şekilde güncellendi.

#### UI

- **`SkDatatable`** — `isCard` modunda `caption` PT slot'u artık `padding: var(--p-card-body-padding) var(--p-card-body-padding) 0` alıyor; başlık ve altyazı standart Card body hizalamasına oturuyor, tablo araç çubuğu ve içerik tam genişlikte kalmaya devam ediyor.

#### Yükseltme

```bash
composer update lvntr/laravel-starter-kit

# Etkilenen stub'ları yeniden yayınla (DİKKAT: özelleştirilmiş stub'lar override edilir — önce diff alın)
# useAdminMenu.ts, SystemHealthController.php, SystemHealthTab.vue,
# ApiClientsManageTab.vue, ApiTokensManageTab.vue
php artisan vendor:publish --tag=starter-kit-stubs --force
```

**Migration notu** — `file_manager_share_revocations` tablosunu v13.5.3'te yayınladıysanız kolon tipini düzeltmek için yeni bir migration çalıştırın:

```php
Schema::table('file_manager_share_revocations', function (Blueprint $table) {
    $table->dropForeign(['revoked_by_user_id']);
    $table->dropColumn('revoked_by_user_id');
    $table->uuid('revoked_by_user_id')->nullable()->after('revoked_at');
    $table->foreign('revoked_by_user_id')->references('id')->on('users')->nullOnDelete();
});
```

Bu sürümde yeni izin, config anahtarı veya davranış değişikliği bulunmuyor.

---

## 2026-05-07 — v13.5.4

### Yama sürüm — v13.5.3 sonrası stub düzeltmeleri, tip uyumlulukları ve CI pipeline iyileştirmeleri

Bu yama sürüm, v13.5.3 sonrası ortaya çıkan stub regresyonlarını gideriyor: `AdminHeader`'da `role` yazım hatası, eksik System Health menü item'ı, `SettingsDefaultsQuery`'de `storage_usage` payload eksikliği, `SystemHealthController`'da yanlış redirect hedefi ve Logs sayfalarındaki `trans()` count tipi sorunları. TabBuilder `rose` icon rengini kazandı (System Health sekmesinin derlenmesi için gerekli). CI pipeline'ı yeniden sıralandı: `auto-imports.d.ts` ve `components.d.ts` artık typecheck'ten önce üretiliyor; `vite.config.ts`'e PHP olmayan ortamlar (Wayfinder) ve Vitest çalıştırması (laravel-vite-plugin HMR kontrolü) için yeni guard'lar eklendi. Yeni izin, migration veya config anahtarı yok.

#### Eklenenler

- **TabBuilder — `rose` icon rengi.** `TabIconColor` artık `rose`'u kabul ediyor; `_tabs.scss`'e karşılık gelen `--p-rose-*` light/dark kuralları eklendi. Settings'teki System Health sekmesi bunu kullanıyor.

#### Düzeltmeler

- **`AdminHeader.vue`** — `page.props.auth?.role` (singular, var olmayan) `roles?.[0]`'a düzeltildi; shared page-prop `roles: string[]` shape'iyle uyumlu.
- **`useAdminMenu.ts`** — eksik `import systemHealth from '@/routes/system-health'` ve System Health menü item'ı (`permission: 'system.health.view'`) eklendi. v13.5.3 sayfasına yalnızca URL üzerinden erişilebiliyordu.
- **`SettingsDefaultsQuery.php`** — `storage_usage` (`used_bytes`, `quota_bytes`) payload'ı `ResolvesMediaModel` trait'i (`computeStorageUsed()` / `storageQuotaBytes()`) üzerinden eklendi. v13.5.2'de gelen `StorageQuotaCard`'ı besliyor.
- **`SystemHealthController@run`** — `back()` yerine `redirect()->route('admin.system-health.index')` (POST → güvenli GET).
- **`Admin/Logs/{Index,Show}.vue`** — `trans()`/`$t()` `count` parametreleri `String(...)` ile sarmalandı; `laravel-vue-i18n` v2.8 strict tipi number değer kabul etmiyordu.
- **`tsconfig.json`** — `@lvntr/components/*` path mapping Vite alias'ıyla hizalandı; `@lvntr/components/FormBuilder/core` gibi yollar artık vue-tsc altında çözümleniyor.
- **`env.d.ts`** — `window.turnstile` için tipli global Window genişletmesi ve `@/routes/*` wildcard module declaration (wayfinder dosyaları henüz üretilmediğinde fallback) eklendi.

#### Build / CI

- **`vite.config.ts`** — `isWayfinderAvailable()` `artisan` yoksa wayfinder plugin'ini atlar (CI / paket reposu); `isVitest` guard'ı `vitest run` sırasında `laravel-vite-plugin` ve `inertia()`'yı atlar (CI'da "Vite HMR server" startup hatası giderildi).
- **GitHub Actions Node job yeniden sıralandı** — `npm ci` → vendor symlink → route stub generation → build → typecheck → lint (`continue-on-error`) → test. Build artık `auto-imports.d.ts` ve `components.d.ts`'i vue-tsc çalışmadan önce üretiyor.
- **`scripts/ci/generate-route-stubs.mjs`** — node-only CI fallback'i; 16 `@/routes/*` modülü için minimal stub dosyaları yazar. Çıktı dizini gitignore'lı; host app'lerde wayfinder gerçek dosyaları üretmeye devam ediyor.
- **Doctor testleri** — kasıtlı olarak İngilizce kalan check mesajları için beklentiler güncellendi.
- **`.gitignore`** — wayfinder routes dizini, CI vendor symlink ve Vite build artifact'ları (`stubs/public/build/`, `stubs/bootstrap/ssr/`) eklendi.

#### Yükseltme

```bash
composer update lvntr/laravel-starter-kit

# Etkilenen stub'ları yeniden yayınla (DİKKAT: özelleştirilmiş stub'lar override edilir — önce diff alın)
# AdminHeader.vue, useAdminMenu.ts, SystemHealthController.php, SettingsDefaultsQuery.php,
# Logs/{Index,Show}.vue, env.d.ts, tsconfig.json, vite.config.ts
php artisan vendor:publish --tag=starter-kit-stubs --force

# Yeni rose tab color CSS'ini içeren tema dosyalarını yayınla
php artisan vendor:publish --tag=starter-kit-theme --force

npm run build
```

Özelleştirilmiş bir `tsconfig.json` kullanıyorsanız `@lvntr/components/*` mapping'ini elle ekleyin (`@lvntr/*`'dan önce gelmeli):

```json
"paths": {
    "@/*": ["resources/js/*"],
    "@lvntr/components/*": [
        "vendor/lvntr/laravel-starter-kit/resources/js/components/Lvntr-Starter-Kit/*"
    ],
    "@lvntr/*": ["vendor/lvntr/laravel-starter-kit/resources/js/*"]
}
```

Yeni izin, migration veya config anahtarı yok.

---

## 2026-05-06 — v13.5.3

### Sürüm — sk:doctor, System Health, Signed Share Link, Bulk Action API, API Client Admin UI, güvenlik güncellemeleri ve hata düzeltmeleri

Bu sürüm `sk:doctor` sağlık kontrol komutunu ve System Health admin sayfasını, HMAC imzalı dosya paylaşım bağlantılarını, DatatableBuilder için cross-page Bulk Action API'sini, Domain Generator v2 opt-in flag'lerini ve tam Passport API Client & Token admin arayüzünü ekliyor. Güvenlik bağımlılık güncellemeleri, iç içe klasör silme için event-dispatch düzeltmeleri, bulk controller'lar için Inertia flash response düzeltmeleri ve UUID/ULID bulk-action ID desteği de bu sürüme dahildir. Mevcut uygulamalar aşağıdaki yükseltme adımlarını uygulamalı.

#### Eklenenler

- **`sk:doctor` artisan komutu** — 12 kontrol noktasını kapsayan sistem sağlık denetimi: PHP extension'ları, veritabanı bağlantısı, Redis, Passport anahtarları, storage symlink, yazılabilir dizinler, queue driver, schedule çalışması, mail driver, npm build artifact'ları, config cache, FileManager disk bağlantısı. `--json` ile makine okunabilir çıktı; `--only=database,redis,...` ile seçili kontroller çalıştırılabilir. Exit kodları: `0` OK, `1` WARN, `2` FAIL.
- **Admin Panel — System Health sayfası** (`/admin/system-health`) — `sk:doctor` çıktısını UI'da görselleştirir; kontrol başına durum rozeti ve manuel yenile butonu. Erişim izni: `system.health.view`.
- **File Manager — Signed Share Link** — HMAC imzalı genel erişim URL'leri. `POST /file-manager/share` ile TTL belirterek paylaşım oluşturulur; `POST /file-manager/share/revoke` ile iptal edilir; `GET /file-manager/share/{media}?expires&signature` ile doğrulama yapılır. Config anahtarları: `file-manager.share.enabled`, `default_ttl_hours` (varsayılan 24), `max_ttl_hours` (varsayılan 720), `allow_revoke`. Token iptali `file_manager_share_revocations` tablosunda `(media_id, signed_token_hash)` composite unique index ile yönetilir. Yeni izinler: `share-media`, `revoke-share-media`.
- **DatatableBuilder — Bulk Action API** — `BulkAction` interface ve `BulkActionDispatcher` ile sayfa sınırını aşan toplu işlem desteği. `SkDatatable`, `select_all_filtered` modunu (filtre snapshot ile) ve cross-page seçimi destekler. Request payload: `{action, ids, select_all_filtered, filter_snapshot}`; response: `{processed, skipped, failed, message}`. Stub örnekleri: `BulkDeleteUserAction` (rank-aware) ve `BulkDeleteRoleAction` (sistem rollerine karşı koruma).
- **Domain Generator v2 (`make:sk-domain`) — opt-in flag'ler** — `--with-policy`, `--with-factory`, `--with-seeder`, `--with-test`, `--with-relations` tek tek ya da `--with=policy,factory,test` toplu syntax ile kullanılabilir. `--relations="belongsTo:User,hasMany:Comment,morphTo:commentable"` ile ilişki scaffold'ı otomatik üretilir. Flag'siz çağrım v13.5.x davranışını korur (geriye dönük uyumlu).
- **API Client & Token Admin UI** — Passport authorization_code ve client_credentials grant'leri ile Personal Access Token yönetimi için admin arayüzü (`/admin/api-clients`, `/admin/api-tokens`). Client secret ve PAT plaintext yalnızca oluşturma response'unda bir kez gösterilir (`Cache-Control: no-store`); `OneTimeSecretModal` dismiss edilemez. Yeni izinler: `api-clients.create`, `api-clients.read`, `api-clients.update`, `api-clients.delete`, `api-tokens.create`, `api-tokens.read`, `api-tokens.delete`. Yeni validation rule: `HttpsOrLocalhostUrl` (RFC 8252 §8.3 — yalnızca HTTPS, localhost istisnası ile HTTP).
- **CI Workflow (GitHub Actions)** — PHP test (`pest`), lint (`pint`), Node 22 build/typecheck/lint job'ları. Aynı branch/PR'da eş zamanlı çalışan job'lar `concurrency: cancel-in-progress` ile iptal edilir.
- `composer test` (`vendor/bin/pest tests/Feature`) ve `composer lint` (`vendor/bin/pint --test`) script'leri katkıda bulunanlar için eklendi.

#### Düzeltmeler

- **`DeleteFolderAction`** — alt klasörler, Eloquent model event'lerini atlayan query-builder `forceDelete()` çağrısıyla kalıcı siliniyordu. `FileFolder` modelindeki `forceDeleted` gözlemcisi (favori kayıtlarını temizlemekten sorumlu) alt klasörler için hiç tetiklenmiyordu; bu da `file_favorites` tablosunda sahipsiz kayıtlar bırakıyordu. Model bazlı iterasyona geçildi, artık her `forceDeleted` event'i doğru şekilde tetikleniyor.
- **`sk:update` — `node_modules/` stubs taramasından filtrelendi.** `NEVER_UPDATE_PATHS` sabitine `node_modules/` eklendi; `isNeverUpdate()` kontrolü `updateModifiableFiles`, `addNewFiles`, `migrateHashRegistry` ve `updateHashRegistry` döngülerinin tamamına uygulandı. Sembolik link (path repository) ortamında `stubs/node_modules/` varlığı aday dosya listesine sızıyordu.
- **`sk:doctor` ve `sk:update` console çıktısı İngilizceye çevrildi.** `DoctorCommand`, `UpdateCommand` ve 12 `DoctorCheck` sınıfındaki tüm kullanıcıya gösterilen mesajlar, ipuçları ve tablo başlıkları İngilizce; PHP kod yorumları değiştirilmedi.
- **Bulk action controller'lar — Inertia flash response.** `UserBulkController` ve `RoleBulkController` artık `ApiResponse` (JSON) yerine `back()->with('success'/'error', ...)` döndürüyor. Önceki JSON response Inertia'nın `onSuccess`/`onError` akışını kırıyor, ham JSON'u ekrana basıyordu; başarı/hata mesajları artık `HandleInertiaRequests` flash paylaşımı üzerinden `SkFlash`/`useFlash` bileşenine ulaşıyor.
- **Bulk action validasyonu — UUID/ULID/integer ID desteği.** `BulkActionRequest::rules()` güncellendi: `ids.*` kuralı `integer`'dan `string|min:1|max:64`'e değiştirildi; `prepareForValidation()` tüm ID'leri string'e cast ediyor. Önceki `integer` kuralı `HasUuids` kullanan modellerde (User, FileBucket, FileFolder vb.) "The ids.0 field must be an integer" hatasına yol açıyordu. Yeni kural integer auto-increment, UUID (36 karakter) ve ULID (26 karakter) primary key'leri tek payload şemasında destekler.

#### Güvenlik

- **`dedoc/scramble`** `^0.13`'ten `^0.13.22`'ye yükseltildi — v0.13.22'de giderilen RCE sınıfı advisory (GHSA) için.
- **`phpseclib/phpseclib`** `3.0.51`'den `3.0.52`'ye güncellendi — `laravel/passport` üzerinden gelen yüksek önem dereceli DoS advisory için.
- **Signed Share Link — cross-media token hijack koruması.** `(media_id, signed_token_hash)` composite unique index, bir token'ın farklı media kayıtlarında geçerli sayılmasını engeller.
- **Personal Access Token — privilege escalation guard.** `user_id` body alanı kabul edilmez; token her zaman kimliği doğrulanmış kullanıcı için mint edilir.
- **Passport client `confidential` zorunluluğu.** API Client UI üzerinden yalnızca `confidential=true` client oluşturulabilir; authorization_code grant için min:1 redirect URI ve HTTPS zorunlu. Mevcut DB kayıtları etkilenmez.

#### Değişiklikler

- **`StarterKitServiceProvider`** — Passport scope ve `Gate::before` kayıtları tek kaynak haline getirildi; `AppServiceProvider` stub'ından duplicate kayıtlar kaldırıldı.

#### Yükseltme

```bash
composer update lvntr/laravel-starter-kit

# Yeni migration'ları yayınla ve çalıştır
php artisan vendor:publish --tag=starter-kit-migrations
php artisan migrate

# Yeni share.* anahtarlarını içeren file-manager config'ini yayınla
php artisan vendor:publish --tag=starter-kit-config --force

# Yeni admin sayfa ve controller stub'larını yayınla
# DİKKAT: özelleştirilmiş stub'lar override edilir — önce diff alın
php artisan vendor:publish --tag=starter-kit-stubs --force

# Yeni izinleri ekle ve permission cache'ini temizle
php artisan db:seed --class=PermissionResourcesSeeder
php artisan permission:cache-reset
```

**Yeni izinler:** `system.health.view`, `share-media`, `revoke-share-media`, `api-clients.create`, `api-clients.read`, `api-clients.update`, `api-clients.delete`, `api-tokens.create`, `api-tokens.read`, `api-tokens.delete`.

**Davranış değişiklikleri:**

- `confidential=false` ile authorization_code Passport client'ları UI üzerinden artık oluşturulamaz. Mevcut DB kayıtları etkilenmez.
- Personal Access Token mint: `user_id` body alanı kaldırıldı; admin başka kullanıcı adına PAT oluşturmak istiyorsa artisan komutu veya özel action kullanılmalıdır.
- `AppServiceProvider` stub'ında duplicate Passport scope / `Gate::before` bloğu varsa silinmeli; `StarterKitServiceProvider` üzerinden çalışmaya devam eder.

---

## 2026-05-06 -v.13.5.2

### Yama sürüm — Ayarlar güvenlik sekmesi birleştirmesi, FileManager geri yükleme düzeltmesi ve i18n iyileştirmeleri

Ayarlar paneli Kimlik Doğrulama ve Turnstile sekmelerini tek **Güvenlik** sekmesinde birleştirdi; disk kullanımını görselleştiren bir Depolama Kotası kartı eklendi. File Manager çöp kutusu geri yükleme hatası giderildi: çöp kutusu artık yalnızca kök seviyedeki silinmiş öğeleri gösteriyor, böylece tekil ve toplu geri yükleme işlemleri "parent in trash" hatasıyla karşılaşmıyor. Tüm File Manager bileşenlerindeki metin boyutları `text-lg` (14 px) olarak standardize edildi, onay diyalogları `trans()` üzerinden çevrildi ve filtre pill etiketleri i18n'e geçirildi. Mevcut uygulamalar `composer update lvntr/laravel-starter-kit && php artisan sk:update && npm run build` çalıştırmalı.

#### Added

- **`SecurityTab.vue`** Kimlik doğrulama ve Cloudflare Turnstile ayarlarını tek sekmede birleştirir; kaldırılan `AuthTab.vue` ve `TurnstileTab.vue` stub'larının yerini alır.
- **`StorageQuotaCard.vue`** Ayarlar panelinde disk-genel depolama kotası kullanımını progress bar ile gösterir.
- **`SettingsDefaultsQuery`** artık Inertia payload'ında `storage_usage` (`used_bytes`, `quota_bytes`) döndürür.
- **i18n key'leri eklendi** — `sk-setting` (güvenlik/depolama bölüm etiketleri), `sk-file-manager` (filtre pill etiketleri: `all`, `image`, `video`, `pdf`, `audio`, `archive`) ve `sk-common` (onay diyalog string'leri).
- **`config('file-manager.settings.enable_trash')`** — FileManager genelinde soft-delete vs hard-delete davranışını kontrol eden yeni config key'i. `true` (varsayılan) silinen dosya ve klasörleri Çöp Kutusu'na gönderir; `false` anında kalıcı olarak siler. `DeleteFileAction` ve `DeleteFolderAction` silme anında bu config'i okur. Değer Inertia üzerinden otomatik paylaşılır (`fileManagerSettings.enable_trash`); Vue bileşeni `:enable-trash` prop'u verilmediğinde config değerine geri döner. Prop yine de instance bazında override için geçilebilir.

#### Fixed

- **Çöp kutusu geri yükleme hatası.** `TrashContentsQuery` artık yalnızca kök seviyedeki silinmiş öğeleri döndürüyor. Üst klasörü de çöp kutusunda olan öğeler bağımsız öğe olarak listeleniyordu ve tekil/toplu geri yüklemede "Cannot restore: the parent folder is also in trash" hatasına yol açıyordu. Kök filtresiyle geri yükleme işlemleri her zaman ağacın tepesinden başlar.

#### Changed

- **FileManager minimum metin boyutu** `text-lg` (14 px) olarak `FileManager.vue`, `FileGrid.vue`, `FileManagerSidebar.vue` ve `FileManagerStats.vue` genelinde standardize edildi.
- **`useConfirm` composable** — onay diyalog string'leri yeni `sk-common` çeviri key'lerini kullanan `trans()` çağrılarına taşındı.
- **`Admin/Files/Index.vue`** sadeleştirildi — gereksiz sarıcı `<div>` kaldırıldı.
- **File Manager sekmesi** — Video/Audio yükleme toggle'ları artık görsel açıdan Images ile aynı checkbox-grid tasarımını kullanıyor.

#### Removed

- **`AuthTab.vue`** ve **`TurnstileTab.vue`** stub'ları — içerik `SecurityTab.vue`'a taşındı. `sk:update`, `DEPRECATED_PATHS` üzerinden otomatik temizler.

#### Upgrade

```bash
composer update lvntr/laravel-starter-kit
php artisan sk:update
npm run build
```

---

## 2026-05-05 -v.13.5.1

### Yama sürüm — NPM exports düzeltmesi, sk:publish iyileştirmeleri, depolama kotası ve yükleme validasyonu

NPM paketi `main` ve `exports` path'leri gerçek dosya yapısıyla eşleştirildi. `sk:publish` bireysel tag'leri artık doğru çalışıyor. Admin Settings > File Manager panelinden GB cinsinden depolama kotası ayarlanabilir; kota aşıldığında yükleme istekleri yerelleştirilmiş hata ile 422 döndürür. Mevcut uygulamalar `composer update lvntr/laravel-starter-kit && php artisan sk:update && npm install && npm run build` çalıştırmalı.

#### Fixed

- **NPM paketi `main` ve `exports` path'leri** artık gerçek dosya yapısını yansıtıyor (`resources/js/components/Lvntr-Starter-Kit/...`). FileManager export'u eklendi.
- **`sk:publish` bireysel tag'leri** (`form`, `datatable`, `tabs`, `skeleton`, `ui`) eski yapıya göre kırık source path'lere sahipti; `Lvntr-Starter-Kit/` segment'i ile düzeltildi.
- **`vendor:publish --tag=starter-kit-components` iç içe path bug'ı** giderildi. Önceki: `resources/js/components/Lvntr-Starter-Kit/Lvntr-Starter-Kit/...`. Şimdi: doğrudan `resources/js/components/Lvntr-Starter-Kit/`.
- **`vendor:publish --tag=starter-kit-file-manager-components`** artık aktif. Source path eski dizin adına işaret ediyordu (`file-manager`); gerçek dizin yapısıyla (`Lvntr-Starter-Kit/FileManager`) hizalandı.
- **`index.ts` barrel'da eksik 9 component export eklendi:** `EditorInput`, `EditorImagePicker`, `EditorColorPalette`, `TranslatableInput`, `ImageLightbox`, `FilePreviewModal`, `ToggleFeatureCard`, `MimePickerField`, `SkTag`.

#### Added

- **`sk:publish --tag=filemanager`** — FileManager UI'ını ayrıca yayınlamak için yeni tag.
- **`sk:install --without-ai-skill`** — Claude Code skill bundle kullanmayan consumer'lar için `stubs/.claude/skills/` yayınını atla.
- **`.gitattributes`** — Composer arşivi artık `tests/`, `docs/`, `.github/`, `plan-docs/`, `package-audit-notes/` vb. geliştirme dosyalarını dışlıyor; arşiv boyutu küçüldü.
- **`.npmignore`** — NPM paketi `__tests__/`, `*.spec.*`, `*.test.*` dosyalarını dışlıyor (root ve alt dizinler; npm 11 davranışıyla uyumlu).
- **Disk-genel depolama kotası (`storage_quota_gb`).** Admin Settings > File Manager panelinden GB cinsinden tek kota değeri tanımlanır (varsayılan 10 GB). Tüm context'leri (`user`, `global`, özel morph map girişleri) ve çöp kutusunu (`withTrashed`) kapsar.
- **Upload kota validasyonu.** `UploadFileRequest::withValidator()` kota kontrolü ekler; kota aşıldığında HTTP 422 ve yerelleştirilmiş `errors.quota_exceeded` mesajı döner.

#### Removed

- **Stub'dan duplike vendor-owned domain command'ları silindi:** `EnvSyncCommand`, `MakeDomainCommand`, `RemoveDomainCommand`. Vendor'dan tek kaynak olarak çalışmaya devam eder. `sk:update` mevcut consumer projelerde `DEPRECATED_PATHS` ile otomatik temizler.
- **`App\Http\Responses\ApiResponse.php` stub'ı silindi.** `StarterKitServiceProvider` alias guard'ı (`App\Http\Responses\ApiResponse` → `Lvntr\StarterKit\Http\Responses\ApiResponse`) consumer dosyası silindikten sonra otomatik devreye girer; mevcut `use App\Http\Responses\ApiResponse;` import'ları değişmeden çalışır.
- **`Lvntr\StarterKit\Enums\PermissionEnum` vendor'dan silindi.** Resmi konum `App\Enums\PermissionEnum` (stubs altında). Vendor'da referans yoktu (grep onayladı). Kodunuz doğrudan bu namespace'i import ediyorsa `App\Enums\PermissionEnum`'a güncelleyin.

#### Changed

- **`sk:publish` primary publish komutu olarak konumlandırıldı.** Granular interactive flow ve namespace rewrite desteği. `vendor:publish --tag=starter-kit-*` BC için korunur; `sk:publish` artık install ve komut dokümantasyonunda öne çıkar.
- **`ResolvesMediaModel::computeStorageUsed()` imzası değişti (internal trait).** Parametre almaz hale geldi; `Media::withTrashed()->sum('size')` ile disk-genel toplam döndürür. Önceki davranış `model_type` + `model_id` filtreli per-context hesaplamaydı. Bu trait'i extend edip `computeStorageUsed($context)` çağırıyorsanız parametreyi kaldırın.
- **`FolderContentsQuery`, `FavoritesContentsQuery`, `TrashContentsQuery`** — `stats.storage_quota` alanı byte cinsinden eklendi.
- **`FileManager.vue`** — `STORAGE_QUOTA_BYTES` hardcoded sabiti kaldırıldı; `quotaBytes` computed değeri `stats.storage_quota`'dan okunuyor. Kota sıfır veya tanımsızsa sidebar `v-if="quotaBytes > 0"` ile gizlenir.

#### Upgrade

```bash
composer update lvntr/laravel-starter-kit
php artisan sk:update
```

`sk:update` çıktısında "Removed" listesinde 4 path görünecek — bu beklenen davranış.

---

## 2026-05-05 -v.13.5.0

### Major sürüm — Vendor-first runtime ve frontend UI lib taşıması

Starter kit runtime tamamen vendor'a taşındı. FileManager backend, paylaşılan base sınıflar, trait'ler, helper'lar, middleware'ler, ApiResponse ve route loader artık `vendor/lvntr/laravel-starter-kit/src/` altında `Lvntr\StarterKit\` namespace'iyle çalışıyor. Frontend bileşen kütüphanesi (`DatatableBuilder`, `FormBuilder`, `TabBuilder`, `FileManager`, `Skeleton`, `ui`) de artık paketin canonical konumunda, app tarafı vendor symlink üzerinden tüketiyor. Mevcut uygulamalar yalnızca `composer update` çalıştırmalı; hiçbir dosya değişmez, rota adı kırılmaz, `php artisan migrate` "Nothing to migrate" döner. Frontend geçişi tamamen isteğe bağlıdır. Yükseltme talimatları: [UPGRADE.md](UPGRADE_.md).

#### Changed

- **Vendor-first yapıya geçildi.** Paket runtime artık stub akışından değil, doğrudan `vendor/` altından çalışıyor. `sk:install` iskelet dosyalarını (auth, layout, user/rol/ayar domain, config) publish eder; FileManager ve Shared katmanlarını `app/` dizinine kopyalamaz.
- **`sk:update` basitleştirildi.** Vendor runtime için kopyalama yapmıyor; `composer update` yeterli. Hash takipli stub'lar (auth/layout/user/rol/ayar) için mevcut davranış korundu.
- **Frontend UI lib taşındı.** `resources/js/components/Lvntr-Starter-Kit/{DatatableBuilder,FormBuilder,TabBuilder,FileManager,Skeleton,ui,index.ts}` artık paketin canonical konumudur. App tarafı vendor symlink üzerinden tüketir.
- **`stubs/vite.config.ts` alias güncellendi.** Yeni install için `@lvntr/components` alias'ı `vendor/lvntr/laravel-starter-kit/resources/js/components/Lvntr-Starter-Kit` path'ini kullanır; `preserveSymlinks: true`; `Components({ dirs })` array'inde vendor path mevcut.
- **`FileManagerAction` abstract base + `ResolvesMediaModel` trait.** `media-library.media_model` config'i üzerinden Media model resolve eder. App-specific `App\Models\Media` overrider'ları (örn. SoftDeletes ile) backward compatible çalışır.
- **`Http/Requests/FileManager/UploadFileRequest`.** Protected method'lar — app tarafında override edilebilir (Setting entegrasyonu vb.).

#### Added

- **`src/Domain/FileManager/`** — Actions, DTOs, Queries, Services, Support `Lvntr\StarterKit\Domain\FileManager\` namespace'iyle vendor'da.
- **`src/Domain/Shared/`** — BaseAction, BaseDTO, ActionPipeline, PipeableAction `Lvntr\StarterKit\Domain\Shared\` namespace'iyle vendor'da.
- **`src/Traits/`** — HasActivityLogging, HasMediaCollections `Lvntr\StarterKit\Traits\` namespace'iyle vendor'da.
- **`src/sk-helpers.php`** — `to_api()`, `definition()`, `definitionLabel()`, `sk_locale_keys()`, `sk_default_locale()`, `format_date()` fonksiyonları `function_exists` guard'larıyla vendor'da.
- **`src/Http/Responses/ApiResponse.php`** — `{success, status, message, data, errors?}` envelope formatı korunarak vendor'a taşındı.
- **`src/Http/Middleware/`** — CheckResourcePermission, SecurityHeaders `Lvntr\StarterKit\Http\Middleware\` namespace'iyle vendor'da.
- **`src/Http/Controllers/FileManagerController.php`** ve **`src/Http/Requests/FileManager/*`** — vendor'da.
- **`src/Console/Commands/PurgeFileManagerTrashCommand.php`** — `file-manager:purge-trash` signature AYNEN korundu.
- **`src/Exceptions/`** — ApiException, ApiExceptionHandler vendor'da.
- **`src/Facades/FileManager.php`** — `FileManager::routes()` ile tek satır route mount.
- **`src/routes/file-manager.php`** — 19 route, isimler AYNEN. Consumer'ın kendi route dosyası varsa vendor mount edilmez.
- **`database/migrations/`** — 3 FileManager migration, dosya adı ve içerik AYNEN korundu.
- **`config/file-manager.php`** — `models.*` ve `settings.*` key'leri eklendi.

#### Deprecated

- **`sk:sync` (PackageSyncCommand).** Composer path symlink workflow'unda gereksiz hale geldi. `--force` ile escape hatch korunur.

#### Upgrade

Güncelleme sonrası yeterli:

```bash
composer update lvntr/laravel-starter-kit
php artisan migrate
```

Mevcut `app/Domain/FileManager/`, `app/Domain/Shared/`, `app/Traits/`, `app/Helpers/sk-helpers.php` gibi dosyalar yerinde kalır ve çalışmaya devam eder. Bu dosyaları vendor versiyonuyla değiştirmek tamamen isteğe bağlıdır. Frontend cleanup (Vite alias'ını vendor path'e yönlendirme ve app tarafındaki kopyayı silme) da opt-in'dir. Her iki rehber için bkz. [UPGRADE.md](UPGRADE_.md).

---

## 2026-05-04 -v.13.4.10

### Minor sürüm — Çevrilebilir FormBuilder alanları ve Sample Contents referans modülü

FormBuilder artık çok dilli metin alanlarını kutudan çıktığı gibi destekliyor. Üç yeni builder — `FB.translatableText()`, `FB.translatableTextarea()` ve `FB.translatableEditor()` — aktif her dil için ayrı input render eder ve Spatie Translatable modelleriyle uyumlu JSON locale map'i submit eder. Bu sürüm ayrıca validation, datatable arama/sıralama ve resource çıktısı için backend helper'ları ve tüm pattern'i uçtan uca gösteren Sample Contents modülünü ekler. Mevcut uygulamalar `composer update lvntr/laravel-starter-kit && php artisan sk:update && php artisan migrate && npm install && npm run build` çalıştırmalı.

#### Added

- **Çevrilebilir FormBuilder alanları.** `FB.translatableText()`, `FB.translatableTextarea()` ve `FB.translatableEditor()`, aktif dil listesine göre locale bazlı input'lar render eder. Locale filtreleme (`onlyLocales`, `exceptLocales`), inline/tab layout ve locale label stilleri (`badge`, `name`, `flag`) desteklenir.
- **Backend translatable helper'ları.** `HasTranslatableRules`, FormRequest kurallarını ve validation label'larını locale bazında üretir. `TranslatableQueryHelpers`, JSON kolon araması, locale-aware sıralama ve datatable/edit form için `resourceShape()` çıktısı sağlar.
- **Locale helper fonksiyonları.** `sk_locale_keys()` aktif locale kodlarını sırayla döndürür; `sk_default_locale()` primary locale'i çözer ve gerekirse `app.fallback_locale` değerine düşer.
- **Sample Contents modülü.** Translatable model, migration, factory, domain action/event/listener'ları, FormRequest'ler, resource, datatable query, Vue sayfaları ve menü/yetki kayıtlarıyla tam bir admin CRUD referansı gelir.
- **Dokümantasyon.** Yeni [Translatable Fields](./translatable-fields.md) ve [Çevrilebilir Alanlar](./translatable-fields.tr.md) rehberleri backend/frontend akışını, migration stratejisini ve Sample Contents referans implementasyonunu anlatır.
- **Paket bağımlılığı.** JSON tabanlı çevrilebilir attribute'lar için `spatie/laravel-translatable` artık uygulama dependency set'ine dahil.

#### Improved

- **FormBuilder dokümanı.** FormBuilder rehberi translatable builder'ları listeler ve özel rehbere bağlanır.
- **Çöp kutusuz File Manager dokümanı.** File Manager rehberi, `enableTrash=false` durumunda tekil ve toplu silmelerin kalıcı silmeye yönlendiğini ve bulk delete için `force_delete=true` gönderildiğini açıklar.
- **Lvntr builder skill dokümanları.** Proje ajan rehberi FormBuilder translatable alanlarını kapsar; gelecekte üretilen admin formlar desteklenen API'yi kullanır.

#### Upgrade

Güncelleme sonrası migration ve frontend build çalıştırın:

```bash
composer update lvntr/laravel-starter-kit
php artisan sk:update
php artisan migrate
npm install
npm run build
```

Kendi dil/settings akışını özelleştirmiş uygulamalar `general.languages` üzerinden okunan aktif dil listesini doğrulamalı. Mevcut düz string kolonlar otomatik taşınmaz; bir model attribute'unu Spatie `HasTranslations` altına almadan önce kolonları aşamalı migration ile JSON'a çevirin.

## 2026-05-02 -v.13.4.9

### Minor sürüm — Dosya Yöneticisi favoriler, çöp kutusu, geri yükleme, kalıcı silme, kopyalama ve yeniden adlandırma

Dosya Yöneticisi’nde v13.4.8’de placeholder olarak görünen yüzeyler artık gerçek özelliklere dönüştü. Favoriler ve Çöp Kutusu gerçek hızlı-erişim görünümleri; klasör/dosya tile’ları yıldızlanabiliyor; silinen öğeler varsayılan olarak çöp kutusuna taşınıyor; çöp kutusundaki öğeler geri yüklenebiliyor veya kalıcı olarak silinebiliyor; çöp görünümünde **Çöpü Boşalt** aksiyonu var. Dosyalar context menüden çoğaltılabiliyor ve yeniden adlandırılabiliyor. Bu sürüm iki migration (`file_favorites` ve `media` soft delete), yeni backend action/query/request sınıfları, yeni File Manager route’ları, genişletilmiş EN/TR dil key’leri ve günlük çalışan `file-manager:purge-trash` komutu getirir. Mevcut uygulamalar `composer update lvntr/laravel-starter-kit && php artisan sk:update && php artisan migrate && npm install && npm run build` çalıştırmalı.

#### Added

- **Favoriler.** Yeni `file_favorites` tablosu ve `FileFavorite` modeli, klasör/dosyaları owner context’e göre yıldızlı tutar. `FavoritesContentsQuery` sidebar’daki **Favoriler** görünümünü besler; `FolderContentsQuery` artık öğeleri `is_favorited` ile işaretler; grid ve context menüler Add/Remove Favorite aksiyonlarını gösterir.
- **Çöp kutusu ve geri yükleme akışı.** `enableTrash` açıkken dosya ve klasörler soft-delete ile çöp kutusuna taşınır. `TrashContentsQuery` **Çöp Kutusu** hızlı görünümünü besler; silinmiş tile’lar silinme zamanını gösterir; çöp context menüleri Restore / Permanently Delete aksiyonlarına döner.
- **Çöpü Boşalt.** `EmptyTrashAction` ve `DELETE /file-manager/trash/empty`, mevcut context’teki tüm çöp öğelerini kalıcı olarak siler; dosyalar klasörlerden önce, klasörler ise çocuklar önce olacak şekilde post-order silinir.
- **Dosya kopyalama ve yeniden adlandırma.** Dosyalar `photo (copy).jpg` / `photo (copy 2).jpg` gibi çakışmasız isimlerle çoğaltılabilir ve shipped dialog + `PATCH /file-manager/files/{media}` endpoint’iyle yeniden adlandırılabilir.
- **Trash purge komutu.** `php artisan file-manager:purge-trash --days=7`, seçilen yaştan eski File Manager çöpünü kalıcı olarak siler. `routes/console.php` içinde günlük schedule edilmiştir.
- **`enableTrash` prop’u.** `FileManager` varsayılan olarak soft-delete davranışıyla gelir; `:enable-trash="false"` verildiğinde çöp kutusu akışı kapatılıp doğrudan kalıcı silme davranışı kullanılabilir.

#### Security

- **Context doğrulaması merkezileştirildi.** `FileManagerContextRequest`, sanal görünümler ve item mutasyonlarında geçerli File Manager context’ini tutarlı şekilde doğrulayıp çözer; favorites/trash endpoint’lerinin normal klasör içerik kontrollerinden sapma riski kapandı.
- **Soft-delete scope sertleştirildi.** Geri yükleme, kalıcı silme, kopyalama, yeniden adlandırma ve favori action’ları öğeleri açıkça mevcut context’e scope eder ve gerektiği yerde `withTrashed()` / `onlyTrashed()` kullanır; cross-context erişim engellenir, trashed öğeler yalnız doğru yollarda bulunur.
- **Klasör geri yükleme cascade korumaları.** Trashed bir klasör geri yüklenirken alt klasörleri ve File Manager media kayıtları transaction içinde geri yüklenir. Parent hâlâ çöpteyse işlem reddedilir; parent kalıcı silinmişse orphan oluşmaması için öğe root’a geri döner.

#### Fixed

- **Toplu force delete artık trashed öğeleri buluyor.** `BulkDeleteAction`, `force=true` durumunda `withTrashed()` kullanır; Trash görünümünden kalıcı silme, zaten soft-delete edilmiş öğeleri artık kaçırmaz.
- **Dil key çakışması düzeltildi.** `labels.details` artık detay bölümü array’i; action label `labels.details_action` oldu. Böylece dosya detay dialog’u label’ları context-menü aksiyon string’iyle ezilmiyor.
- **Collection scope sıkılaştırıldı.** Trash purge ve kalıcı silme yalnız File Manager media kayıtlarını (`collection_name = files`) etkiler; avatar, logo, editor upload veya diğer MediaLibrary collection’larına dokunmaz.

#### Upgrade

Güncelleme sonrası migration çalıştırın:

```bash
composer update lvntr/laravel-starter-kit
php artisan sk:update
php artisan migrate
npm install
npm run build
```

API response tarafında breaking değişiklik yok. File Manager stub’larını özelleştirmiş uygulamalar `sk:update --force` kullanmadan önce özellikle `FileManager.vue`, `useFileManager.ts`, `FileGrid.vue`, `FileManagerController.php`, `routes/web/file-manager-route.php`, `lang/{en,tr}/sk-file-manager.php`, yeni request/action/query dosyaları ve iki migration ile kendi dosyalarını karşılaştırmalı.

## 2026-04-30 -v.13.4.8

### Minor sürüm — Dosya Yöneticisi UX yenilemesi (sidebar + stats + details + arama)

Dosya Yöneticisi UX yenilemesi — backend aynı, route'lar aynı, media tablosu aynı; yeni bir kabuk. Tek-kolon grid yerine sidebar + ana-kolon layout'u; üç yeni shipped component (`FileManagerSidebar`, `FileDetailsDialog`, `FileManagerStats`); mevcut klasörü client-side filtreleyen üst-bar arama kutusu; ve yeni girişlerle genişletilmiş sağ-tık menüsü (Yeni sekmede aç, Önizle, Paylaş, Kopyala, Yeniden Adlandır, Favorilere Ekle, Detaylar). Önceden belgelenmiş tüm davranışlar — yüklemeler, drag-and-drop taşıma, toplu silme, image lightbox, preview dialog'u, özel context'ler, settings, permission'lar — birebir aynı çalışır; değişiklik tamamen shipped frontend (`FileManager.vue` + üç yeni component + `types.ts` + `lang/{en,tr}/sk-file-manager.php`). Yeni composer veya npm bağımlılığı yok, migration yok, config yok, permission girdisi yok. Mevcut consumer uygulamaları `composer update lvntr/laravel-starter-kit && php artisan sk:update && npm install && npm run build` çalıştırarak yamayı çeker; breaking change yok.

#### Added

- **`FileManagerSidebar.vue` — dairesel storage-kullanım halkası, hızlı-erişim listesi, klasör ağacı ve "Yeni Klasör" butonuyla sol panel.** Storage halkası, `circumference - dashOffset` doluluğuyla bir SVG çember kullanır ve renk-bandı eşiği uygular (primary < 70 %, amber 70–90 %, rose ≥ 90 %); kullanılan byte'lar `fm.contents.stats.total_size`'tan gelir, kota şimdilik backend setting'i bağlanana kadar görsel olarak makul 10 GB default'tur. Klasör ağacı, taşıma modalı'nın zaten yüklediği `fm.tree` verisini tekrar kullanır. Hızlı-erişim hedefleri: **Tüm Dosyalar** root'a name asc sıralı döner, **Son Yüklenenler** root'a date desc sıralı döner, **Favoriler** ve **Çöp Kutusu** yaklaşan özelliğin placeholder'ı olarak yeni `coming_soon` toast'unu gösterir.

- **`FileDetailsDialog.vue` — dosya detayları modali (Ad, Tip, Boyut, Yüklenme, Klasör ve resimlerde Boyutlar).** Resim boyutları async yüklenir — dialog `file.url`'a karşı gizli bir `new Image()` tetikler ve `onload` çalıştığında `naturalWidth × naturalHeight`'ı render edilen satıra düşer. Dialog, sağ-tık menüsündeki `downloadFile` handler'ını yeniden kullanan bir "İndir" footer butonuyla gelir; böylece action yüzeyleri hizalı kalır. Dosya context menüsündeki yeni "Detaylar" girişinden açılır.

- **`FileManagerStats.vue` — üst-bar stats widget'ı (Toplam Dosya, Toplam Boyut, Klasör Sayısı, Favoriler, Son Yükleme).** Yatay bir icon-tinted kart sırası render eder (light'ta `bg-{renk}-100`, dark'ta `bg-{renk}-900/40`). Klasör sayısı tüm nested ağacı dolaşır (`flattenTree(fm.tree.value)`); son yükleme mevcut klasördeki en yeni `created_at`'i yansıtır ve "Az önce / X dk / X sa / X g / locale-tarih" formatında yeni `stats.time_*` key'leri üzerinden gösterilir.

- **Üst-bar arama.** Body'nin üzerinde bir `IconField` + `InputText` şeridi, `fm.contents.folders` ve `fm.contents.files`'ı `name` / `file_name` üzerinde case-insensitive `includes` ile filtreler — yeni `filteredFolders` / `filteredFiles` computed'leri üzerinden yüzeye çıkar. Filtre render edilen klasörle sınırlıdır; navigasyon `fm.loadContents()` bir sonraki çağrıda filtreyi örtük olarak temizler.

- **Genişletilmiş dosya context menüsü — Aç / Önizle / İndir / Paylaş / Taşı / Kopyala / Yeniden Adlandır / Favorilere Ekle / Detaylar / Sil.** "Aç" artık dosyayı yeni sekmede açar (`window.open(file.url, '_blank', 'noopener,noreferrer')`); "Önizle" mevcut lightbox / dialog akışını korur; "Paylaş" mutlak dosya URL'sini panoya kopyalar (`navigator.clipboard.writeText(...)`), başarıda yerelleştirilmiş "Bağlantı kopyalandı" toast'u, izin reddinde `coming_soon` toast'u gösterir; "Detaylar" yeni dialog'u açar; "Kopyala", "Yeniden Adlandır", "Favorilere Ekle" yaklaşan özelliklerin placeholder'larıdır. Destructive Sil satırı, ayrı styling için yeni bir `fm-menu-danger` class'ı alır.

- **Klasör context menüsü — Sil'den önce "Favorilere Ekle" (placeholder) eklenir.** Dosya menüsündeki placeholder'larla aynı `coming_soon` toast pattern'i.

- **`types.ts` — `ViewMode = 'grid' | 'list'` ve `QuickView = 'all' | 'recent' | 'favorites' | 'trash'` eklenir.** `ViewMode` yaklaşan list-view renderer için ayrılmıştır (şu an yalnızca grid); `QuickView` sidebar hızlı-erişim akışı tarafından tüketilir. Mevcut export'lar değişmez.

- **`lang/{en,tr}/sk-file-manager.php` — yeni key'ler.** Üst seviye: `link_copied`, `coming_soon`. Label'lar: `upload_new`, `preview`, `share`, `copy`, `add_to_favorites`, `details`, `search_placeholder`, `view_grid`, `view_list`, `files_section`, `folders_section`, `no_results`. Yeni iç içe gruplar: `labels.sidebar.*`, `labels.stats.*`, `labels.details.*`.

#### Removed

- **`FileManager.vue`'dan eski header back-button + sort dropdown kaldırıldı.** Önceki kabukta header'da `←` back butonu + sort key için `Select` dropdown + yön-toggle butonu vardı; navigasyon artık sidebar (klasör ağacı + breadcrumb) üzerinden, sıralama ise hızlı-erişim akışı ("Son Yüklenenler" = `setSort('date', 'desc')`) üzerinden gerçekleşir. `useFileManager` composable'ı doğrudan çağrı yapanlar için hâlâ `setSort` / `toggleSortDirection` export eder.

#### Upgrade

Breaking change yok. Mevcut consumer uygulamaları `composer update lvntr/laravel-starter-kit && php artisan sk:update && npm install && npm run build` çalıştırır — `sk:update` yeni shipped dosyaları ve genişletilmiş dil key'lerini çeker. Wire üzerindeki veri şekli değişmez; backend değişmez.

## 2026-04-26 -v.13.4.7

### Patch sürüm — `EditorInput`'da duplicate Link extension uyarısı susturuldu

Tek-fix patch — Tiptap'ın `EditorInput` ayağa kalkarken yazdığı `Duplicate extension names found: ['link']` uyarısını susturur. Tiptap v3'ün `@tiptap/starter-kit`'i Link extension'ını default olarak bundle'lamaya başladı, ama editör hâlâ `@tiptap/extension-link`'i opsiyonel `props.links` branch'i üzerinden kendi `openOnClick: false, autolink: true` config'imizle push ediyordu — yani aynı editöre iki `link` registration'ı giriyordu. Düzeltme StarterKit çağrısında tek bir config flag (`link: false`); böylece bundle'lanmış kopya devre dışı, manuel-push branch'imiz tek kaynak. Davranış hem `props.links === false` (Link hiç yok) hem de `props.links === true` (sadece manuel-push) için birebir aynı; sadece console gürültüsü kalkıyor. Mevcut consumer uygulamaları `composer update lvntr/laravel-starter-kit && php artisan sk:update` çalıştırır — migration yok, config yok, breaking yok.

#### Fixed

- **`EditorInput.vue` — duplicate Link extension uyarısı susturuldu.** Tiptap v3'ün `@tiptap/starter-kit`'i Link extension'ını default olarak içeriyor; editör ayrıca `props.links` opsiyonel branch'i üzerinden `@tiptap/extension-link`'i de manuel olarak push ediyordu — sonuç: editör console'da `Duplicate extension names found: ['link']` uyarısıyla ayağa kalkıyordu. `StarterKit.configure({ heading: { levels: [2, 3, 4] }, link: false })` ile bundle'lanmış kopya devre dışı bırakıldı; tek kaynak artık manuel-push branch'i (kendi `openOnClick: false, autolink: true` config'imizle). `props.links === false` durumunda Link tamamen kalkıyor; `props.links === true` durumunda sadece manuel-push branch'i çalışıyor — aynı davranış, uyarı yok.

#### Upgrade

Breaking change yok. `composer update lvntr/laravel-starter-kit && php artisan sk:update` patch'i çeker — düzeltme `sk:update`'in zaten takip ettiği aynı shipped Vue dosyası üzerinden geliyor; ek adım gerekmez.

## 2026-04-26 -v.13.4.6

### Patch sürüm — Vite optional-peer-dep stub'ı + `sk:update` package.json merge

`EditorInput` öncesi bir kit sürümünden (13.4.0 ve öncesi herhangi bir kurulum) 13.4.2+ sürümlerine yükseltirken yüzeye çıkan iki ilişkili build/upgrade düzeltmesi. Paketin `package.json`'u artık `@tiptap/*` setini `peerDependencies` + `peerDependenciesMeta.optional` ile bildirmiyor — bu bildirimler, deps consumer'ın project root'unda yüklü olsa bile, `vendor/lvntr/laravel-starter-kit/` altından resolve edilen import'larda Vite'ın optional-peer-dep stub fallback'ini (`__vite-optional-peer-dep:@tiptap/extension-table:@lvntr/starter-kit:false`) tetikliyordu. Sonuç: build sırasında `"Table" is not exported by …`, runtime'da `does not provide an export named 'BubbleMenu'` — ikisi de Vite'ın stub modülünden (`export default {}; throw …`) geliyordu, gerçek paketten değil. Ayrıca `sk:update` artık `sk:install`'in `mergePackageJson()` adımını mirror'lıyor; böylece yeni `@tiptap/*` seti yükseltmede consumer'ın `package.json`'una otomatik düşüyor — daha önce yalnızca fresh install bunu çekiyordu, `<13.4.2`'den yükselen her consumer 16 dependency entry'sini elle kopyalamak zorundaydı. Ortak key'ler için stub-version-wins, user extra'ları korunur, tekrar çalıştırılınca idempotent.

#### Fixed

- **Paket `package.json` — `@tiptap/*` seti için `peerDependencies` + `peerDependenciesMeta` kaldırıldı.** Paket composer üzerinden dağıtılıyor (npm'de yayınlanmadı) — peer-dep bildirimlerinin `npm install` üzerinde hiçbir etkisi yoktu; pratikte tek etkileri Vite'ın `tryNodeResolve` fallback'iydi. Bare bir import (`import { Table } from '@tiptap/extension-table'`), normal `node_modules` walk-up'tan resolve edilemediğinde — paket `vendor/` altında olunca tetiklemesi kolay — Vite importer'in en yakın `package.json`'una bakıyor, dep'i optional peer olarak buluyor ve hata yerine `__vite-optional-peer-dep:<dep>:<parent>:<isRequire>` döndürüyordu. Stub `export default {}; throw new Error("Could not resolve …")` olarak yüklenir — named export yok; build'deki yanıltıcı `"Table" is not exported by …` ve `@tiptap/vue-3/menus` subpath'i için runtime'daki `does not provide an export named 'BubbleMenu'` bu yüzden çıkıyordu. Bildirimler kaldırılınca düz `node_modules` resolve geri devreye giriyor, project root'a kadar walk-up edip gerçek paketleri buluyor.

- **`sk:update` artık `stubs/package.json`'u consumer'ın `package.json`'una merge'liyor.** `UpdateCommand` daha önce sadece `app/`, `config/`, `resources/` ve `routes/` altındaki dosyalara dokunuyordu — projenin `package.json`'una asla. Bu yüzden 13.4.2'nin stub'a eklediği 16 `@tiptap/*` entry'si, `composer update lvntr/laravel-starter-kit && php artisan sk:update` yapan consumer'lara hiç ulaşmıyordu. Yeni adım (`handle()`'da 4c), `InstallCommand::mergePackageJson()`'u mirror'lıyor: stub key'leri root'ta kazanır, `array_merge`-d `dependencies`/`devDependencies` (sıralı), user extra'ları korunur, sadece render'lanan JSON gerçekten farkını yansıttığında yazar (tekrar çalıştırma no-op). Özet, değişikliği `package.json (merged stub dependencies — run npm install)` olarak gösterir; böylece kullanıcı sonradan `npm install` çalıştırması gerektiğini bilir.

#### Upgrade

Breaking change yok. Mevcut consumer uygulamaları `composer update lvntr/laravel-starter-kit && php artisan sk:update && npm install && npm run build` çalıştırır — `sk:update` artık eksik `@tiptap/*` entry'lerini `package.json`'unuza sync'ler ve Vite stub'lar yerine gerçek paketleri resolve eder.

## 2026-04-26 -v.13.4.5

### Patch sürüm — code-review taraması (API hiyerarşi + role-data + 2FA loading + permission directive + i18n)

v13.4.x yüzeyinin takip eden bir kod incelemesinden çıkan küçük bir bulgu paketini kapatır. İki güvenlik / bilgi-sızdırma düzeltmesi (API kullanıcı listesi artık admin panelinin uyguladığı aynı role-hiyerarşi filtresini uyguluyor; rol JSON `data` endpoint'i artık `edit`/`destroy` action'larıyla aynı `CanManageRoleQuery` guard'ından geçiyor), bir UX düzeltmesi (2FA enable/disable butonları yalnızca happy path'te değil, hata yollarında da loading state'lerini sıfırlıyor), bir latent-bug düzeltmesi (`v-role` directive'i Inertia paylaşımlı prop'unun yanlış key'ini okuyup sessizce hep `false` döndürüyordu) ve bir i18n temizliği (`useApi` composable'ının hata toast'ları ve sentezlenmiş envelope mesajları artık hardcoded Türkçe stringler yerine `sk-message.*` key'leri üzerinden geçiyor). Tüm değişiklikler wire üzerinde additive — aynı response shape, aynı status kodları, aynı UI. Üç regression test'i iki güvenlik düzeltmesini koruyor. Mevcut tüketici uygulamalar `php artisan sk:update` ile yamaları çeker; migration yok, config yok, breaking yok.

#### Security

- **`Api/UserController::index` artık `UserDatatableQuery`'ye delegasyon yapıyor — admin paneliyle aynı role-hiyerarşi filtresi.** Önceki haliyle API, `UserDatatableQuery`'nin uyguladığı `whereDoesntHave('roles', sort_order < me)` clause'unu atlayan kendine özel bir `DatatableQueryBuilder` zinciri kullanıyordu. Sonuç: `users.read` izni olan ama `system_admin` olmayan bir API tüketicisi `GET /api/v1/users` ile her üst-rank kullanıcıyı — `system_admin` hesapları dahil — görebilirdi; admin UI ise onları gizliyordu. Controller artık `UserDatatableQuery`'yi method-inject edip doğrudan `response($request->user())` döndürüyor. Query'nin allowlist'leri legitimate API çağrılarının wire kontratı değişmesin diye `first_name`, `last_name`, `email`, `status`, `id`, `created_at` sortable key'leri (önceden API'a özeldi) ile genişletildi. Yeni `tests/Feature/Api/UserTest.php` "hides higher-rank users from non-system_admin api callers" regression test'iyle korunuyor.

- **`Admin/RoleController::data` artık rol JSON'unu döndürmeden önce `CanManageRoleQuery` çalıştırıyor.** `data()`, `edit()`'in JSON kardeşidir (admin rol formu bunu `useApi().get('/admin/roles/{role}/data')` ile pre-fetch ediyor). `edit()` ve `destroy()` zaten role hiyerarşisini zorlamak için `CanManageRoleQuery::check()`'ten geçiyordu; `data()` geçmiyordu — bu yüzden alt-rank bir admin, render edeceği form hiyerarşi-aware olduğu halde, üst-rank bir rolün tüm permission setini JSON üzerinden okuyabiliyordu. Kontrol artık `data()`'nın en üstüne inline edildi (`abort(403)` mismatch'te), `edit()` davranışını mirrorluyor. İki yeni `tests/Feature/Admin/RoleManagementTest.php` regression test'iyle korunuyor ("forbids non-system_admin from reading higher-rank role data" + same/lower rank için pozitif kardeş).

#### Fixed

- **2FA enable/disable butonları artık hata durumunda takılı kalmıyor.** `Profile/components/TwoFactorTab.vue`, Fortify'a çağrı atmadan önce `twoFactorProcessing = true` set ediyordu ama yalnızca success branch'inde sıfırlıyordu. Bir axios 4xx/5xx (tipik: süresi dolmuş bir oturum, password-confirm timeout'u) ya da bir Inertia `router.reload` hatası, butonu tam sayfa yenilemeye kadar spinner'da bırakıyordu. `enableTwoFactor()` ve `disableTwoFactor()` artık flag'i bir `finally` bloğunda sıfırlıyor; herhangi bir hata buton'u tekrar tıklanabilir + bir toast olarak yüzeye çıkıyor (donmuş UI yerine).

- **`v-role` directive'i artık doğru Inertia paylaşımlı prop key'ini okuyor.** `resources/js/plugins/permission.ts` `auth.roles`'u kontrol ediyordu ama `HandleInertiaRequests` kullanıcı rol isimlerini `auth.role_names` altında paylaşıyor. Directive sessizce hep `false`'a düşüyordu — `<div v-role="'system_admin'">` markup'ı, actor'ün rolü ne olursa olsun hiç görünmüyordu. Plugin artık `auth.role_names`'i okuyor. Plugin dosyasındaki duplicate `useCan` export'u (aynı yanlış key'i okuyordu) da kaldırıldı — kanonik `useCan()` `@/composables/useCan` altında yaşıyor ve zaten doğruydu, yani uygulama kodu etkilenmedi. Plugin dosyası artık yalnızca `PermissionPlugin`'i export ediyor (`v-can` + `v-role` kayıt eder).

- **`useApi` composable'ı hata mesajları `sk-message.*` i18n key'leri üzerinden akıyor.** `resources/js/composables/useApi.ts` üç hardcoded Türkçe hata stringi içeriyordu (non-JSON response için sentezlenmiş envelope, network-failure toast detayı, toast `summary`). `trans('sk-message.invalid_response')`, `trans('sk-message.request_failed', { status })`, `trans('sk-message.network_error')`, `trans('sk-message.error_summary')` ile değiştirildi. Dört yeni key hem `lang/en/sk-message.php` hem `lang/tr/sk-message.php` içine eklendi. EN-locale kullanıcıları artık bir API çağrısı normal envelope yolu dışında başarısız olduğunda Türkçe metin görmüyor.

#### New

- **İki güvenlik düzeltmesi için regression test'leri.** `tests/Feature/Api/UserTest.php` `hides higher-rank users from non-system_admin api callers` test'ini kazanıyor — `RoleEnum` index'i üzerinden role hiyerarşisini seed ediyor, `users.read` + `admin` rolünü `api` guard'ına da mirrorlayıp (Spatie'nin `Guard::getDefaultName()`'i `Passport::actingAs` altında `api`'ye geçiyor), bir admin kullanıcısına web + api versiyonlarını birlikte assign ediyor ve response'un üst-rank `system_admin` peer + acting `system_admin` user'ını dışlayıp same-rank admin peer'ı içerdiğini assert ediyor. `tests/Feature/Admin/RoleManagementTest.php` ikisini kazanıyor: `forbids non-system_admin from reading higher-rank role data` (admin `/admin/roles/{system_admin}/data`'da 403 alır) ve `allows non-system_admin to read lower-rank role data` (admin `/admin/roles/{user}/data`'da 200 alır).

## 2026-04-25 -v.13.4.4

### Patch sürüm — system-admin log görüntüleyici (`/logs`)

`storage/logs/` altındaki Laravel log dosyalarını listelemek, aramak ve silmek için yalnızca bakım rolüne açık bir admin bölümü ekler. Kendi içinde tamamlanmıştır — yeni composer/npm bağımlılığı, migration veya permission girdisi gerekmez. Yalnızca `system_admin` kullanıcılarına görünür; geri kalan herkes paneli aynı şekilde görür. Tamamı additive.

#### Added

- **`/logs` admin bölümü — yalnızca system-admin log görüntüleyici.** "Sistem" başlığı altındaki yeni sidebar öğesi `storage/logs/` içeriğini bir `SkDatatable`'da listeler (dosya adı, kanal tipi, boyut, değiştirilme zamanı, aktif flag'i); dosya bazlı görüntüleyici sayfası ise yapısal filtreler (seviye, tarih aralığı, anahtar kelime) altında cursor sayfalanmış kayıt akışını gösterir. Tekli + toplu silme aynı endpoint üzerinden kısmi-başarı semantiğiyle çalışır — aktif dosyalar (bugünün günlük log'u, son 5 saniyedir yazılanlar) tek tek reddedilir ve `failed[]` listesinde geri döner; geri kalan dosyalar silinir. Her silme batch'i `LogFilesDeleted` event'i dispatch eder; yeni `LogActivityForLogFilesDeleted` listener'ı `log_name = system` altında bir `spatie/activitylog` kaydı yazar — silme işlemi **Admin → Activity Logs** sayfasında otomatik görünür.

- **`app/Domain/Logs/` bounded context.** Dört DTO (`LogFileDTO`, `LogEntryDTO`, `LogEntryFilterDTO`, `DeleteLogFilesDTO`), iki query (`LogFileQuery` dosya listesi için, `LogEntryQuery` kayıt stream'i için), bir action (`DeleteLogFilesAction`), bir event/listener çifti ve stateless `LaravelLogParser` servisi. `LogEntryQuery::paginate()` dosyayı `fopen('rb')` + 64KB ile sınırlı `fgets()` ve byte offset cursor ile okur; bellek kullanımı dosya boyutundan bağımsız olarak sabit kalır; çok satırlı stack trace'ler ait oldukları kayda eklenir; ilk Laravel-format başlığından önce gelen (veya hiç başlık içermeyen dosyalarda kalan) satırlar tek bir raw `LogEntryDTO` olarak basılır (`is_raw = true`, gri chip, gizli timestamp) — yani dosya içeriği sessizce kaybolmaz. Yapısal filtre uygulandığı an (level / from / to / keyword) raw entry'ler doğal olarak listeden düşer.

- **`logs.*` isimli route grubu.** `routes/web/log-route.php` beş route içerir — `index`, `dtApi`, `show`, `entries`, `destroy` — hepsi `role:system_admin` ile sarılır. `{filename}` parametre kısıtı (`[A-Za-z0-9._-]+\.log`) hem `show` hem `entries` üzerinde zorlanır; path traversal ve `.log` olmayan istekler controller'a hiç ulaşmaz. Bölüm role-gated olduğu (permission-gated olmadığı) için dosya `routes/web.php` içindeki `$routesWithoutPermissionMiddleware` allowlist'ine eklenmiştir.

- **`lang/{en,tr}/sk-log.php` çeviri dosyası.** Tüm UI metinleri (filtre etiketleri, boş durumlar, silme onayları, hata sebep kodları) iki dilde de `sk-log.*` namespace'i altındadır. Yeni `sk-menu.logs` key'i sidebar'daki menü öğesini etiketler.

#### Security

- **Üç katmanda path-traversal koruması.** Güvenli dosya adı regex'i `^[A-Za-z0-9._-]+\.log$` (1) route parametre kısıtında, (2) `DeleteLogFilesRequest` kurallarında ve (3) `DeleteLogFilesAction::execute()` içinde (defence in depth) zorlanır. Kalan her şey `log.invalid_filename` olarak failure döner ya da route binding'den 404 alır — disk path'i ham input'tan inşa edilmez.

- **Aktif dosya silme reddi.** `LogFileQuery::isActive()`, bugünün günlük dosyasını (`laravel-{today}.log`) ve `mtime`'ı son 5 saniye içinde olan her dosyayı işaretler. `DeleteLogFilesAction` işaretli dosyaları item-bazlı `reason: 'active_file_protected'` ile reddeder; toplu submit, Laravel'in o anda yazdığı dosyayı kazara truncate edemez.

- **`role:system_admin` route gate'i, permission girdisi yok.** Görüntüleyici bilinçli olarak `config/permission-resources.php`'ye eklenmemiştir. `admin` rolü vermek bunu açmaz; yalnızca özel `system_admin` rolü açar. system-admin olmayan kullanıcılar route'a 403 alır ve menü öğesini hiç görmez — özellik onlara görünmez.

- **64KB satır okuma sınırı.** `LogEntryQuery` `fgets($handle, 65536)` çağırır; sınırsız uzunlukta tek satırlık bir kayıt process belleğini tüketemez. Uzun satırlar isteği abort etmeden temiz şekilde truncate olur.

## 2026-04-25 -v.13.4.3

### Patch sürüm — zengin dikey tab'lar + datatable per_page üst sınırı

`TB` builder üzerinden daha zengin bir dikey tab görünümü (icon tile, description satırı, trailing badge veya check) ve `DatatableQueryBuilder` tarafında `?per_page=` parametresi için opsiyonel üst sınır geliyor. Değişikliklerin tamamı additive — breaking yok. `sk:update` yeni TabBuilder Vue bileşenlerini, yeniden yazılmış `_tabs.scss`'i ve EN/TR `sk-setting.tab_descriptions` dil anahtarlarını taşır; paket katmanındaki `max_per_page` config için `composer update` yeterli.

#### Added

- **`TB.item()` zengin dikey tab fluent metodları.** Dört yeni fluent metod: `.description(text)` label altında ikincil bir satır, `.iconColor(color)` renkli icon tile preset'i (13 renk: `blue`, `amber`, `emerald`, `purple`, `teal`, `red`, `indigo`, `slate`, `pink`, `orange`, `cyan`, `green`, `yellow`), `.badge(value, severity?)` sağ tarafta badge (5 severity: `success`, `warn`, `info`, `danger`, `secondary`) ve `.checked()` sağ tarafta yeşil check (badge üzerinde önceliklidir). Mevcut tab tanımları olduğu gibi çalışır. **Ayarlar → Genel** sayfası yeni API'yi kullanacak şekilde güncellendi (per-tab description + icon color); kanonik örnek olarak hizmet veriyor. Yeni i18n bloğu `sk-setting.tab_descriptions` yedi ayar tabını kapsar.

- **`STARTER_KIT_DATATABLE_MAX_PER_PAGE` env var + `config('starter-kit.datatable.max_per_page')`.** `DatatableQueryBuilder` üzerinde `?per_page=` parametresi için opsiyonel üst sınır. Anahtar tanımlı değilse `100`'e düşer.

#### Security

- **`DatatableQueryBuilder` — `?per_page=` üst sınırı zorlanır.** Önceki sürümlerde bir istemci `?per_page=99999` gönderip builder'ı tüm tabloyu tek payload olarak materialise etmeye zorlayabiliyordu. Yeni tavan (`config('starter-kit.datatable.max_per_page')`, default 100) değeri sessizce kırpıyor — tavanın altında her şey aynı çalıştığı için meşru çağrılar etkilenmiyor.

#### Improved

- **Dikey tab sidebar — `.isCard(true)` ile PrimeVue Card sarmalayıcı.** Tab'lar seviyesinde (per-tab değil) ayarlanır; dikey sidebar daha az iç padding'le bir Card içine sarılır. Yeni icon tile + description alanlarıyla birleştirildiğinde Ayarlar sayfasının sidebar'ı kutudan çıkar çıkmaz modern admin-panel düzenine uyuyor.

#### Fixed

- **Branding — legacy "Starter Kit 12" referansları.** İki yer hâlâ "Starter Kit 12" diyordu — `config/scramble.php` API açıklaması ve `app.blade.php` fallback title; her ikisi de artık "Starter Kit 13" diyor.

## 2026-04-24 -v.13.4.2

### Patch sürüm — Tiptap editor input, şifre üreticisi, dashboard hoş geldin mesajı + güvenlik sertleştirmesi

Zengin metin editörü olarak çalışan `FB.editor()` FormBuilder alanı (arkasında Tiptap v3) server-side `HtmlSanitizer` utility'si ile birlikte, `FB.password()`'a crypto-safe şifre üreticisi ve **Ayarlar → Genel** altında editor ile yazılan admin dashboard hoş geldin mesajı bu sürümde geliyor. Dosya yükleme, editor-scoped upload'ların gruplu kalması için opsiyonel `folder_name` parametresi kazandı; FileManager artık HTTP 413 Payload Too Large için özel bir hata mesajı gösteriyor. Değişikliklerin tamamı additive — breaking yok. `sk:update` publish edilmiş dosyaları (yeni Vue bileşenleri, `HtmlSanitizer`, dil anahtarları) taşır; paket-katmanı değişiklikleri için `composer update` yeterli.

#### Added

- **Tiptap tabanlı `FB.editor()` FormBuilder input'u.** Tiptap v3 üzerine kurulan yeni bir form alanı tipi; bubble menu, link / image / table / task list / text align / text color / text style ve placeholder extension'ları içerir. Araç çubuğu düzeni `.preset('minimal' | 'standard' | 'full')` ile seçilir; resim yüklemeleri FileManager context'i üzerinden opsiyonel folder-grouping parametresiyle yönlendirilir; yardımcı bileşenler (`EditorColorPalette`, `EditorImagePicker`) renk ve görsel seçici akışlarını karşılar. Çeviriler `lang/{en,tr}/sk-editor.php` dosyalarında. İçerik kaydedilirken yeni `App\Support\HtmlSanitizer` üzerinden geçer — yalnızca allowlist'teki tag / attribute / URL scheme'leri DB'ye yazılır.

- **`FB.password().generator()` — crypto-safe şifre üreticisi.** Parola alanlarının yanına generate butonu ekleyen opt-in fluent metodu; `crypto.getRandomValues()` kullanır. Default'lar bilinçli olarak `Password::defaults()`'tan daha sıkı (16 karakter, mixed case + harf + rakam + sembol) — böylece üretilen her değer ilk submit'te proje-wide parola politikasını geçer. Aynı değişiklikte yeniden yazılmış custom eye toggle ile `password` ve `password_confirmation` alanları `InputGroup` içinde birebir aynı görünüyor. PrimeVue `<Password>` artık yalnızca `.feedback()` ile strength meter'a opt-in edildiğinde kullanılır — diğer tüm kullanımlar daha hafif `InputText + eye` yoluna düşer. Admin User formunda kutudan çıkar çıkmaz aktif.

- **Admin dashboard hoş geldin mesajı.** **Ayarlar → Genel** altına `FB.editor()` ile yazılan opsiyonel bir `welcome_message` WYSIWYG alanı geldi. Dashboard sanitize edilmiş HTML'i Inertia prop'u olarak paylaşıyor, `resources/js/pages/Admin/Dashboard/Index.vue` ise `sk-prose` container'ında `v-html` ile render ediyor. Değer hem yazılırken (FormRequest `prepareForValidation` hook'u) hem okunurken (DashboardController defense-in-depth geçişi) sanitize ediliyor; böylece on-disk değer bozulsa bile eski kayıtlardaki kötü niyetli HTML frontend'e ulaşmıyor.

- **`folder_name` upload parametresi.** `POST /file-manager/files` artık opsiyonel `folder_name` string'i kabul ediyor (nullable, `max:100`, sıkı regex: yalnızca harf / rakam / boşluk / tire / altçizgi — path traversal ve keyfi karakter riski validation'da kapatıldı). Geçildiğinde `UploadFileAction::ensureManagedFolder` mevcut context için o isimde root-level bir klasörün varlığını atomik şekilde garanti ediyor ve upload'ı içine koyuyor. Welcome-message editor'ü bu parametreyi kullanarak tüm inline görsel upload'larını tek "Welcome Message" klasörü altında gruplar — eski read-query side-effect pattern'i geri gelmiyor. Frontend'teki `EditorImageUploadConfig` aynı alanı `folderName` üzerinden expose ediyor.

#### Security

- **`App\Support\HtmlSanitizer` — tag, attribute ve URL scheme allowlist'i.** Editor payload'larından allowlist'te olmayan tüm tag / attribute / URL scheme'lerini süzen yeni utility. URL işleme blocklist'ten allowlist'e çevrildi: relative URL'ler + `http://`, `https://`, `mailto:`, `tel:` kabul ediliyor — diğer her şey (`blob:`, `data:`, `file:`, `ftp:`, `javascript:`, `vbscript:`) reddediliyor. Kendi `tests/Unit/HtmlSanitizerTest.php` regression suite'i ile kapsanıyor.

- **`SettingService::normalizeValue()` — tüm yazma yollarında HTML sanitize.** `setValue()` ve `setGroup()` her değeri paylaşılan `normalizeValue()` hook'undan geçiriyor. Yeni `HTML_SAFE_KEYS` whitelist'inde listelenen anahtarlar (şu an `general.welcome_message`) DB'ye ulaşmadan önce `HtmlSanitizer::sanitize()`'den geçiyor; yani FormRequest dışı tüm yazma yolları (tinker, scheduled command, queue job) da sanitize'e tabi — normal setting API'si üzerinden sanitize edilmemiş HTML DB'ye asla yazılamıyor.

- **Dashboard welcome message — defense-in-depth okuma sanitize'i.** `DashboardController::index` saklanmış welcome message'ı Inertia'ya paylaşmadan önce `HtmlSanitizer::sanitize()`'den ikinci kez geçiriyor. Write-path sanitize'i gelmeden önce yazılmış tarihi kayıtlar ve drift etmiş veya manuel poison edilmiş DB değerleri browser'a ulaşamıyor.

- **`UploadFileAction::ensureManagedFolder` — concurrency-safe managed folder oluşturma.** Ensure path'i `DB::transaction` içinde aday satıra `lockForUpdate` ile kilit koyuyor, unique-constraint yarışı için `QueryException` catch ile refetch'e düşüyor ve soft-deleted klasörleri yeniden oluşturmak yerine `withTrashed()` ile restore ediyor. Üç katman birlikte, iki paralel editor upload'unun aynı klasör adında deadlock'a girmesi veya soft-deleted bir satırı silip unique index'e çarpan bir sibling oluşturması yarışını kapatıyor.

- **`UploadFileRequest` — `folder_name` input'u sıkı validasyonla geçiyor.** Yeni alan `nullable|string|max:100|regex:/^[\pL\pN _-]+$/u` kullanıyor; path-traversal ve keyfi karakterli içerik downstream'e değil, FormRequest sınırında reddediliyor.

#### Improved

- **FileManager upload hata mesajları.** Client composable artık HTTP 413 (Payload Too Large) durumunda jenerik hata yerine yeni `too_large` çevirisini gösteriyor (EN + TR); diğer tüm non-200 yanıtlar ise client-side mesajda status code'u da taşıyor — upload hatalarını devtools network sekmesini açmadan teşhis etmek kolaylaştı.

- **Password alanı default render yolu.** Yukarıdaki `.generator()` eklemesine ek olarak, default `FB.password()` render'ı PrimeVue `<Password>` yerine `InputText` + custom eye toggle'a geçti. `<Password>`'un kendi eye ikonunun `InputGroup` addon'larında kaybolma sorununu çözüyor ve `password` / `password_confirmation` alanlarının birebir aynı görünmesini sağlıyor. `.feedback()` çağrıldığında (strength meter yolu) hâlâ PrimeVue `<Password>` kullanılıyor. Yeni i18n anahtarları: `generate_password`, `password_generated`, `password_generated_detail`, `show_password`, `hide_password` (EN + TR).

#### Fixed

- **`SettingsDefaultsQuery` read path'i artık yazma yapmıyor.** Önceki sürümde **Ayarlar → Genel** ekranı okunduğunda `resolveWelcomeMessageFolderId()` yan etkisi olarak `FileFolder::firstOrCreate(...)` çağırıyordu. Aynı isimde soft-deleted bir klasör barındıran install'larda unique index insert'i reddediyor ve admin, saf bir okuma ekranında 500 alıyordu. Folder ensure yolu artık yalnızca upload anında çalışan `UploadFileAction::ensureManagedFolder`'da; `SettingsDefaultsQuery` yeniden tamamen side-effect-free. Frontend'teki `welcome_message_folder_id` Inertia prop bağımlılığı da kaldırıldı — editor doğrudan `folderName` üzerinden çalışıyor.

- **Editor upload — stale `blob:` URL'lerinin form payload'una sızması engellendi.** `EditorInput.vue` artık `setContent({ emitUpdate: false })` sonrasında parent `v-model`'i elle senkronlıyor; taze bitmiş bir upload'tan geride kalan / kırık `<img src="blob:...">` parçaları submit edilen HTML'de sunucuya gitmiyor.

## 2026-04-22 -v.13.4.1

### Patch sürüm — API response sertleştirme + Postman/Apidog sync + OAuth UUID fix

Bu sürüm, baştan sona elden geçirilen API response zarfı (trace-id pipeline, merkezi exception handler, leak kapatan controller patch'leri) ile iki yeni API client entegrasyonu (Postman ve Apidog sync) ve iki adet kurulum fix'ini (OAuth UUID uyumluluğu, otomatik Passport personal access client) birlikte getiriyor. Çoğu değişiklik additive (yeni body alanı + header'lar, yeni admin butonları) ama üç adet API-response davranışsal breaking noktası strict client'lar için önemli — detay için [docs/UPGRADE.tr.md](UPGRADE.tr.md). Taze kurulumlar her şeyi otomatik alır; mevcut projeler upgrade rehberini takip etmelidir. `sk:update` publish edilmiş dosyaları taşır; controller patch'leri ve sonrası Passport adımı manueldir.

#### Security

- **Controller `$e->getMessage()` leak'leri kapandı (11 yer).** `FileManagerController` (bulkDelete/createFolder/renameFolder/moveItem/deleteFolder/upload/deleteFile), `Api/UserController::destroy` ve `Api/Auth/AuthController::login`+`twoFactorChallenge` içinde `to_api(null, $e->getMessage(), 4xx)` pattern'i `throw ApiException::*` ile değiştirildi. Mesaj metni aynı şekilde client'a gidiyor ama artık merkezi handler'dan geçiyor — `trace_id` eşleniyor, 500+ log'lanıyor, `X-Correlation-ID` echo ediliyor. Iç `LogicException` mesajı yerine kontrollü `ApiException` tipine geçiş, gelecek refactor'larda iç mesaj sızıntısı riskini kapatıyor.

- **`abort($code, 'msg')` raw mesajı artık client'a sızmıyor.** `HttpExceptionInterface` dalı `$e->getMessage()` yerine sabit `defaultMessageForStatus()` tablosunu kullanıyor. `abort(400, 'SQL error: ...')` çağrısı artık body'de `"Bad request."` döndürür; iç detay sadece `APP_DEBUG=true` iken `debug.message` alanında görülür. Controlled mesaj için `throw ApiException::badRequest('...')` kullanın.

- **`Api/AuthController` ham User model'i yerine `UserResource` dönüyor.** `register`, `login` (default kind), `twoFactorChallenge` ve `me` endpoint'leri `data.user` için artık `UserResource::toArray()` çıktısı veriyor. Ham Eloquent serializasyonu `$hidden`'a güveniyordu; ileride eklenecek hassas bir alan unutulursa sessizce sızabilirdi. Resource artık kontrat — hangi alan client'a gidiyor açıkça yazılı.

#### Added

- **Postman sync — admin butonu + CLI.** API Rotaları sayfasındaki "Postman'e Gönder" aksiyonu (ve `php artisan postman:sync` komutu) Scramble OpenAPI spec'ini Postman'in `/import/openapi` endpoint'ine `folderStrategy=Tags` parametresiyle push ediyor — tag'ler doğrudan folder'a dönüşüyor. Her sync önce taze koleksiyonu import ediyor, yeni UID'yi ayarlar tablosuna yazıyor, ardından eski koleksiyonu best-effort siliyor. `import-first, delete-after` sırası sayesinde Postman tarafında geçici bir hata veya geçersiz token, mevcut çalışan koleksiyonu kaybetmeden geçiyor. Yapılandırma: Settings → API Clients → Postman card'ı (API Key + Workspace ID; collection ID otomatik yönetiliyor).

- **Apidog sync — admin butonu + CLI.** Aynı pipeline Apidog'un `POST /v1/projects/{projectId}/import-openapi` endpoint'ine inline JSON input ve `OVERWRITE_EXISTING` davranışıyla push ediyor. `php artisan apidog:sync` olarak da çağrılabiliyor. Yapılandırma: Settings → API Clients → Apidog card'ı (Access Token + Project ID).

- **Settings → API Clients tabı.** Postman ve Apidog yapılandırması için ayrı card'lar içeren tek bir tab. Gizli alanlar (`postman.api_key`, `apidog.access_token`) `config/settings.php`'deki `sensitive_keys` listesi aracılığıyla DB'de encrypted tutuluyor. Eski `POSTMAN_*` `.env` anahtarları artık kullanılmıyor — mevcut değerler ayarlar tablosuna migrate ediliyor.

- **Ortak `OpenApiExporter` helper.** İki sync Action'ı aynı exporter'ı paylaşıyor: `scramble:export` çalıştırıyor, `storage/app/postman/` altına her çağrı için benzersiz bir geçici dosya yazıyor, `finally` bloğunda temizliyor — CLI komutu ve admin butonu eş zamanlı çalıştığında paylaşılan bir dosyada yarışmıyorlar. Spec **değişmeden** gönderiliyor: content-type rewrite yok, push edilen koleksiyon gerçek sunucu kontratını aynen yansıtıyor (client'lar kendi UI'larında body görünümünü istedikleri gibi raw/form-data arasında değiştirebilir).

#### Improved

- **Başarılı ve hatalı response'lar aynı `trace_id` altında eşleşiyor.** Yeni `AssignTraceId` middleware'i API grubuna prepend edildi; her request'te UUID üretiyor ve `$request->attributes->get('trace_id')` üzerinden hem success (`ApiResponse::toResponse`) hem error (`ApiExceptionHandler`) dalı aynı id'yi pick-up ediyor. Body'de `trace_id` + header'da `X-Request-ID` + client'ın sanitize edilmiş `X-Request-ID`'si `X-Correlation-ID` olarak echo. Müşteri destek senaryolarında client log'u ile sunucu log'u tek id ile eşleşebiliyor.

- **`ModelNotFoundException` mesajı model ismini içeriyor.** `"The requested resource was not found."` → `"User not found."` (veya `Role`, `Product`, …). `ApiExceptionHandler::modelNotFoundMessage` `class_basename($e->getModel())` ile resolve ediyor. Bir önceki AGENTS.md vaadini karşılıyor; model sınıf adı zaten URL'den tahmin edilebildiği için güvenlik etkisi yok.

- **429 Too Many Requests response'una `Retry-After` header'ı propagate ediliyor.** `ThrottleRequestsException::getHeaders()` içindeki tüm rate-limit header'ları (`Retry-After`, `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`) response'a kopyalanıyor. Rate-limit'e takılan client'lar artık kaç saniye bekleyeceğini mesajdan parse etmek yerine standart header'dan okuyabiliyor.

- **`simplePaginate()` desteği.** `to_api(Model::simplePaginate(15))` artık type error vermiyor; `meta.has_more` ile yetinen lightweight pagination için destek eklendi. `LengthAwarePaginator` ve `CursorPaginator` davranışı değişmedi.

- **`to_api(paginator, 'msg', 201)` artık pagination meta'sını kaybetmiyor.** Helper'da paginator-detect 201/202 branch'lerinden önce çalışıyor; batch-create tipinde endpoint'ler bile meta üretiyor (önceki sürüm paginator'u ham nesne olarak serialize ediyordu — sessiz bug düzeldi).

- **`ApiResponse` DRY + `final`.** `paginated()` ve `paginatedCollection()` meta üretim mantığı tek bir private helper'a çekildi. Sınıf artık `final` — subclass invariant'ı kırma riski kapandı. Controller'ların dönüş tipi imzasında davranışsal değişim yok; public API surface aynı.

- **Scramble `ApiResponseExtension` şema açıklamaları zenginleşti.** Envelope'daki her alan için tanım + örnek + validation rule description eklendi. Multi-status şema (201 / 204 / 4xx / 5xx için ayrı Response) Scramble `TypeToSchemaExtension` API'siyle direct desteklenmediği için sonraki sürüme taşındı — `OperationExtension` ile modellenecek.

#### Fixed

- **OAuth migration'ları UUID uyumlu.** `oauth_access_tokens.user_id` ve `oauth_auth_codes.user_id` artık `foreignUuid` (önceden `foreignId` / `bigint unsigned`); `oauth_clients.owner_*` artık `nullableUuidMorphs`. Starter kit'in UUID `users.id` primary key'i ile birlikte önceki uyumsuzluk login akışında `SQLSTATE 1265: Data truncated for column 'user_id'` hatasını tetikliyordu — login akışı artık kutudan çıkar çıkmaz temiz çalışıyor.

- **`site:install` Passport personal access client'ı otomatik oluşturuyor.** `passport:keys` ile admin-user seed adımları arasına `passport:client --personal --provider=users` eklendi. Taze kurulumlar artık API token üretebiliyor; önceden operatörün manuel olarak bu komutu çalıştırması gerekiyordu.

- **202 Accepted'ın ölü kodu temizlendi.** `to_api($data, '', 202)` için `'Operation queued.'` fallback'i zaten hiç devreye girmiyordu (`$message` default'u truthy). Helper tek bir mantıksal akışa sadeleşti.

- **`ApiResponse::toResponse()` request attribute'u değerlendiriyor.** Önceki sürüm `Responsable::toResponse($request)` imzasını alıyordu ama `$request`'i kullanmıyordu — yeni middleware ile entegrasyon bu parametreye bağlı, artık değerlendiriliyor.

- **Exception handler `match` sıra kritikalitesi yoruma bağlandı.** `ApiException extends HttpException`, `HttpExceptionInterface` dalından önce kalmalı — aksi halde custom API exception'lar generic `abort()` handling'e düşerdi. Fragile sıralama yorum satırıyla ve regression test (`tests/Feature/Api/ApiResponseTest.php`) ile güvence altına alındı.

#### New

- **Regresyon test dosyası: `tests/Feature/Api/ApiResponseTest.php` (16 test, 57 assertion).** Envelope şekli, exception → status mapping, trace id eşleşmesi, 204 boş body, `Retry-After` propagation, `debug` sadece `APP_DEBUG=true` iken, sanitize edilmiş `X-Correlation-ID` echo — tüm kontrat testleri. Paketten örnek olarak `vendor/lvntr/laravel-starter-kit/tests/examples/ApiResponseTest.php`'tan kopyalanabilir.

- **`sk:update` otomasyon kapsamı genişledi.** `app/Http/Middleware/AssignTraceId.php` ve `app/Helpers/sk-helpers.php` artık safe-update listesinde; `php artisan sk:update` bu iki dosyayı otomatik senkronize ediyor. `ApiResponse.php` ve `ApiExceptionHandler.php` zaten listedeydi.

#### Breaking

Detaylı migration adımları için [docs/UPGRADE.tr.md](UPGRADE.tr.md). Özet:

- `abort($code, 'custom message')` artık mesajı göstermiyor — `ApiException::*` throw kullanın.
- `ModelNotFoundException` mesajı model adını içerir (`"User not found."`). Frontend regex eşleşmeleri güncellenebilir.
- `Api/Auth/AuthController` `data.user` alanları `UserResource::toArray()` çıktısıyla sınırlı. Ham modelin bir alanına bağımlıysanız resource'u güncelleyin.

## 2026-04-21 -v.13.4.0

### Minor sürüm — Güvenlik sertleştirme sprinti

Paralel bir kod inceleme turu ~37 bulgu çıkardı — 13 HIGH, 14 MEDIUM, 4 LOW. Bu sürümde 36 tanesi kapatıldı; 1 HIGH (git history'deki Passport private-key rotation'ı) operatörün manuel adım atması gereken bir düzeltme. Patch'lerin büyük çoğunluğu **publish edilmiş** dosyalara (yani `sk:install`'ın sizin uygulamanıza kopyaladığı dosyalara) dokunuyor; bu yüzden mevcut consumer projeler [docs/UPGRADE.tr.md](UPGRADE.tr.md) içindeki diff'leri uygulamalı. Taze kurulumlar her şeyi otomatik alıyor. Nadir paket-katmanı değişiklikleri (HSTS `preload`, stub güncellemeleri) `composer update lvntr/laravel-starter-kit` ile geliyor.

#### Security

- **`UserPolicy::delete`'te self-delete engellendi + API `UserController::destroy` null guard.** `UserPolicy::delete` actor === target durumunda `true` dönüyordu, bu nedenle `users.delete` izni taşıyan herhangi bir authenticated user `DELETE /api/v1/users/{self}` ile kendini silebiliyordu. Self branch'i artık `false` dönüyor — kendi kendini silmenin desteklenen tek yolu Profile sayfasındaki password-confirmed Fortify akışı. `Api\UserController::destroy` ayrıca `$request->user()` null olduğunda (stale / expired bearer) temiz bir 401 dönüyor — önceki `(string) null = ''` cast'i boş performer id log'luyordu.

- **`CreateRoleAction` + `UpdateRoleAction` role + permission sync'ini `DB::transaction` içinde çalıştırıyor.** `Role::create(...)` ardından `->syncPermissions(...)` transaction dışında koşuyordu; iki write arasında permission-cache race veya bağlantı düşmesi olursa role satırı permission'sız kalıyordu. İki action da artık `DB::transaction(...)` içinde çalışıyor; `RoleCreated` / `RoleUpdated` commit sonrası dispatch ediliyor, listener'lar tutarlı state görüyor.

- **`UpdateAuthSettingsAction` 2FA revoke loop'unu `DB::transaction`'a aldı.** Admin `auth.two_factor`'ı off'a çevirdiğinde action önce ayar satırını yazıyor, sonra her user'da `two_factor_secret` / `two_factor_recovery_codes` / `two_factor_confirmed_at` alanlarını temizliyor. Loop ortasında bir fail, sistemi yarı-revoke durumunda bırakıyordu — ayar "2FA off" diyordu ama bazı user'ların aktif TOTP secret'ı hâlâ duruyordu. Tüm operasyon artık atomik.

- **`LogoutUserAction` null-safe token revoke.** API logout endpoint'i `$user->token()->revoke()` çağırıyordu; request controller'a aktif access token'sız ulaştığında (stale token, cache temizliği, worker race) zincirli çağrı `Error: Call to a member function revoke() on null` fırlatıp endpoint 500 dönüyordu. Artık `?->revoke()` kullanılıyor — token yokken bile temiz 204 dönüyor.

- **FileManager subtree walk'ları N sorgudan 1'e indi.** `BulkDeleteAction::collectDescendantIds` ve `DeleteFolderAction::collectDescendantIds` silinen klasörün alt ağacını yürürken hop başına bir `FileFolder::find` sorgusu atıyordu — 50 seviyelik ağaç 50 sıralı sorgu demekti, sibling sayısı arttıkça maliyet büyüyordu ve saldırganlara açık bir request-timing DoS kulbu veriyordu. İki action da artık owner-scoped `(id, parent_id)` map'ini tek `select` ile yükleyip ağacı PHP'de visited-set cycle guard ile yürüyor.

- **SMTP `encryption=none` artık TLS'i doğru devre dışı bırakıyor.** Publish edilen Mail ayarları ekranı "No encryption" seçeneği sunuyordu ama `SettingsServiceProvider` `'none'` string'ini `config('mail.mailers.smtp.encryption')`'a olduğu gibi yazıyordu. Laravel'in SMTP transport'u null dışındaki her değeri — `'none'` dahil — "bu TLS modunu kullan" olarak yorumluyor, yani kayıtlı "No encryption" ayarları ilk connect'te default STARTTLS upgrade'ine düşüyor ve offer etmeyen sunucularda fail edebiliyordu. Provider artık outbound config write'ında `'none' → null` eşlemesi yapıyor.

- **`ApiExceptionHandler` — exception mesajı sızıntısı + `X-Request-ID` log injection.** Exception→status mapping'in `default` arm'ı `config('app.debug') ? $e->getMessage() : 'A server error occurred.'` dönüyordu; `APP_DEBUG`'ın yanlışlıkla açık kaldığı her ortamda handle edilmemiş exception'lar API tüketicilerine stack-trace-grade detay sızdırıyordu. Handler artık generic mesajı koşulsuz dönüyor; debug detayı sadece `Log::error`'a ve zaten `APP_DEBUG` ile gated olan `debug` block'una yazılıyor. Trace id artık her zaman `Str::uuid()` ile sunucu tarafında üretiliyor; client'tan gelen `X-Request-ID` header'ı yalnızca charset + length-cap sanitizer'dan (`[A-Za-z0-9._-]`, ≤128 char) geçtikten sonra correlation metadata olarak kabul ediliyor ve `client_request_id` olarak log'lanıyor — kötü niyetli bir client artık uygulama log'una CRLF payload veya sahte trace id enjekte edemiyor.

- **`SecurityHeaders` HSTS direktifine `preload` eklendi.** Baseline HSTS header'ı `max-age=31536000; includeSubDomains`'ten `max-age=31536000; includeSubDomains; preload`'a çevrildi; deployment artık HSTS preload listesi için uygun. Paket `src/` katmanından geliyor — `composer update` ile otomatik.

- **Parola politikası 10+ / mixed case / digits / symbols seviyesine çıkarıldı.** `AppServiceProvider` artık proje-wide bir `Password::defaults(...)` kuruyor; default'a güvenen her FormRequest (register, password reset, password confirm, profile password change) otomatik devralıyor. Mevcut user'ların parolası invalidate olmuyor — yalnızca yeni parolalar daha sert kurala karşı ölçülüyor.

- **Axios CSRF + credential default'ları.** `resources/js/app.ts` artık `axios.defaults.withCredentials = true`, `xsrfCookieName = 'XSRF-TOKEN'`, `xsrfHeaderName = 'X-XSRF-TOKEN'` + `X-Requested-With: XMLHttpRequest` + `Accept: application/json` set ediyor. Admin UI Fortify endpoint'lerini (2FA, sessions, password-confirm) Axios üzerinden doğrudan çağırıyor; `withCredentials` ve XSRF header olmadan tarayıcı session cookie'sini gönderiyordu ama mutating request'lerde CSRF token'ı göndermiyordu — yani compromised bir origin, web flow'unun güvendiği CSRF check'ini bypass edebiliyordu.

- **2FA QR kodu `v-html` yerine `<img src="data:image/svg+xml;base64,...">` ile render ediliyor.** Fortify QR kodunu SVG string olarak döner. Önceki `v-html="qrCodeSvg"` çalışıyordu ama man-in-the-middle (veya bozulmuş bir Fortify override'ı) SVG'ye `<script>` / `onload` iliştirirse çalıştırırdı. Yeni yaklaşım SVG'yi base64'lü `<img>` data URL'ine çeviriyor — `<img>` sandbox'ı inline script'leri SVG içinde olsa dahi çalıştırmaz.

- **`useDefinition.load()` / `loadAll()` fail eden fetch'te `loaded.value = true` yapmıyor.** Composable datatable / form option dropdown'larını besleyen definition JSON'ının tek durak yükleyicisi. Daha önce `.then(r => r.json())` zincirini doğrudan kuruyordu — fetch fail ederse (network, 500, parse) `loaded.value` `true` kalıyor ve UI hiçbir konsol geri bildirimi olmadan stale / boş option listesi göstermeye devam ediyordu. İki metod da artık `try/catch` içinde, `res.ok` kontrolü var, hatalar konsola yazılıyor ve fail'da `loaded.value` `false` kalıyor böylece consumer retry edebiliyor.

- **On bir `FormRequest::authorize(): return true;` ihlali kapatıldı.** Şu request'ler — admin user store, API user store, admin role store, admin settings (auth/general/mail/storage/filemanager/turnstile), test-mail, destroy-sessions — artık `authorize()`'ı ilgili `*.create` / `*.update` permission check'ine delege ediyor (destroy-sessions sadece `$this->user() !== null` kontrolü yapıyor). `CheckResourcePermission` middleware'i zaten bunları route seviyesinde zorluyordu ama kontrolü request'e taşımak, controller action'ının off-route çağrıldığı (test, internal dispatch) veya action map'in yeni route isimleriyle drift ettiği anda açılacak defense-in-depth boşluğunu kapatıyor. Public auth endpoint'leri (`Api/Auth/*Request`) ve FileManager context-based request'leri bilinçli olarak dokunulmadı.

- **2FA challenge artık kesinlikle tek kullanımlık.** `TwoFactorChallengeAction` yanlış TOTP / yanlış recovery code / boş submit durumunda `api:2fa_challenge:{uuid}` cache entry'sini bırakıyordu; geçerli bir challenge id'yi ele geçirmiş saldırgan 5 dakikalık TTL × `throttle:5/min` penceresinin tümünü kod denemesi için kullanabiliyordu. Her fail arm'ı artık `Cache::forget($cacheKey)` çağırıyor — challenge id kesinlikle bir kez çalışıyor; sonraki denemeler `invalidChallenge()`'a düşüyor ve client yeni bir uuid almak için tekrar login olmak zorunda.

- **`SettingService::getValue` / `getGroup` `allGrouped()` cache'inden okuyor + `setGroup()` `DB::transaction`'da.** Sıcak okuma yolu, full `allGrouped()` sonucu için zaten bir cache katmanı olmasına rağmen çağrı başına bir sorgu atıyordu. Settings-yoğun request path'leri (Dashboard, FileManager, Admin sayfaları) request başına birkaç round-trip kazandı. Bulk write path'i de artık atomik — multi-setting save sırasında bir fail, DB'yi karışık durumda bırakmıyor.

- **`MoveItemRequest` — `item_type`'a göre `item_id` tiplendirmesi.** Kurallar her `item_type` için her `item_id` değerini kabul ediyordu. Effective kural artık `item_type=file` için `integer|min:1`, `item_type=folder` için `uuid` — DB şemasıyla birebir uyumlu; `item_type`'ın kendisi `string|in:...` string formu yerine `Rule::in([...])` kullanıyor.

- **`DeleteFolderRequest` — explicit FormRequest, çıplak `Request` yerine.** `FileManagerController::deleteFolder` önceden ham `Request` alıyor, context'i controller içinde kuruyor ve authorizer'ı doğrudan çağırıyordu. Yeni `DeleteFolderRequest`, `FileManagerRequest`'i extend ediyor, paylaşılan context kurallarını çalıştırıyor ve `$request->context()` expose ediyor — diğer FileManager endpoint'leriyle aynı yüzey; controller iki satır boilerplate düşürdü.

- **`UserController::uploadAvatar` artık explicit `Gate::authorize('update', $user)` çalıştırıyor.** `UploadAvatarRequest::authorize()` zaten `{user}` route parametresi bind'li olduğunda `UserPolicy::update`'e delege ediyor ama controller'daki ikinci Gate çağrısı view/update/delete'de kullanılan belt-and-braces pattern'ini yansıtıyor ve controller'ı yalnız başına okurken kontrolü görünür tutuyor.

#### Security — manuel operatör adımı

- **GV-H1 — Passport private keys rotation.** `.gitignore` kuralı düşmeden önce bu dosyaları commit etmiş legacy install'larda `storage/oauth-private.key` ve `storage/oauth-public.key` git history'de duruyor. [docs/UPGRADE.tr.md §6](UPGRADE.tr.md#6-gv-h1--passport-private-key-rotation-critical-manual) `git filter-repo` + `passport:keys --force` + `passport:purge` + takım-geneli `git reset --hard` akışını belgeliyor; bu adım paket tarafında otomatize edilemez. Repo'nuz key dosyalarını hiç commit etmediyse adımı atlayın.

#### Changed

- **`LOG_LEVEL` default'u artık `error`.** `.env.example` daha önce `LOG_LEVEL=debug` shipping ediyordu — production'da (verbatim commit edilirse) log'u SQL trace, Passport token debug'ı vb. ile dolduruyor, gürültülü ve zaman zaman hassas. Production profili `error` veya `warning` göndermeli.

- **`laravel/tinker` `require-dev`'e taşındı.** Tinker geliştirici kolaylığı — production dependency olarak shipping edilmesi PsySH'ı ve transitive zincirini her container build'e çekiyordu. Local dev `require-dev`'de olduğu için yine kuruyor.

- **`.env.example` Passport key + Turnstile placeholder'ları kazandı.** İki yorumlanmış `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` stub'ı env-based key-loading path'ini (önerilen alternatif, `storage/oauth-*.key` commit etmek yerine) belgeliyor; uncommented `TURNSTILE_ENABLED=false` + boş site/secret key'ler taze install'larda Turnstile middleware'ini admin açana kadar no-op yapıyor.

- **Inertia `appEnv` / `appDebug` shared prop'ları artık production'da sızmıyor.** `HandleInertiaRequests::share` önceden `config('app.env')` + `config('app.debug')` koşulsuz dönüyordu. Production'da bu, ortam adını her authenticated user'a sızdırıyor ve `APP_DEBUG`'ın açık olup olmadığını ilan ediyordu. İki anahtar da artık `app()->environment('production')` altında `null` / `false` dönüyor; non-prod'da dev overlay için gerçek değeri taşıyor.

- **CORS preflight cache'i 0'dan 7200 saniyeye çıkarıldı.** `config/cors.php` önceden `max_age => 0` shipping ediyordu, her mutating request'te tarayıcıya preflight rerun ettiriyordu. `max_age=7200` ile SPA / mobile client'lar OPTIONS cevabını 2 saat cache'liyor.

#### Fixed

- **`useDialog` / `useImageLightbox` — 300 ms timer leak.** İki composable da `close()`'ta 300 ms `setTimeout` başlatıyordu; exit animasyonu oynasın diye DOM'dan kaldırma işi geciktiriliyordu. Hızlı `open → close → open` sekansı iki timer kuyruğa alabiliyordu; sondaki, dialog yeniden açıldıktan sonra fire ediyor ve render'ı iptal ediyordu. Module-seviyesi timer ref'i artık hem `open()` hem `close()` girişinde temizleniyor; timeout gövdesi fire ettiğinde ref'i null'luyor.

- **`SkForm` dirty-form guard — parent prop update'leri user input'unu silmiyor.** `watch(derivedDefaults, ...)` block'u parent her yeni object gönderdiğinde form'u koşulsuz default'lara reset ediyordu. User form'u yarı doldurmuşken parent poll etse (örn. sibling datatable refresh paylaşılan-state update tetiklerse), yazılmakta olan input siliniyordu. Watcher artık `internalForm.isDirty`'i kontrol ediyor — form dirty ise yeni değerler default olarak kaydediliyor (sonraki bir `reset()` onları alsın diye) ama canlı form state'i korunuyor.

- **`SkDatatable` URL filtreleri — `api.get` + `Promise.allSettled`.** URL-driven filter loader çıplak `fetch(...)` + `Promise.all` kullanıyordu, yani bir filtrenin options endpoint'indeki tek bir 500, handle edilmemiş rejection ile tüm filter bar'ını zehirliyordu. Loader artık paylaşılan `api.get<T>()` helper'ını kullanıyor (Axios default'larını + XSRF'i devralıyor) ve `Promise.allSettled` kullanıyor; her filtre bağımsız, fail eden endpoint boş listeye düşüyor ve konsola warning basılıyor. Aynı dosyada `let activeMenuItems` → `const activeMenuItems` (ref hiç re-assign edilmiyordu).

- **`TwoFactorTab.enableTwoFactor` Inertia reload'u await ediyor.** Orijinal kod `router.reload({ only: [...] })`'u await etmeden fire ediyor, sonra hemen `loadQrAndSetupKey()`'e geçiyordu. Yavaş bağlantıda QR fetch reload'la race edip stale ekran render edebiliyordu. `router.reload` artık `onFinish`'te resolve olan bir promise'e sarılı.

- **`ProfileInfoTab` / `UserForm` — `as any` avatar cast'leri kaldırıldı.** İki `(x as any)?.avatar_url` erişimi tiplendirilmiş shape ile değiştirildi — davranış değişikliği yok ama cast, backing tip `avatar_url` accessor'ını kaybettiği anda ortaya çıkacak gerçek bir TypeScript hatasını gizliyordu.

- **`DashboardController::index` explicit `: Response` return type aldı.** Proje Larastan seviyesinde kalan son `return_type_missing` bulgusunu kapatıyor.

### Yükseltme

`composer update lvntr/laravel-starter-kit --with-all-dependencies` yalnızca paket `src/` katmanını (HSTS `preload`, stub güncellemeleri) alıyor. Yukarıdaki diğer her fix publish / stub-backed dosyalarda yaşıyor. Tam diff listesi ve smoke-test checklist'i için [docs/UPGRADE.tr.md](UPGRADE.tr.md).

## 2026-04-20 -v.13.3.3

### Patch sürüm — Builder core importları için Windows build düzeltmesi

#### Fixed

- **Windows production build `Could not load .../FormBuilder/core` hatasıyla patlıyordu.** `FormBuilder`, `DatatableBuilder` ve `TabBuilder` bileşenlerinin her biri, `index.ts`'i `@lvntr/components/<Builder>/core` olarak import edilen bir `core/` klasörüne sahip. Bazı Windows kurulumlarında Vite resolver'ı dizin→`index.ts` adımını atlayıp `vite:load-fallback`'e düşüyor, klasörü dosya gibi okumaya çalışıp `ENOENT` fırlatıyordu. Düzeltme: her üç builder için `core/` klasörünün yanına, `./core/index`'ten re-export yapan bir `core.ts` barrel dosyası eklendi; böylece import her platformda gerçek bir dosyaya rezolve oluyor. macOS/Linux davranışı değişmedi, `/core/builder` gibi mevcut subpath importları da etkilenmedi. Fixes lvntrdev/laravel-starter-kit#1.

## 2026-04-19 -v.13.3.2

### Patch sürüm — güvenlik sertleştirmesi, user audit event'leri, Logo API zarfı, media-delete policy, permission-middleware cache doğruluğu, test bootstrap

Tam test suite auditi sırasında ortaya çıkan gizli bug'ların toplu düzeltmesine ek olarak, admin user flow'undaki bir privilege-escalation yolunu kapatan, Settings ekranının SMTP/S3/Turnstile sırlarını frontend'e sızdırmasını durduran ve API auth akışını web akışıyla aynı seviyeye (email verification + iki-adımlı doğrulama) çeken bir güvenlik incelemesi yapıldı. Orijinal bug'ların çoğu sadece belirli runtime'larda (Octane/queue worker) veya `site:install` atlanmış taze clone'larda görünen, ya da user write'ları için audit log'u sessizce düşüren sorunlardı.

#### Security

- **Rol atamasında privilege escalation — admin user flow.** `StoreUserRequest` ve `UpdateUserRequest` eskiden `role` alanını yalnızca `Rule::exists('roles', 'name')` ile doğruluyordu; yani `users.create` veya `users.update` izni olan herhangi bir kullanıcı, admin UI dropdown'ının sunduğundan bağımsız olarak ham HTTP isteğiyle `role=system_admin` gönderebiliyordu — `Gate::before` üzerinden tüm yetki kapılarını bypass eden super-admin rolüne anında atlıyordu. Ayrıca `UpdateUserRequest` hedef kullanıcının rank'ine bakmıyordu; bu nedenle düşük rank'lı bir actor, kendinden üstün rank'lı birini (örn. `system_admin`) edit edip düşürebiliyordu. Düzeltme: `role` artık `Rule::in(...)` ile doğrulanıyor — liste, dropdown'ı besleyen hiyerarşi-farkında `RoleSelectOptionsQuery` tarafından üretiliyor (`sort_order >= actor'ın min sort_order`, `system_admin` non-system_admin actor'lara kapalı). `UpdateUserRequest::authorize()` da hedefin top-rank'i actor'un rank'inden yüksek olduğunda 403 dönüyor. Rolü olmayan ama Spatie direct permission ile `users.*` taşıyan bir actor, mümkün olan en düşük rank olarak muamele görür — hiçbir rol atayamaz ve kendisinden başkasını edit edemez; önceki `(int) null = 0` fallback'i kazara `system_admin` dahil tüm rol listesini açıyordu.

- **Settings sırları artık frontend'e sızmıyor.** Admin **Settings** sayfası `mail.password`, `storage.spaces_secret`, `storage.aws_secret` ve `turnstile.secret_key` değerlerini, `settings.read` izni olan her kullanıcıya Inertia prop olarak düz metin gönderiyordu. Yalnızca `.env`'de duran değerler bile `config()` fallback'i üzerinden sızıyordu. Düzeltme: `SettingsDefaultsQuery` her secret alan için `null` dönüyor ve yanında `*_is_set: bool` flag'i ekliyor. Admin UI değer set olduğunda `••••••••` placeholder'ı gösterip, form boş submit edildiğinde sıfırdan boş string yolluyor — backend bunu "mevcut değeri koru" olarak yorumluyor; boş olmayan değer yazılırsa üstüne yazar. Yeni `tests/Feature/Admin/Settings/SecretsDisclosureTest` Inertia payload'ının ham secret string'ini hiçbir yerde taşımadığını doğruluyor.

- **`storage.aws_secret` artık DB'de şifreli saklanıyor.** `config/settings.php` içindeki `sensitive_keys` listesine `storage.aws_secret` eklendi — daha önce `mail.password`, `storage.spaces_secret` ve `turnstile.secret_key` listede vardı ama AWS muadili yoktu, UI üzerinden kaydedilen S3 secret'ları `settings` tablosunda plaintext duruyordu. `SettingService` listedeki her anahtarı yazarken `Crypt::encryptString` ile şifreliyor, okurken çözüyor.

- **`check.permission` middleware'i production'da fail-closed.** Middleware, route'tan çözülen permission (örn. `users.index` için `users.read`) DB'de seed edilmemişse isteği geçiriyordu. Production'da bu, permission kaydı unutulmuş her yeni rotayı sessizce korumasız bırakmak anlamına geliyordu. Middleware artık `app()->environment('production')` altında çalışırken `AuthorizationException` (403) fırlatıyor, non-production ortamlarda ise seed edilmemiş permission'ı `Log::warning` ile kaydediyor — dev ergonomisi korundu, production foot-gun'ı kapatıldı.

- **Test-mail endpoint'i artık ham exception detayını yansıtmıyor.** `SettingsController::testMail()` eskiden SMTP exception mesajını (host / username / TLS detayları) tarayıcıya flash ediyordu. Mesaj artık `Log::error`'a sınıf + message context'iyle yazılıyor; kullanıcı yalnızca generic bir "Failed to send test email. Check the server logs for details." görüyor — aynı başarı/başarısızlık sinyali, bilgi ifşası olmadan.

- **API auth — email verification ve iki-adımlı doğrulama web flow'uyla paritede.** API eski durumda register ve her başarılı parola login'inde hemen access token veriyordu, web flow'unun zorunlu kıldığı email-verification ve 2FA checkpoint'lerini bypass ediyordu. Üç `POST /api/v1/auth/*` endpoint'i yeniden düzenlendi:
    - **`register`** — Fortify'ın `emailVerification` feature'ı açıkken (default), register'da token verilmiyor. Endpoint kullanıcıyı oluşturuyor, `Illuminate\Auth\Events\Registered` fırlatıyor (Fortify'ın notification pipeline'ı verification link'ini gönderiyor) ve `{ data: { user, requires_verification: true } }` + 201 dönüyor. Feature kapalıysa eski token-on-register davranışı korunuyor.
    - **`login`** — discriminated payload dönüyor:
        - `{ user, token }` — normal başarı
        - `{ requires_verification: true }` — credential'lar geçerli ama email verify edilmemiş (verification feature açıkken)
        - `{ requires_two_factor: true, challenge: "<uuid>" }` — credential'lar geçerli ama hesapta 2FA confirmed; tek kullanımlık bir challenge id veriliyor (5 dakikalık cache TTL). Henüz access token yok.
    - **`two-factor-challenge`** — yeni endpoint `POST /api/v1/auth/two-factor-challenge` (throttle `5/dk`). TOTP için `{ challenge, code }` veya `{ challenge, recovery_code }` kabul ediyor. Başarıda `{ user, token }` dönüyor. TOTP Fortify'ın `TwoFactorAuthenticationProvider`'ı ile doğrulanıyor; recovery code'lar `hash_equals` ile eşleştirilip `replaceRecoveryCode` üzerinden tüketiliyor, böylece yeniden kullanılamıyorlar. Geçersiz / bilinmeyen / süresi dolmuş challenge'lar 401 dönüyor.

    **API tüketicileri için breaking** — `register` / `login`'dan gelen her 2xx yanıtta `{ user, token }` bekleyen client'ler artık `data.requires_verification` ve `data.requires_two_factor` flag'lerine göre dallanmalı ve hesapta 2FA onaylıysa token almadan önce `/api/v1/auth/two-factor-challenge` endpoint'ini tamamlamalıdır. 2FA'sız, verify edilmiş kullanıcılar eski şekli görmeye devam ediyor.

- **Settings `required` validation'ı UI secret göstergesiyle uyumlu.** `UpdateMailSettingsRequest` ve `UpdateTurnstileSettingsRequest`, bir secret'ın "zaten set" olup olmadığına karar verirken yalnızca DB kaydına bakıyordu; eğer değer sadece `.env`'de duruyorsa UI'daki `*_is_set` flag'i `true` dönüyordu (çünkü `SettingsDefaultsQuery` `config()`'e fallback yapıyor) ama password / secret_key alanı boş bırakılmış form submit edilince kafa karıştırıcı bir `required` hatası veriyordu. `required` branch'i artık query ile aynı mantığı izliyor — DB satırı VEYA config fallback — yani env-destekli kurulumlar artık bu hatayı görmüyor.

- **Admin avatar upload / delete'te IDOR.** `POST /users/{user}/avatar` ve `DELETE /users/{user}/avatar` rotaları `CheckResourcePermission` altında herhangi bir permission'a map'lenmiyordu — çünkü route action'ları `uploadAvatar` / `deleteAvatar`, middleware'in `ACTION_ABILITY_MAP` tablosunda yoktu; middleware permission kontrolü olmadan `$next($request)` dönüyordu. `UploadAvatarRequest::authorize()` de koşulsuz `true` dönüyordu. Bu kombinasyonla, yalnızca `dashboard.read` taşıyan bir `user` rolü bile — email verified şartıyla — herhangi bir kullanıcının (sistem admini dahil) avatar'ını üzerine yazabiliyor veya silebiliyordu. Düzeltme: action map'ine `uploadAvatar => update` ve `deleteAvatar => update` eklendi; `UploadAvatarRequest::authorize()` route'ta `{user}` parametresi varsa `UserPolicy::update`'e delege ediyor (profile self-upload akışı aynı kalıyor); `SettingsController::deleteAvatar` açık olarak `Gate::authorize('update', $user)` çağırıyor.

- **Admin `UserController` ve API `UserController`: view / update / delete için rank-hiyerarşisi guard'ı.** `GET /users/{user}/data`, `GET /users/{user}/edit`, `DELETE /users/{user}`, `PATCH /api/v1/users/{user}` ve `DELETE /api/v1/users/{user}` yalnızca `users.read` / `users.update` / `users.delete` permission kontrolüne ve (yalnızca admin UI'daki) `UpdateUserRequest::authorize()` rank check'ine güveniyordu. Permission taşıyan düşük rütbeli bir admin, data endpoint veya API üzerinden yüksek rütbeli bir kullanıcıyı hâlâ okuyabiliyor veya silebiliyordu. Düzeltme: `UserPolicy::view / update / delete` artık aynı `canManage()` rank kontrolünü çalıştırıyor (system_admin bypass, rolsüz actor'lar en düşük rütbe sayılıyor). Admin ve API controller'ları her cross-user operasyonda `Gate::authorize('view' / 'update' / 'delete', $user)` çağırıyor. Admin ve API `UpdateUserRequest`'ler `authorize()`'ı `UserPolicy::update`'e delege ediyor; böylece rank kontrolü tüm akışlarda aynı.

- **`POST /api-routes/regenerate-docs` herhangi bir authenticated kullanıcı tarafından çağrılabiliyordu.** Route action'ı `regenerateDocs` da `ACTION_ABILITY_MAP`'te yoktu, bu yüzden `CheckResourcePermission` permission kontrolsüz `$next($request)` dönüyordu. Email verified her authenticated kullanıcı, sunucuda artisan komut çalıştıran OpenAPI regeneration'ı tetikleyebiliyordu. Düzeltme: `regenerateDocs => update` map'e eklendi; `config/permission-resources.php`'ye `api-routes.update` eklendi; seeder permission kaydını oluşturuyor.

- **Logo + FileManager'da SVG upload yasaklandı.** Hem admin logo uploader (`SettingsController::uploadLogo`) hem de FileManager default MIME listesi `image/svg+xml`'i kabul ediyor ve dosyayı `public` disk'e kaydediyordu. SVG `<script>`, `onload` ve foreignObject JavaScript'i gömebilir; mağdur direkt `/storage/...` URL'sini açtığında script app origin'de çalışır (stored XSS). Düzeltme: logo validation artık `mimes:png,jpg,jpeg,webp` + `dimensions:max_width=4096,max_height=4096` sabitliyor. `UploadFileRequest` içine `BLOCKED_MIMES` listesi (`image/svg+xml`, `image/svg`, `text/html`, `application/xhtml+xml`) eklendi — `file_manager.accepted_mimes`'te ne yazılı olursa olsun effective liste bu değerlerden arındırılıyor. `UpdateFileManagerSettingsRequest` kayıt sırasında bu MIME'ları `Rule::notIn(...)` + `^[a-z0-9.+-]+/[a-z0-9.+-]+$` regex ile reddediyor. Admin UI picker'ları (`MimePickerField`, `FileManagerTab`, `GeneralTab` logo input) artık SVG listelemiyor. `SettingsDefaultsQuery::fileManager()` eski install'lardaki seed'lenmiş SVG'yi UI'a göndermeden önce süzüyor; böylece geçmiş kurulumlar formda SVG'yi seçili görmüyor.

- **Avatar rule'u sertleştirildi.** `UploadAvatarRequest::rules()` eskiden `['required','image','max:2048']` idi — `image` rule'u SVG'yi kabul ediyor ve piksel boyutu için sınır koymuyordu, bu da polyglot dosyalar ve decompression-bomb PNG'ler için kapı açıktı. Yeni rule: `required | image | mimes:jpg,jpeg,png,webp | max:2048 | dimensions:max_width=4096,max_height=4096`.

- **`media-library.disk_name` artık default `local`.** Önceki default `public` idi — installer seeder başarısız olsa, admin FileManager disk toggle'ını değiştirse veya seeder atlansa, kullanıcının yüklediği belgeler dünya-okunabilir URL üzerinden servis ediliyordu. Default artık `local` olduğu için eksik konfigürasyon fail-closed; FileManager zaten indirmeleri `DownloadFileAction` üzerinden stream ediyor, public URL'e ihtiyacı yok.

- **`SESSION_ENCRYPT` + `SESSION_SECURE_COOKIE` default `true`.** `config/session.php` içinde `'encrypt' => env('SESSION_ENCRYPT', false)` ve `'secure' => env('SESSION_SECURE_COOKIE')` (null default) vardı. Bu env değişkenlerini set etmeyi unutan deploy'lar, HTTPS üzerinde bile plaintext session payload + secure bayraksız cookie gönderirdi. İki default de artık `true`; `.env.example` zaten ikisini `true` olarak shipping ediyor ve Herd HTTPS'le serve ettiği için local dev de etkilenmiyor.

- **`SecurityHeaders` middleware'i baseline CSP header ekliyor.** Middleware X-Frame-Options / X-Content-Type-Options / Referrer-Policy / Permissions-Policy / HSTS ekliyordu ama `Content-Security-Policy` yoktu. Kodbase'de iki `v-html` sink'i olduğu için (Fortify 2FA QR SVG ve DataTable `column.render` escape hatch) CSP saldırı alanını anlamlı ölçüde daraltıyor. Header yalnızca non-local ortamlarda set ediliyor — local dev'deki Vite HMR, script/connect/style için dev-server origin'ine ihtiyaç duyuyor ve bu değer geliştiriciye göre değişiyor, bu yüzden local'de tight CSP dev akışını bozmaktan başka işe yaramaz.

- **Scramble "Try It" production'da kapalı.** `config/scramble.php` `hide_try_it: false` + `try_it_credentials_policy: 'include'` ile shipping ediliyordu — production'da `api-docs.read` taşıyan herhangi bir admin'e, kendi session cookie'lerini her isteğe iliştiren in-browser API tester'ı sunuyordu. İki değer de artık `APP_ENV === 'production'` kontrolüne göre ayrılıyor (prod'da gizli + `omit`, local/staging'de interaktif).

- **Passport access-token TTL kısaltıldı, scope kataloğu eklendi.** Access token'lar 15 gün, personal access token'lar 6 ay geçerli tutuluyordu — sızan bir bearer token haftalarca kullanılabilir kalıyordu. Yeni default'lar: `access_token_minutes=60`, `refresh_token_days=14`, `personal_token_days=30`; eski `PASSPORT_TOKEN_DAYS` / `PASSPORT_PERSONAL_TOKEN_MONTHS` env anahtarları set edilmişse yine öncelikli, bu yüzden mevcut kurulumlar etkilenmiyor. `config/starter-kit.php` artık opt-in bir scope kataloğu (`users.read`, `users.write`, `files.read`, `files.write`, `admin`) shipping ediyor; `Passport::tokensCan()` önceden bağlı — spesifik API rotalarına `middleware('scope:...')` eklediğin anda per-scope erişim devreye giriyor.

- **API register / login artık `turnstile` middleware'ini çalıştırıyor.** Cloudflare Turnstile tarayıcı auth formları için `FortifyServiceProvider` + `ValidateTurnstile` üzerinden bağlıydı, ama API rotaları (`POST /api/v1/auth/register`, `POST /api/v1/auth/login`) yalnızca `throttle:5,1` limit'i taşıyordu — saldırgan dakika başına IP başına beş hesap açabiliyordu. İki rota da artık mevcut `turnstile` middleware alias'ından geçiyor; Turnstile settings'te kapalıysa middleware no-op, açıldığında API de aynı `cf_turnstile_response` kontrolünü devralıyor.

#### Fixed

- **User domain event'leri artık Create/Update/Delete'te dispatch ediliyor.** `App\Domain\User\Actions\CreateUserAction`, `UpdateUserAction` ve `DeleteUserAction` içindeki `UserCreated::dispatch(...)` / `UserUpdated::dispatch(...)` çağrıları daha önce yorumda veya eksikti — `DomainServiceProvider`'da register edilen listener'lar (audit-log listener dahil) user write'ları için hiç çalışmıyordu. `Create` ve `Update` artık gerçekten değişiklik olduğunda dispatch ediyor (no-op update `UserUpdated` fırlatmıyor); `Delete` ise silmeden önce id/email yakalayıp başarı halinde `UserDeleted` fırlatıyor — `Role*` action pattern'iyle aynı.

- **Admin `users.show` rotası 500 dönsüyordu.** `routes/web/user-route.php` içindeki `Route::resource('users', UserController::class)` örtük olarak `GET /users/{user}` rotasını açıyordu ama `UserController` hiç `show()` metodu taşımıyordu — o URL'ye her istek `BadMethodCallException` fırlatıyordu. Resource kaydı artık `->except(['show'])` ile daraltıldı; detay verisi admin UI'nın zaten kullandığı `GET /users/{user}/data` endpoint'inden okunuyor.

- **Settings logo endpoint'leri artık `ApiResponse` zarfı dönüyor.** `App\Http\Controllers\Admin\SettingsController` içindeki `POST /settings/logo` ve `DELETE /settings/logo` eskiden çıplak `response()->json([...])` / `response()->json(status: 204)` dönsüyordu — admin API'nın geri kalanının izlediği "her JSON yanıt `{ success, status, message, data }` zarfı taşır" sözleşmesini kırıyordu. Her ikisi de artık `to_api(...)` üzerinden geçiyor. Frontend (`GeneralTab.vue`) `json.data.logo_url` okuyor, şekil aynı.

- **`App\Policies\UserPolicy`'ye `delete` ability'si eklendi.** `DELETE /media/{media}` `MediaUploadController`'da `Gate::authorize('delete', $media->model)` çağırıyor. Medyanın sahibi bir `User` ise, `UserPolicy`'de `delete` tanımlı olmadığı için (sadece `view` ve `update` vardı) Gate fallback'ten deny'a düşüyor ve 403 dönüyordu — kendi avatar/dosyasını silmeye çalışan sahip için bile. Yeni `delete(User $actor, User $user)` metodu `update`'i birebir yansıtıyor: self her zaman izinli, aksi halde actor'un `users.delete` permission'ına ihtiyacı var.

- **`CheckResourcePermission` middleware: process-geneli cache yerine request-scoped cache.** Middleware içindeki permission-existence lookup'ı sonucu `static $cached` değişkeninde tutuyordu. Uzun ömürlü worker'larda (Laravel Octane, container'ı job'lar arası sıcak tutan queue worker'lar) bu cache hiç yenilenmiyordu — yeni oluşturulan permission kayıtları worker restart edene kadar görünmez kalıyordu. Daha kötüsü, test suite içinde static test'ler arası hayatta kalıyordu: `RefreshDatabase` `permissions` tablosunu truncate ediyordu ama middleware önceki test'in seed ettiği permission isimlerini hâlâ "var" olarak raporluyor ve permission'sız olması gereken rotalarda aralıklı 403'ler üretiyordu. Cache artık `app()->instance('check-permission.cache', ...)` ile saklanıyor — prod'da request-scoped, test container'ında test-scoped.

- **`UserFactory` `two_factor_*` kolonlarını default olarak `null` seed ediyor.** Eloquent strict mode (`Model::shouldBeStrict(! isProduction())`, `Lvntr\StarterKit\StarterKitServiceProvider` tarafından set ediliyor), kod taze bir factory instance üzerinde o kolonları okuduğunda (örn. `$this->actingAs(User::factory()->create())` sonrası `ProfileController`) "attribute [two_factor_secret] either does not exist or was not retrieved" fırlatıyordu. Factory artık `two_factor_secret`, `two_factor_recovery_codes` ve `two_factor_confirmed_at` için açık `null` yazıyor — in-memory model `->refresh()` gerekmeden üçünü de taşıyor.

- **`CreateUserAction` ve `UpdateUserAction` artık write + role sync'i transaction içinde çalıştırıyor.** `User::create(...)` ardından `->syncRoles(...)` transaction dışında koşuyordu — `syncRoles` başarısız olursa (bağlantı düşmesi, permission cache invalidation, role-not-found race) user row'u kalıyor ama rol atanmıyordu, admin listede tutarsız kayıt görünüyordu. İki action da artık `DB::transaction(...)` içinde çalışıyor; event dispatch commit'ten sonra yapılıyor, böylece listener'lar tutarlı state görüyor.

- **`MoveItemAction::wouldCreateCycle` artık her ancestor için SELECT atmıyor.** Method folder ağacını `FileFolder::find($parentId)` ile her hop'ta tek tek yürüyordu; N ancestor'ı olan bir klasör taşındığında N sorgu atılıyordu. Büyük ağaçlarda hem performans ayak tuzağı hem de slow-query DoS için potansiyel yoldu. Ancestor haritası artık tek sorguda yükleniyor (`SELECT id, parent_id WHERE owner_type=? AND owner_id=?`) ve walk in-memory, cycle-visited guard'ıyla yapılıyor.

- **Folder create / rename / move artık unique-constraint ihlallerini yakalıyor.** `CreateFolderAction`, `RenameFolderAction` ve `MoveItemAction` `(owner_type, owner_id, parent_id, name)` için check-then-act yapıyordu. Eş zamanlı iki istek exists kontrolünü eş anlı geçince, ikincisi temiz validation hatası yerine ham `QueryException` (500) veriyordu. Race pencere artık kapalı — her action SQL-state `23000` (veya MySQL 1062) yakalayıp localized `LogicException` fırlatıyor; controller'lar bunu zaten 422 + `sk-file-manager.errors.duplicate_folder` mesajına çeviriyor. Mevcut pre-check `parent_id=NULL` senaryosunu (MySQL/SQLite NULL'ı farklı saydığı için unique index korumuyor) korumak üzere duruyor.

- **`UserDatatableQuery` artık `media` relation'ını eager load ediyor.** `UserResource::$appends` `avatar_url` accessor'ını zorluyor, o da `$user->getFirstMedia('avatar')` çağırıyor. Datatable query yalnızca `roles` eager load ettiği için her satır ayrı bir media lookup tetikliyordu (N+1). `media` artık eager load listesinde; per-page render `1 + n` sorgudan `2`'ye düştü.

- **`RoleController@data` ve `@edit` artık `$role->toArray()` spread yerine `RoleResource` kullanıyor.** Spread eklemesi hızlı olmuştu ama projenin "response'lar bir Resource üzerinden geçer" konvansiyonunu kırıyor ve `roles` tablosuna eklenecek gelecekteki herhangi bir hassas kolonu otomatik yayınlayacaktı. Yeni `App\Http\Resources\Admin\Role\RoleResource` alanları açıkça listeliyor (`id`, `name`, `display_name`, `group`, `sort_order`, `guard_name`, `seeded_permissions`, timestamps + `permissions` yüklendiğinde conditional). Frontend payload şekli korundu.

- **`resources/js/pages/Admin/ApiRoutes/Index.vue`: external link'e `rel="noopener noreferrer"` eklendi.** "Open API Docs" anchor'ı `target="_blank"` kullanıyor ama rel attribute'u eksikti. Projenin geri kalanıyla tutarlı hale getirildi.

- **2FA disable confirmation dialog'u için eksik çeviriler.** `sk-setting.auth.two_factor_disable_title` ve `sk-setting.auth.two_factor_disable_warning` anahtarları Auth settings tab'ından referans ediliyordu ama lang dosyalarında tanımlı değildi. EN ve TR için eklendi.

#### Added

- **API test suite'i için Passport key otomatik üretimi.** `tests/Pest.php` artık `tests/Feature/Api` scope'una bir `beforeEach` hook'u kaydediyor — `storage/oauth-private.key` eksikse `passport:keys --force` çalıştırıyor. Taze clone ve CI runner'lar artık Passport-destekli testler (`AuthTest`, `UserTest`) geçsin diye `php artisan site:install` çalıştırmak zorunda değil — eski davranış `league/oauth2-server` tarafından atılan anlaşılmaz bir `LogicException: Invalid key supplied` idi.

- **`tests/Feature/Domain/User/UserEventsTest.php`.** Yukarıdaki fix'in getirdiği event-dispatch sözleşmesini kilitler — `UserCreated` create'te dispatch ediliyor, `UserUpdated` sadece takip edilen en az bir alan değiştiğinde fırlıyor, `UserDeleted` başarılı silmede fırlıyor, self-delete guard false dispatch üretmiyor.

- **`tests/Feature/Admin/SettingsTest.php`'ye logo upload/delete coverage'ı eklendi.** `POST /settings/logo` üzerinde `ApiResponse` zarfını (200 + `data.logo_url`) ve `DELETE /settings/logo` üzerinde 204 sözleşmesini kilitliyor.

## 2026-04-18 -v.13.3.0

### Özellik sürümü — Cloudflare Turnstile, last-login takibi, dosya önizleme modalları, shipping edilen `validation.php` ve `sk-*` çeviri namespace'i

Geniş bir sürüm. Birkaç bağımsız yeni özellik ve çeviri katmanında mimari bir değişiklik.

#### Eklenen

- **Auth akışlarında Cloudflare Turnstile captcha.** Login, register ve şifre sıfırlama formlarında artık bir Turnstile widget'ı (`resources/js/components/Auth/TurnstileWidget.vue`) render ediliyor ve token sunucu tarafında doğrulanıyor. Shipping: `turnstile` middleware alias'ı (`App\Http\Middleware\ValidateTurnstile`), ad-hoc validasyon için `App\Rules\TurnstileRule`, `App\Domain\Setting\DTOs\TurnstileSettingsDTO`, ve **Ayarlar → Turnstile** admin sekmesi (site key / secret key UI üzerinden yönetiliyor). Kurulum bazlı açılıp kapatılıyor; feature kapalıysa widget'lar temiz şekilde short-circuit olur.

- **Last-login takibi.** Yeni `App\Listeners\UpdateLastLogin` listener'ı `Illuminate\Auth\Events\Login`'e bağlı: her başarılı girişte user'a `last_login_at` ve `last_login_ip` yazılıyor. Kullanıcı detay sayfasında ve users datatable'ında sıralanabilir kolon olarak görünür.

- **Girişte pasif kullanıcı engeli.** `App\Providers\FortifyServiceProvider` artık authenticate olan kullanıcının status'ü `active` değilse login attempt'i reddediyor ve net bir hata dönüyor — session başlamıyor. Bir hesabı askıya almak için artık silmek gerekmiyor.

- **`FormBuilder.trans(bool)`.** Her field builder'a eklenen yeni fluent method (`FB.inputText()`, `FB.select()`, `FB.toggleSwitch()`, …). Label'ın çeviri anahtarı olarak mı (varsayılan, `true`) yoksa önceden çevrilmiş raw string olarak mı (`false`) render edileceğini belirler. Script içinde `trans('admin.example')` gibi çevrilmiş bir değer vermek istediğinde işe yarar — normalde form template'i `$t()`'i tekrar çağırdığı için anahtar bulunamaz, fallback'e düşer. `.trans(false)` ile template ikinci çeviri adımını atlar. Varsayılan davranış değişmedi; mevcut sayfalar hiç dokunmadan çalışmaya devam eder.

    ```ts
    FB.inputText().key('last_name'); // varsayılan — label → $t('validation.attributes.last_name')
    FB.inputText().key('x').label(trans('admin.example')).trans(false); // raw render, ikinci $t() çağrısı yok
    ```

- **Uygulama içi dosya önizlemeleri (lightbox + modal).** Yüklenen dosyalar — file manager'da ve her `FB.fileUpload()` form alanında — thumbnail'a veya dosya adına tıklandığında artık yeni tarayıcı sekmesi açmıyor. Resimler **tam ekran lightbox**'ta açılıyor (Google Drive tarzı: bulanıklaştırılmış koyu arkaplan, ESC ile kapanır, isim sol üstte). Resim olmayan dosyalar (PDF, video, audio, text) **mime-bazlı dialog**'ta açılıyor; dialog doğru viewer'ı embed ediyor (iframe / `<video>` / `<audio>`), file manager tarafında "İndir" butonu ve tanınmayan formatlar için "Yeni sekmede aç" escape hatch'i var. Lightbox tek bir global overlay — `AdminLayout`'ta `<AppDialog />` yanına register ediliyor; modal ise mevcut `useDialog` composable üzerinden açılan `FilePreviewModal` component'i.

- **Dosya Yöneticisi ayarlarında kategorize mime-type seçici.** **Ayarlar → Dosya Yöneticisi → Kabul edilen dosya türleri** eskiden uzun bir multiselect dropdown'du. Artık kategorize kart-checkbox ızgarası (Görseller / Dokümanlar / Arşiv) — her seçenek eşleşen dosya tipi ikonuyla birlikte. Dropdown listesinden daha kolay taranıyor, tıklama alanı tüm kart, alfabetik sıra yerine mantıklı gruplama var.

- **"Video yükleme" ve "Ses yükleme" için feature-toggle kartları.** Dosya Yöneticisi ayarlarındaki iki toggle, mime picker ile aynı kart estetiğini paylaşıyor — solda renkli ikon, ortada kalın başlık + kısa açıklama (örn. "MP4, WebM, MOV, MKV, AVI ve OGG video formatlarına izin ver."), sağda switch. Kartın herhangi bir yerine tıklamak toggle'ı çevirir.

- **`lang/{en,tr}/validation.php` artık kit ile shipping ediliyor.** Laravel'in default validation rule mesajları + hem Laravel validator'ının hem de FormBuilder / DatatableBuilder'ın kullandığı `attributes` ve `custom` bölümleri. `.label()` belirtilmediğinde FormBuilder ve DatatableBuilder, alan etiketini `validation.attributes.{key}` üzerinden otomatik çözer. Türkçe mesajlar Laravel-Lang/lang konvansiyonlarını takip ediyor. Tüketici uygulamalar bu dosyaları serbestçe düzenleyip yeni attribute label'ları ekleyebilir — özel bir translation loader'a ihtiyaç yok, her şey Laravel'in native translation sistemi üzerinden çalışıyor.

- **Rol ismi lokalizasyonu — zarif bir fallback zinciri ile.** Admin topbar / sidebar'da görünen (Inertia üzerinden `auth.role` olarak paylaşılan) rol etiketi artık üç adımda çözülüyor: önce `roles.display_name[locale]` veritabanından; sonra `config('permission-resources.display_names.roles.{name}.{locale}')` altındaki locale anahtarı; son olarak da `Str::headline($role->name)` — yani taze seed edilmiş `system_admin` gibi bir rol, hiçbir lokalize tanım yapılmamış olsa bile raw slug yerine "System Admin" olarak görünüyor.

#### Değişen — çeviriler `sk-*` namespace'ine taşındı

Shipping edilen her çeviri dosyası artık `sk-` dosya adı prefix'i taşıyor: `sk-admin.php`, `sk-auth.php`, `sk-button.php`, `sk-datatable.php`, `sk-menu.php`, `sk-setting.php`, `sk-user.php`, `sk-attribute.php`, `sk-file-manager.php`, `sk-activity-log.php`, … Shipping edilen tüm Vue sayfaları ve PHP kodu yeni anahtarları kullanıyor (`__('sk-button.save')`, eski `__('button.save')` yerine). Amaç: tüketici uygulamalar prefix'siz namespace'i özgürce sahiplensin (örn. `lang/en/admin.php`'yi starter kit menü metinleriyle çarpışmadan kendi dashboard string'leri için kullansın).

#### Kaldırılan

13.3 öncesi prefix'siz stub'lar — `stubs/lang/{en,tr}/{admin,auth,button,common,datatable,enums,file-manager,message,pagination,passwords,validation}.php` (21 dosya) — artık shipping edilmiyor. `sk-*` geçişinden sonra kit içinde hiçbir kod bunlara referans vermiyordu; taze kurulumlarda tutulması sadece kafa karıştırıyordu. Paket seviyesindeki **`starter-kit::` namespace'i dokunulmamış** — `__('starter-kit::admin.menu')` çağrıları hâlâ çalışıyor.

#### Düzeltilen

- **"Video yükleme" açık olsa bile `.ogg` video ve `.avi` dosyaları reddediliyordu.** Upload request'in `allow_video=true` branch'i yalnızca `video/mp4`, `video/webm`, `video/quicktime` ve `video/x-matroska` mime'larını whitelist'liyordu. `video/ogg`, `video/x-msvideo` ve `video/avi` eklendi; validation hata mesajlarındaki "İzinli tipler" listesine `.OGV` ve `.AVI` uzantı etiketleri de eklendi.

- **`npm run build` üzerindeki gereksiz uyarılar susturuldu.** Production build'den iki gürültülü uyarı temizlendi: (1) `@tailwindcss/vite` ve `@inertiajs/vite` tarafından basılan "Sourcemap is likely to be incorrect" uyarıları — iki plugin de transform'dan sonra sourcemap'i yeniden üretmiyor, runtime etkilenmiyor — artık `vite.config.ts` içindeki odaklı bir Rollup `onwarn` hook'u ile filtreleniyor (diğer uyarılar olduğu gibi geçmeye devam ediyor); (2) shipping edilen `SkDatatable.vue` ve `FileManager.vue` üzerinde çıkan `resolveDirective imported but never used` uyarısı — PrimeVue'nun `v-tooltip` / `v-ripple` direktifleri artık `<script setup>` bloğunda açıkça binding ediliyor (`const vTooltip = Tooltip`) ve template dinamik bir lookup yerine doğrudan referansa derleniyor.

#### 13.2.x'ten yükseltme

`sk:update` hash-aware çalışır: dokunmadığın dosyalar yeni sürümle değiştirilir; düzenlediğin dosyalar `skipped` veya `untracked` olarak raporlanır ve dokunulmaz. 13.3'ün birkaç özellik dosyası — `SettingsController`, `SettingsDefaultsQuery`, `FortifyServiceProvider`, `HandleInertiaRequests`, `AppServiceProvider` ve yeni FormRequest sınıfları — büyük ihtimalle bu listede görünecek ve ilgilenilmesi gerekecek.

1. Önce neyin skip/untracked olduğuna bak: `php artisan sk:update --dry-run`
2. `app/` katmanında lokal özelleştirmen yoksa tüm dosyalar için paket sürümünü kabul et:

    ```bash
    php artisan sk:update --force
    ```

3. Yeni çeviri dosyalarını elle kopyala (`sk:update` `lang/`'a dokunmaz):

    ```bash
    cp vendor/lvntr/laravel-starter-kit/stubs/lang/en/sk-*.php lang/en/
    cp vendor/lvntr/laravel-starter-kit/stubs/lang/tr/sk-*.php lang/tr/
    ```

4. `lang/en/` altında önceki `sk:install`'dan kalma `admin.php`, `auth.php`, … varsa artık öksüzler. Paket onlara referans vermiyor; kendi `__('admin.x')` çağrılarını `__('sk-admin.x')`'e taşıdıktan sonra silebilirsin.
5. `npm run build` — yeni `TurnstileWidget.vue` shipping edilen bir stub ve `Login/Register/ForgotPassword` tarafından import ediliyor. Taze kurulumlar otomatik alır. Dosyayı henüz almamış mevcut kurulumlarda build şu hatayla patlıyor: `Could not load resources/js/components/Auth/TurnstileWidget.vue`; `sk:update` dosyayı kopyalamış olmalı (mevcut dosya değil, yeni dosya), kopyalamamışsa şuradan al: `vendor/lvntr/laravel-starter-kit/stubs/resources/js/components/Auth/TurnstileWidget.vue`.

---

## 2026-04-16 -v.13.2.9

### `npm run build` — lang JSON çift import uyarısı giderildi

Tüketici projelerde `npm run build` her seferinde şu iki uyarıyı basıyordu:

```
(!) lang/php_en.json is dynamically imported by resources/js/app.ts but also statically imported by resources/js/app.ts, dynamic import will not move module into another chunk.
(!) lang/php_tr.json is dynamically imported ...
```

Sebep: `resources/js/app.ts` içindeki `i18nVue` resolve callback'i SSR ve client için iki ayrı `import.meta.glob('../../lang/*.json', ...)` çağrısı tutuyordu — biri `eager: true` (statik), diğeri normal (dinamik). Vite iki dalı da statik analiz ediyor, aynı dosyalar için hem statik hem dinamik import gördüğü için "dinamik dal ayrı chunk'a alınmayacak" diyordu. Dinamik dal aslında hiçbir kazanç sağlamıyordu çünkü dosyalar zaten statik bundle'daydı.

Tek eager glob'a indirildi, modül scope'una çıkarıldı, client'ta `Promise.resolve()` ile sarmalandı:

```ts
const langs = import.meta.glob<Record<string, string>>('../../lang/*.json', { eager: true });
const resolveLang = (lang: string) => langs[`../../lang/php_${lang}.json`];
app.use(i18nVue, {
    resolve: ssr ? resolveLang : (lang: string) => Promise.resolve(resolveLang(lang)),
});
```

Lang JSON dosyaları küçük (birkaç KB) olduğu için statik bundling'in bundle boyutuna etkisi sıfıra yakın — uyarı kalıcı olarak kaybolurken davranış aynı kalıyor.

---

## 2026-04-16 -v.13.2.8

### Daha temiz ilk kurulumlar

Yeni kurulumlar artık gereksiz geliştirme kalıntıları ve gürültülü örnek veriler taşımıyor.

- **`.env.example` temizliği** — tekrarlanan `DB_*` satırları ve eski örnek veritabanı adı kaldırıldı. Dosya artık yalnızca `your_database`, `your_username` gibi genel placeholder'ları tutuyor.
- **Frontend/kurulum temizliği** — gerekli olmayan geliştirme odaklı frontend tooling kayıtları çıkarıldı; böylece `npm install` daha temiz bir başlangıç yapıyor.
- **Daha az proje karmaşası** — yeni uygulamaya ait olmaması gereken yardımcı/tooling dosyaları artık gönderilmiyor.

---

## 2026-04-15 -v.13.2.7

### File manager upload — HTTP context'i için `crypto.randomUUID` fallback'i

File manager upload composable'ı, kuyruğa giren her dosya için `crypto.randomUUID()` ile geçici bir id üretiyordu. Bu API yalnızca secure context'te (HTTPS ya da `localhost`) tanımlı — dolayısıyla düz HTTP bir dev domain'inde çalışan tüketiciler (Herd'in `.test`'i, çıplak bir intranet IP'si vs.) `TypeError: crypto.randomUUID is not a function` alıyor ve upload ilk XHR atılmadan ölüyordu.

`useFileManager` artık üç kademeli fallback'e sahip lokal bir `generateTempId()` helper'ından geçiyor:

1. Varsa `crypto.randomUUID()` (HTTPS / localhost)
2. `crypto.getRandomValues(new Uint8Array(16))` hex olarak seri hale getirilmiş (her modern tarayıcıda var, secure-context gerektirmez)
3. Son çare olarak `Date.now().toString(16)` + `Math.random().toString(16)`

tempId yalnızca bir pending-upload satırını tamamlanma/hata callback'iyle eşleştirmek için kullanılıyor — kriptografik güce ihtiyaç yok, bu yüzden fallback güvenli.

### Güvenlik başlıkları — geolocation kendi kaynağından izinli

`SecurityHeaders` middleware'indeki `Permissions-Policy` `geolocation=()` idi (tamamen reddediliyordu). `geolocation=(self)` olarak değiştirildi; böylece first-party script'ler meşru bir ihtiyaç olduğunda geolocation isteyebiliyor — üçüncü taraf frame'ler hâlâ bloklanıyor.

---

## 2026-04-15 -v.13.2.6

### File manager validation mesajları — okunabilir, lokalize, dosya adıyla

File manager'da sunucu reddi durumunda toast'lar artık gerçekten görünüyor ve Laravel'in ham `files.0 field must be a file of type: image/webp` mesajı yerine anlamlı bir Türkçe mesaj taşıyor.

- **Toast group bug fix** — `FileManager.vue` içindeki tüm `toast.add()` çağrılarına `group: 'bc'` eklendi. Ortak `ToastComponent` `group="bc"` ile mount edildiği için bu anahtar olmadan gönderilen toast'lar sessizce düşüyordu. Klasör oluştur/yeniden adlandır/sil/taşı ve dosya yükleme (başarı + hata) toast'ları artık tekrar görünüyor.
- **Sunucu hata mesajı çıkarımı** — Upload XHR önceden 422 cevabında sadece `envelope.message` ("Validation error.") okuyordu. Composable artık `envelope.errors`'u dolaşıp ilk alan-bazlı mesajı çıkarıyor; toast asıl gerekçeyi taşıyor.
- **Dosya başına anlamlı validation mesajı** — `UploadFileRequest` `attributes()` ve `messages()` metodlarını override ediyor. Her `files.{i}` slot'u dosyanın `getClientOriginalName()`'ine bağlı (toast `files.0` yerine `vacation.jpg yüklenemedi: …` diyor). Mimetypes / max-size hataları okunabilir uzantı listesi (`İzinli tipler: WEBP, PDF, JPG, …`) ve insanca boyut limiti (`en fazla 10 MB`) ile çevrildi.
- **Çeviri anahtarları** — `errors.upload_invalid_type`, `errors.upload_too_large`, `errors.upload_invalid_file` `lang/{en,tr}/file-manager.php`'ye eklendi.

İki yeni feature testi mesajları kapsıyor: orijinal dosya adı kontrolü ve okunabilir boyut limiti. File manager + install + publish suite'leri tamamen yeşil (22/22 + 11/11).

### Helpers yeniden organize — vendor-owned core, user-owned custom, publishable override

`to_api()` ve `format_date()` (artı iki yeni helper — aşağıda) artık paket vendor'undan geliyor ve otomatik autoload ediliyor. Son kullanıcı uygulamaları `to_api` kopyasını `app/` altında tutmuyor — bu da her `sk:update`'te ortaya çıkan merge baş ağrısını ortadan kaldırıyor.

- **`vendor/lvntr/laravel-starter-kit/src/sk-helpers.php`** kanonik konum. Paketin `composer.json` `autoload.files`'ı üzerinden register ediliyor; `composer require` ile birlikte helper'lar anında geliyor.
- **`app/Helpers/custom.php`** son kullanıcı uygulamasına ilk install'da basılıyor, app'in `composer.json` `autoload.files`'ına ekleniyor ve `sk:update` ile **asla** üzerine yazılmıyor. Kullanıcının kendi global helper'ları buraya yazılır.
- **`app/helpers.php` deprecated.** `sk:update` mevcut dosyanın md5'ini bilinen stock hash listesiyle karşılaştırıyor; eşleşirse dosya sessizce siliniyor. Kullanıcı kendi fonksiyonlarını eklediyse dosya konsol uyarısıyla yerinde bırakılıyor — kullanıcı kodu korunuyor. `composer.json` autoload entry'si yalnızca dosya gerçekten silinince yeniden yazılıyor; sessizce kullanıcı kodu kırılmıyor.
- **İki yeni helper** — `definition($key, $value)` `DefinitionService`'ten eşleşen definition kaydını (object) döndürüyor; `definitionLabel($key, $value)` onun `label`'ını döndürüyor. Enum-style değerleri görüntülenecek string'e çevirirken her çağrıda definition listesini tekrar çekmeden işe yarıyor.

### `sk:publish --tag=helpers` — paket helper'larını fork etmeden override et

Yeni bir tag `sk-helpers.php`'yi publish komutuna açıyor. Publish sonrası dosya `app/Helpers/sk-helpers.php`'ye iniyor, kullanıcı serbestçe düzenleyebiliyor.

Vendor dosyası autoload anında published kopyayı tespit edip `require_once` ile route ediyor:

```php
$skPublishedHelpers = dirname(__DIR__, 4).'/app/Helpers/sk-helpers.php';
if (is_file($skPublishedHelpers) && realpath($skPublishedHelpers) !== realpath(__FILE__)) {
    require_once $skPublishedHelpers;

    return;
}
```

Realpath guard, dosya published kopya olarak yüklendiğinde self-recursion'ı engelliyor. `composer.json` değişikliğine gerek yok — composer autoload yine vendor dosyasını tetikliyor, o da kullanıcı dosyasına delegate ediyor. Published dosya silindiğinde anında vendor implementasyonuna geri dönülüyor.

`sk:publish` interaktif prompt'una dördüncü seçenek geldi: **Global Helpers (sk-helpers.php)**.

---

## 2026-04-14 -v.13.2.4

### Tip güvenliği turu — `vue-tsc` ve ESLint sıfır uyarı

Starter kit kaynak kodu artık `vue-tsc --noEmit` ve `eslint 'resources/js/**/*.{ts,vue}'` altında 0 hata / 0 uyarı ile geçiyor. Davranışta değişiklik yok, tamamen tip ve lint temizliği.

- **tsconfig tekilleştirme** — tip tarama yolları sadeleştirildi; aynı UI kaynakları artık iki kez taranmıyor. Böylece lokal geliştirmede kafa karıştıran duplicate hatalar kaldırıldı.
- **Vite `Components` plugin tek kaynak** — `dirs` artık yalnızca `resources/js/components` tarıyor; paket yolu kaldırıldı. Auto-generated `components.d.ts` artık source yollarına referans veriyor.
- **SkDatatable filter tipleri genişletildi** — `activeFilters` tipi `string | number | Date | (Date | null)[] | null` ile tek `FilterValue` alias'ı üzerine oturtuldu. DatePicker kullanımları `v-model` → `:model-value` + `@update:model-value` ile güvenli cast'lere dönüştürüldü; `select`, `select-button`, `date`, `daterange` filtrelerinin her biri kendi tipinde çalışıyor.
- **Tag icon / pagination i18n fix'leri** — `:icon` ifadesi `?? undefined` ile null sızmasını kapatıyor, `datatable.records_info` çevirisine geçilen `from/to/total` parametreleri artık `String(... ?? 0)` ile zaten beklenen `string` tipine uyuyor.
- **`SharedPageProps` index signature** — `PageProps` constraint'ini karşılayacak şekilde `[key: string]: unknown` eklendi. `useCan()` artık `usePage<SharedPageProps>()` generic'iyle temiz derleniyor.
- **`env.d.ts` auth şekli gerçekle hizalandı** — Inertia `sharedPageProps.auth` artık `{ user, role, role_names, permissions }` tutuyor; AdminHeader'daki `page.props.auth?.role` okuması ve benzerleri doğru tiplerle resolve oluyor. `appEnv`, `appDebug`, `locale`, `availableLocales` da tiplenmiş şekilde shared prop'larda.
- **Küçük prop / cast düzeltmeleri** — `RoleForm.vue` Wayfinder `update.url({ id })` şeklinde çağrılıyor (optional `id`'yi narrowing ile geçiyor), `Settings/Index.vue` `general` tipine `logo_url: string | null` eklendi, `Dashboard/Index.vue` selamlamada `user?.name` yerine mevcut alan olan `user?.first_name` kullanıyor, Inertia v3'te zaten default olan `preserveScroll: true` opsiyonu `router.reload()` çağrılarından kaldırıldı.
- **ESLint uyarıları** — `SkDatatable` içindeki `v-html` gerekçeli disable-next-line yorumuyla işaretlendi (render string'i author-tanımlı ve escapeHtml helper'ı sunuluyor). `Breadcrumb.rootLabel`, `FileGrid.emptyLabel` ve `SkTag.{value,icon,color,severity}` prop'larına `withDefaults` ile default değerler verildi.

Mevcut uygulamalar için aksiyon gerekmiyor — değişiklikler davranışsal değil, tip/lint düzeyinde.

## 2026-04-14 -v.13.2.3

### Installer DX — AST tabanlı enjeksiyon, bootstrap helper, preset uyarıları

Fresh bir Laravel üzerinde `composer require lvntr/laravel-starter-kit` akışını daha güvenli ve daha az invaziv hale getiren installer/upgrade ergonomi turu.

- **AST tabanlı config enjeksiyonu** — `sk:install` artık `config/app.php`, `config/filesystems.php` ve `config/media-library.php`'yi `nikic/php-parser` ile format-preserving pretty print kullanarak düzenliyor. Regex tabanlı patch kaldırıldı; farklı Laravel config formatlarına toleranslı ve tamamen idempotent (bir kere inject edildikten sonra `sk:install`'ı tekrar çalıştırmak no-op).
- **Bootstrap helper sadeleştirmesi** — middleware ve exception wiring'i tek bir ortak bootstrap helper üzerinden akıyor; bu da kurulum ve güncelleme davranışını daha öngörülebilir hale getiriyor.
- **`bootstrap/app.php` artık ezilmiyor** — stub kopyası kaldırıldı. Bunun yerine installer kullanıcının mevcut Laravel default dosyasına **sadece üç satır AST-inject ediyor**: `withRouting(...)` içine `api: __DIR__.'/../routes/api.php'`, `withMiddleware` / `withExceptions` closure'larına ise `Bootstrap::middleware(...)` / `Bootstrap::exceptions(...)` çağrıları. Kullanıcının eklediği middleware, trusted proxies, custom exception reporter vs. korunuyor.
- **`bootstrap/providers.php` artık ezilmiyor** — installer array'e `DomainServiceProvider`, `FortifyServiceProvider`, `SettingsServiceProvider` ekliyor (idempotent, zaten kayıtlı olanları atlıyor); kullanıcının mevcut provider'larına dokunmuyor.
- **`package.json` JSON-merge** — blind overwrite yerine akıllı merge: ortak dependency'lerde stub versiyonu kazanıyor, kullanıcının eklediği dep/script/workspace/root-level key'ler korunuyor.
- **Lang dosyaları için first-install tespiti** — `lang/*` re-install'da hâlâ preservable (customization kaybolmaz), ama gerçek ilk install'da (hash registry yok) installer artık force-copy yapıyor; böylece fresh projeler Laravel'in cılız default lang dosyalarıyla kalmıyor ve starter kit UI eksik çeviri göstermiyor.
- **Ölü kod temizliği** — aktif akışın parçası olmayan eski `IdentityType` ve `YesNo` enum'ları yeni kurulumlardan ve güncelleme akışından çıkarıldı.
- **IdeHelper temizliği** — yeni kurulumlarda `AppServiceProvider` artık gereksiz `class_exists(IdeHelperServiceProvider::class)` kontrolünü taşımıyor.
- **Açık `nikic/php-parser ^5.0` bağımlılığı** — Tinker üzerinden dolaylı yoldan zaten kuruluydu, şimdi pakete direkt dep olarak eklendi.
- **"Bare Laravel" kurulum uyarısı** — README (EN/TR) ve [install.md](./install.md) / [install.tr.md](./install.tr.md) en üstte uyarıyla açılıyor: starter kit'ten önce `install:inertia`, `install:api`, Breeze, Jetstream veya benzeri preset'leri **çalıştırmayın** — preset'ler starter kit'in de yayınladığı controller/route/sayfa/layout'ları oluşturur, installer bunları tespit edemez ve yetim "ölü kod" olarak kalırlar.
- **Testler** — 12 yeni `InstallCommandTest` senaryosu: AST config enjeksiyonu (üç dosya için), idempotency, format/yorum koruma, `package.json` merge, first-install tespiti, bootstrap app/providers AST enjeksiyonu + user-code koruma. Toplam installer test suite 20/20 yeşil.

Mevcut kurulumlar için aksiyon gerekmiyor — installer tarafındaki tüm değişiklikler geriye dönük uyumlu, first-install tespiti ya da idempotent guard'larla korunuyor.

## 2026-04-14 -v.13.2.2

### FileManager — `ContextRegistry` ile pluggable context'ler

FileManager artık `user` / `global` ile sınırlı değil. Her Eloquent model'i klasör ağacına sahip olabilir; **service provider'a tek satır bile yazmadan**.

- **Yeni `ContextRegistry` servisi** (`app/Domain/FileManager/Support/`) bir context anahtarını üç adımda çözer: explicit `register()` → Laravel morph-map alias → `App\Models\{Studly(key)}` convention fallback. Tanımsız anahtar yine validation üzerinden 422 döner.
- **Sıfır-konfig custom context** — model class'ı + karşılığında bir policy (`view` / `update`) yeterli:
    ```vue
    <FileManager context="vehicle" :context-id="vehicle.id" height="100%" />
    ```
- **`global` registry içine gömüldü** — önceden `AppServiceProvider::boot()`'ta duran kayıt artık `ContextRegistry` constructor'ında. Starter kit'i adopt ederken FileManager için boot-time kurulum gerekmez. `AppServiceProvider` sadece singleton binding yapıyor.
- **`user` tamamen auto-resolve** — `App\Models\User` convention + yeni paketle gelen `app/Policies/UserPolicy.php` (self + `users.read` / `users.update`). `user` için explicit kayıt kaldırıldı, yetki policy'ye taşındı.
- **Self-match kısa yollu default authorizer** — auto-resolve edilen context'lerde actor kendi kaydına dokunuyorsa (actor IS owner) otomatik izin. Diğer istekler Laravel policy'lerine delegate: okuma `can('view', $owner)`, yazma `can('update', $owner)`.
- **MorphMap uyumu** — `FileManagerContextDTO` artık `ownerType`'ı `$owner->getMorphClass()` ile saklıyor; model morph-map alias'ı olsa bile query ve path üretimi tutarlı.
- **Runtime-driven validation** — `FileManagerRequest` sabit `in:user,global` kuralı yerine çalışma anında `ContextRegistry`'ye soran bir closure kullanıyor. Yeni context tanımlamak için hiçbir Request dosyası güncellenmiyor; `context_id` yalnızca kayıtlı path'te `{id}` varsa zorunlu.
- **Custom key'ler için frontend tip gevşemesi** — `FileManagerContext` artık `'user' | 'global' | (string & {})`. `<FileManager context="vehicle" />` tamamen tip-güvenli, built-in anahtarlarda autocomplete kaybolmuyor.
- **Upload dayanıklılığı** — `UploadFileRequest`, `file_manager.accepted_mimes` ayarı seed edilmemiş fresh install'larda makul bir MIME listesine (image / pdf / office / text) fallback ediyor; "file must be of type: ." 422 hatası yok.
- **Testler** — yeni `CustomContextTest` dosyası: explicit register, path override, folder listing, tanımsız-context reject ve morph-map auto-resolve. 26/26 FileManager testi geçiyor.
- **Doküman** — [file-manager.tr.md](./file-manager.tr.md) "Özel (custom) context'ler" bölümü aldı: çözüm sırası, zero-config walkthrough, `VehiclePolicy` örneği, contract tablosu ve override rehberi.

## 2026-04-14 -v.13.2.1

### FileManager — UX rötuşları ve takip iyileştirmeleri

13.2.0 sürümünün ardından gerçek kullanımdan çıkan bir iyileştirme turu eklendi:

- **Önizleme modalı** — dosya tile'ına tek tık veya sağ tık **Aç** artık 90vw'lik bir modal açıyor; resim, PDF, video, ses ve metin dosyaları inline preview; diğer tipler için "Yeni sekmede aç" + "İndir" aksiyonları.
- **Tile bazlı yükleme progress'i** — her dosya ayrı XHR ile yükleniyor; grid'de optimistic placeholder tile üstünde dolan progress bar gösteriliyor. Başarısız yüklemeler dismissable hata tile'ı olarak kalıyor; başarılı olanlar liste yenilendiğinde yerine geçiyor. Toolbar Upload butonu toplu yükleme sırasında spinner'a dönüyor.
- **Drag-and-drop taşıma** — tile'lar `draggable`; bir klasör tile'ına bırakılınca seçili tüm öğeler hedef klasöre taşınıyor. External (OS) dosya sürüklemesi `Files` data-transfer tipi üzerinden ayırt ediliyor — internal drag artık upload overlay'ini tetiklemiyor.
- **Klasör ağacı picker'lı Move modalı** — folder ve file context menülerinde **Taşı** aksiyonu; açılan dialog'ta `FolderTree` ile hedef klasör seçiliyor. Tek ve çoklu seçim destekleniyor.
- **Busy overlay (modal kart)** — Sil / Taşı / Yeniden Adlandır operasyonlarında FileManager alanının üstüne beyaz modal kart (spinner + başlık + açıklama) çıkıyor; toplu operasyonlarda "N öğe kaldı" canlı sayaç + **Durdur** butonuyla döngü iptal ediliyor.
- **Her zaman görünür seçim checkbox'ı** — her folder/file tile'ının sağ üstünde primary-dolu seçili / outline hover-opaq çıkan checkbox var. Klasörlere **çift tıkla aç** davranışı geri geldi; tek tık sadece seçer. Dosyalar tek tıkla preview'a gidiyor. Tile üstündeki 3-nokta menüler kaldırıldı — sağ tık tek giriş noktası.
- **Sağ tık artık zorla seçmiyor** — seçili olmayan bir tile'a sağ tık mevcut seçimi bozmuyor; bulk aksiyonlar sadece sağ tık yapılan öğe zaten seçimdeyse tetikleniyor.
- **Klavye kısayolları** — `Ctrl/Cmd + A` mevcut klasörün tümünü seçer, `Delete` / `Backspace` seçimi siler (confirm'lü), `Esc` seçimi temizler. Input içindeyken veya dialog açıkken tetiklenmiyor.
- **Breadcrumb yeniden tasarlandı** — PrimeVue breadcrumb yerine chip/pill stil crumb'lar, arada chevron, konum info bar'ın altına alındı. Uzun klasör isimleri `maxChars` (default 18) ile kesiliyor, `…` ile. Tam ad `title` tooltip'inde.
- **Başlıkta mevcut klasör + geri butonu** — sol folder tree kaldırıldı (sadece Move picker'da kullanılıyor). Ana alan artık klasör ikonu + mevcut klasör adı; root değilken sol tarafta `←` butonu.
- **Boş klasör illüstrasyonu** — boş klasörlerde büyük outline folder SVG'si + başlık + iki satır ipucu ("Sürükle bırak / Yükle" ve "Yeni Klasör").
- **Info bar'da aggregate istatistik** — dosya sayısı + toplam boyut artık mevcut klasörün tüm alt ağacını tarıyor; yalnızca o klasörün direkt dosyalarını değil.
- **Diskler arası download** — `DownloadFileAction` `Storage::disk($media->disk)->download(...)` kullanıyor; local, S3 ve DigitalOcean Spaces için aynı şekilde çalışıyor.
- **Context menu yeniden stillendi** — beyaz rounded kart, daha büyük item padding, folder ve file menülerinde **Sil**'den önce separator.
- **Sıralama yönü tooltip'i** — asc/desc toggle butonunda dinamik PrimeVue tooltip'i ("Artan sıralama · Azaltana geç" / EN). Yan etki olarak `Tooltip` directive'i `app.ts`'de global kaydedildi.
- **Footer kredisi** — `AdminFooter` sağda _Crafted with **Lvntr Starter Kit**_ linki (lvntr.dev'e bağlı).

Güncellenmiş kullanım, prop ve composable çıktıları için [file-manager.tr.md](./file-manager.tr.md).

## 2026-04-14 -v.13.2.0

### FileManager — dosya yöneticisi modülü

Yeni bir **FileManager** modülü eklendi: Windows Explorer tarzı bir UI ile kullanıcı-bazlı veya global dosyalar için tam kapsamlı dosya yönetimi.

- **Nested klasörler** — oluştur, yeniden adlandır, taşı, cascade sil
- **Çoklu dosya yükleme** — drag & drop veya butonla
- **Seçim** — tek tık, `Ctrl/Cmd + tık`, kauçuk-bant (rubber-band) fare ile toplu seçim
- **Toplu silme** — toolbar butonu veya seçili öğeye sağ tık
- **Sıralama** — isim / boyut / tarih + asc/desc
- **Türe göre önizleme** — resim thumbnail + PDF/Word/Excel/Video/Ses/Arşiv için renk kodlu simgeler
- **Bilgi çubuğu** — mevcut klasörün dosya sayısı ve toplam boyutu
- **Context menüler** — klasör/dosya/boş alan için ayrı aksiyonlar (Yeni Klasör, Yükle, Tümünü Seç, Yenile)

Eklenen sayfalar: ana menüde **Dosyalar**, `Admin > Kullanıcılar > Düzenle` sayfasında **Dosyalar** tab'ı. Maksimum dosya boyutu, kabul edilen MIME tipleri ve video/ses toggle'ları `Admin > Ayarlar > Dosya Yöneticisi` altından yapılandırılabilir.

Depolama: `user/{id}/files/{uuid}/...` ve `global/files/{uuid}/...` — klasör taşıma tamamen mantıksal, fiziksel dosya hareketi yok.

Kullanım ve API detayları için [file-manager.tr.md](./file-manager.tr.md).

## 2026-04-13 -v.13.1.10

### FormBuilder — stale form reset düzeltmesi

`FB` ile oluşturulan formlarda, Inertia `back()` navigasyonu veya `formConfig`'in yeniden hesaplanmasına yol açan herhangi bir `page.props` tazelenmesi sonrasında formun sessizce eski (stale) remote data'ya resetlenmesine yol açan hata düzeltildi. İç `SkForm` artık yeni türetilmiş default değerleri öncekilerle shallow-karşılaştırır ve değerler aynıysa reset'i atlar — böylece kullanıcının devam eden düzenlemeleri korunur.

Etkilenen: config'i `page.props`'a bağımlı olan tüm `FB` formları (örn. koşullu `isFieldsLocked`, `isSelf`, auth'a göre alan görünürlüğü). API değişikliği yok — mevcut formlar otomatik olarak yararlanır.

## 2026-04-13 -v.13.1.8

### FormBuilder — ColorSelector çıktı formatı

`FB.colorSelector()` artık `.format()` ve `.defaultTone()` ile yapılandırılabilir çıktı formatlarını destekliyor:

- `format('name')` _(varsayılan)_ → `"blue"` kaydeder
- `format('name-tone')` → `"blue-500"` kaydeder
- `format('hex')` → `"#3b82f6"` kaydeder

`'name-tone'` ve `'hex'` formatlarında dropdown'un altında tıklanabilir bir tone seçici çıkar; seçilen değer tone pill'lerinin yanında gösterilir. Modele başlangıçta bir hex string geldiğinde, component Tailwind paletinde ters arama yaparak eşleşen renk + tone seçimini geri yükler.

Detaylar için [formbuilder.tr.md](./formbuilder.tr.md#colorselector-alan-apisi).
