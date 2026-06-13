# FormBuilder

`SkForm`, `FB` ile üretilen fluent konfigürasyona göre dinamik formlar oluşturur. PrimeVue bağlantısını, definition yüklemeyi, bağımlı select'leri, dosya yüklemeyi ve yetki tabanlı salt-okunur modu kendi yönetir — sayfa katmanı ince bir render katmanı olarak kalır.

## İmportlar

```ts
import { FB } from '@lvntr/components/FormBuilder/core';
import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
```

## Temel Kullanım

```vue
<script setup lang="ts">
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import users from '@/routes/users';

    const formConfig = FB.form()
        .cols(2)
        .cardTitle('sk-user.create')
        .submit({
            url: users.store.url(),
            method: 'post',
        })
        .addFields(
            FB.inputText().key('first_name'),
            FB.inputText().key('last_name'),
            FB.inputText().key('email').inputType('email'),
            FB.inputMask().key('phone').mask('(999) 999-9999').unmask(),
            FB.select().key('status').definitionOptions('userStatus').default('active'),
            FB.select().key('gender').definitionOptions('gender'),
            FB.password().key('password').toggleMask(),
        )
        .build();
</script>

<template>
    <SkForm :config="formConfig" />
</template>
```

## İki Çalışma Modu

### Dahili submit modu

`submit(...)` tanımlanırsa `SkForm`, kendi içinde Inertia `useForm()` yönetir.

### Harici model modu

`submit(...)` yoksa `v-model` ile form verisini kendin yönetebilirsin.

```vue
<SkForm v-model="formData" :config="formConfig" :errors="errors" />
```

## Form Builder API

- `layout('vertical' | 'horizontal')`
- `cols(number)` — form grid sütun sayısı (1–12 aralığının tamamı desteklenir; daha önce 6'nın üzerindeki değerler varsayılan 2 sütunlu düzene düşüyordu)
- `class(string)`
- `dataUrl(url)`
- `dataKey(key)`
- `initialData(record)`
- `actionsPosition('top' | 'bottom' | 'both')`
- `submit({ url, method, preserveScroll? })`
- `actionLabels(...)`
- `hideCancel(boolean)`
- `hideSubmit(boolean)`
- `onCancel('back' | 'emit')`
- `inDialog(boolean)`
- `showBack(boolean)`
- `cardTitle(string)`
- `cardSubtitle(string)`
- `isCard(boolean)`
- `permission(key)` — formu salt-okunur moda alan yetki anahtarı (yetki yoksa tüm alanlar disabled + submit gizli)
- `addFields(...fields)`

## Ortak Field Metotları

Çoğu alan şu metotları destekler:

- `key`
- `label`
- `required`
- `optional`
- `class`
- `hint`
- `visible(fn)`
- `disabled(fn)`
- `hidden(boolean)`
- `default(value)`
- `props({...})`
- `colSpan(number)` — bu alanın form grid'inde kaç sütun kaplayacağı (1..cols). Belirtilmezse 1 hücre. Aktif `cols` değerini aşan değerler otomatik clamp edilir; section içindeyse clamp section'ın kendi `cols` değerini kullanır.

`hidden(true)`, alanı gönderilen payload içinde tutarken görünür bir kontrol yerine gizli input olarak render eder.

```ts
FB.inputText().key('user_id').default(currentUserId).hidden();
```

## Kullanılabilir Field Builder'lar

- `FB.inputText()`
- `FB.inputNumber()`
- `FB.inputOtp()`
- `FB.inputMask()`
- `FB.datePicker()`
- `FB.select()`
- `FB.multiselect()`
- `FB.radio()`
- `FB.selectButton()`
- `FB.checkbox()`
- `FB.checkboxGroup()`
- `FB.password()`
- `FB.textarea()`
- `FB.editor()`
- `FB.translatableText()`
- `FB.translatableTextarea()`
- `FB.translatableEditor()`
- `FB.toggleButton()`
- `FB.toggleSwitch()`
- `FB.fileUpload()`
- `FB.colorSelector()`
- `FB.title()`
- `FB.section()`
- `FB.slot()`

## İkonlar (Paket-Bağımsız)

Kit, paket-spesifik bir ikon kütüphanesine bağlı değildir. Tüm ikon API'leri (`icon`, `labelIcon`, `iconPosition`, `labelIconPosition`, title/section icon) bir `string` "ikon descriptor'ı" alır.

Descriptor üç formattan birini otomatik algılar:

| Desen | Anlam | Örnek |
| --- | --- | --- |
| `<svg…` ile başlar | Ham SVG markup — `v-html` ile render | `'<svg viewBox="0 0 24 24">…</svg>'` |
| `https?:` veya `data:` ile başlar | URL veya data URI — `<img src>` ile render | `'https://cdn.example.com/icon.svg'`, `'data:image/svg+xml;base64,…'` |
| Diğer | CSS class — `<i :class>` ile render | `'pi pi-search'`, `'fa fa-user'`, `'mdi mdi-account'` |

Bu yaklaşım PrimeIcons, FontAwesome, Material Design Icons, Lucide, Iconify ve diğer class-tabanlı ikon setlerini **aynı API üzerinden** destekler.

**Güvenlik notu:** İkon descriptor'ları developer-controlled builder config'ten gelmelidir — kullanıcı girdisinden değil. `<svg…` yolu `v-html` ile render eder; bu bir XSS vektörüdür. API'den gelen bir string'i sanitize etmeden doğrudan field ikon config'ine sokmayın.

## Label İkonları

`.labelIcon(descriptor)`, bir field'ın label'ı yanına ikon ekler. Tüm field tiplerinde desteklenir.

- `.labelIconPosition('left' | 'right')` — label metnine göre konum (varsayılan `'left'`).

```ts
FB.inputText()
    .key('email')
    .label('E-posta')
    .labelIcon('pi pi-envelope')
    .labelIconPosition('left')
```

Tüm layout modlarında çalışır: `vertical` (üst label), `vertical` (inline label) ve `horizontal`.

## Input İkonları

`.icon(descriptor)`, PrimeVue `IconField` + `InputIcon` kullanarak input elemanının içine ikon yerleştirir.

- `.iconPosition('left' | 'right')` — konum (varsayılan `'left'`).

Desteklenen field tipleri: `input-text`, `input-number`, `input-mask`, `password` (yalnızca custom path — aşağıdaki nota bakın).

```ts
FB.inputText().key('search').label('Ara').icon('pi pi-search').iconPosition('left')
FB.inputNumber().key('price').label('Fiyat').icon('fa fa-dollar').iconPosition('right')
FB.inputMask().key('phone').label('Telefon').mask('(999) 999-9999').icon('mdi mdi-phone')
```

**Uyarılar:**

- `.groupPrefix()` / `.groupSuffix()` önceliklidir — InputGroup wrapper varsa input ikonu devre dışı kalır.
- `FB.password().feedback()`, PrimeVue `<Password>` üzerinden render eder (güç göstergesi yolu). Bu yolda `.icon()` etkisizdir. `feedback` kapalıyken (varsayılan custom path) `.icon()` normal çalışır.
- `.icon()`, `select`, `multiselect`, `textarea`, `editor`, `file-upload`, `color-selector` ve `date-picker` (kendi `showIcon` mekanizması vardır) tiplerinde **desteklenmez**. Bu tipler için `.labelIcon()` kullanın ya da `componentProps` ile özelleştirin.

## InputMask Alan API'si

`FB.inputMask()`, telefon, kimlik numarası ve formatlı tarih gibi alanlarda kullanışlıdır.

- `mask(string)`
- `placeholder(string | boolean)`
- `slotChar(string)`
- `autoClear(boolean)`
- `unmask(boolean)`

```ts
FB.inputMask().key('phone').mask('(999) 999-9999').placeholder('sk-common.placeholder.phone').slotChar('_').unmask();
```

`unmask(true)` aktif olduğunda modelde tutulan değer, maske karakterleri olmadan döner.

## DatePicker Alan API'si

`FB.datePicker()`, tarih, tarih-saat, aralık, çoklu tarih, ay ve yıl girişleri için PrimeVue `DatePicker` render eder.

- `placeholder(string | boolean)`
- `dateFormat(string)` — PrimeVue tarih formatı, varsayılan `'dd/mm/yy'`.
- `selectionMode('single' | 'range' | 'multiple')`
- `showTime(boolean)`
- `hourFormat('12' | '24')`
- `showIcon(boolean)`
- `iconDisplay('input' | 'button')`
- `minDate(Date)`
- `maxDate(Date)`
- `showButtonBar(boolean)`
- `numberOfMonths(number)`
- `view('date' | 'month' | 'year')`
- `inline(boolean)`

```ts
FB.datePicker()
    .key('published_at')
    .label('Yayın tarihi')
    .showIcon()
    .showTime()
    .hourFormat('24')
    .dateFormat('dd/mm/yy');
```

## Password Alan API'si

`FB.password()`, opsiyonel güç göstergesi, crypto-safe üretici ve tutarlı göz toggle'ı ile gelen bir parola input'u üretir.

- `toggleMask(boolean)` — göster/gizle göz toggle'ı (varsayılan `true`).
- `feedback(boolean)` — PrimeVue `<Password>` güç göstergesine opt-in. Çağrılmadığında alan, daha hafif `<InputText>` + custom göz toggle yoluna düşer; böylece `InputGroup` içinde birebir aynı görünür. Varsayılan `false`.
- `generator(options?)` — input'un yanına crypto-safe generate butonu ekleyen opt-in metodu. Tüm seçenekler opsiyonel:

    ```ts
    FB.password().key('password').generator();
    // → 16 karakter, mixed case + harf + rakam + sembol

    FB.password().key('password').generator({
        length: 20,
        mixedCase: true,
        letters: true,
        numbers: true,
        symbols: true,
    });
    ```

    Varsayılanlar bilinçli olarak proje-wide `Password::defaults()` kuralından daha sıkı — üretilen her değer ilk submit'te backend validation'ı geçer. Üretilen parola doğrudan input'a yazılır, toast üzerinden bir kez gösterilir (`password_generated` / `password_generated_detail`) ve alandan kopyalanabilir.

```ts
// Basit göz toggle'lı parola alanı
FB.password().key('password');

// Üretici butonlu parola alanı
FB.password().key('password').generator();

// Güç göstergeli varyant (PrimeVue <Password>'a düşer)
FB.password().key('password').feedback();

// Özel uzunluk ve sembol seti ile üretici
FB.password().key('password').generator({ length: 24 });
```

## Editor Alan API'si

`FB.editor()`, Tiptap v3 tabanlı bir WYSIWYG editör'ü FormBuilder alanı olarak render eder. İçerik sanitize edilmiş HTML olarak saklanır — `App\Support\HtmlSanitizer` hem yazma hem okuma yolunda allowlist dışındaki tag, attribute ve URL scheme'lerini süzer.

- `toolbar('minimal' | 'standard' | 'full')` — toolbar düzeni. `minimal` bold / italic / link; `standard` başlıkları, listeleri, hizalamayı ve rengi ekler; `full` tablo, task list, görsel gömme ve horizontal rule'u aktive eder. Varsayılan `'standard'`.
- `placeholder(string)` — editor boşken gösterilen çeviri anahtarı.
- `minHeight(string)` — editor gövdesi için CSS `min-height` (varsayılan `'10rem'`).
- `imageUpload({ context, contextId?, folderId?, folderName?, acceptedMimes? })` — File Manager üzerinden inline görsel upload'ını konfigüre eder. `context` zorunludur ve File Manager context registry içinde kayıtlı olmalıdır. `folderName`, bu editör üzerinden yüklenen her görseli ilgili context'te tek bir root-level klasör altında gruplar (örn. her welcome-message görseli "Welcome Message" altına gider). Server-side `folder_name` validator'ı ile aynı regex: yalnızca harf, rakam, boşluk, tire, altçizgi.
- `links(boolean)` — link toolbar butonunu ve paste auto-linking davranışını açar. Varsayılan `false`.
- `treatEmptyAsBlank(boolean)` — editör boşken `<p></p>` yerine boş string üretir. Varsayılan `true`.

```ts
FB.editor()
    .key('welcome_message')
    .toolbar('standard')
    .placeholder('sk-setting.general.welcome_message_placeholder')
    .imageUpload({ context: 'global', folderName: 'Welcome Message' });
```

### Sanitize edilmiş içeriği render etme

Editor çıktısını admin UI'ın başka bir yerinde render ederken `sk-prose` container'ına sarın; böylece typography extension'ları tutarlı çözülür:

```vue
<div class="sk-prose" v-html="welcomeMessage" />
```

Server tarafında, frontend'e paylaşmadan önce her okumayı `HtmlSanitizer::clean()` üzerinden geçirin (defense-in-depth — yazma yolu da sanitize'liyor ama drift etmiş bir DB satırı veya sanitize öncesi eski bir kayıt tarayıcıya asla ulaşmamalı).

### URL scheme allowlist'i

`HtmlSanitizer` relative URL'lerle birlikte `http://`, `https://`, `mailto:`, `tel:` scheme'lerine izin verir. Diğer her şey (`blob:`, `data:`, `file:`, `ftp:`, `javascript:`, `vbscript:`) reddedilir. Editor içeriğini programatik doldururken bunu hatırlayın — kayıt öncesinde kaçak scheme temizlenir.

## Çevrilebilir Alan API'si

Bir metin alanının aktif her dil için ayrı değer saklaması gerekiyorsa translatable builder'ları kullanın:

- `FB.translatableText()` — her locale için bir `InputText`.
- `FB.translatableTextarea()` — her locale için bir `Textarea`.
- `FB.translatableEditor()` — her locale için bir zengin editör.

Ortak metotlar:

- `onlyLocales(['tr', 'en'])` — yalnız bu locale kodlarını render eder.
- `exceptLocales(['en'])` — bu locale kodlarını gizler.
- Çok dilli alanlar her zaman tab'lı locale panelleri olarak render edilir (kit'in tek çok dilli giriş tasarımı).
- `localeLabelStyle('badge' | 'name' | 'flag')` — locale label görünümü.

```ts
FB.form().addFields(
    FB.translatableText().key('title').label('Title').required(),
    FB.translatableTextarea().key('description').label('Description').rows(4),
    FB.translatableEditor().key('content').label('Content').minHeight('220px'),
);
```

Backend eşleşmesi:

- Her attribute'u JSON kolonda saklayın.
- Modele Spatie `HasTranslations` ekleyin ve attribute'ları `$translatable` içine yazın.
- FormRequest'lerde `Lvntr\StarterKit\Support\HasTranslatableRules` kullanın (bir trait — bu trait'in `App\Support` geriye-dönük alias'ı yoktur, vendor namespace'inden import edin).
- Datatable arama/sıralama ve resource çıktısı için `Lvntr\StarterKit\Support\TranslatableQueryHelpers` kullanın.

Tam backend ve frontend rehberi için [Çevrilebilir Alanlar](./translatable-fields.tr.md) dokümanına bakın.

## ColorSelector Alan API'si

`FB.colorSelector()`, Tailwind renk paletinden seçim yapılan ve isteğe bağlı tone seçici içeren bir alan üretir.

- `colors(string[])` — kullanılabilir renk adları. Varsayılan: 22 Tailwind palet ailesinin tamamı — 17 kromatik aile (`red`'den `rose`'a) ve 5 nötr aile (`slate`, `gray`, `zinc`, `neutral`, `stone`).
- `tones(number[])` — gösterilecek tone basamakları. Varsayılan: `[50, 100, …, 950]`.
- `format('hex' | 'name' | 'name-tone')` — çıktı formatı. Varsayılan: `'name'`.
- `defaultTone(number)` — tone gerektiren formatlarda ilk seçim. Varsayılan: `500`.

Çıktı formatı modele kaydedilecek değeri belirler:

| `format`       | Kaydedilen değer |
| -------------- | ---------------- |
| `'name'`       | `"blue"`         |
| `'name-tone'`  | `"blue-500"`     |
| `'hex'`        | `"#3b82f6"`      |

Tone seçici, `'name-tone'` ve `'hex'` formatlarında dropdown'un altında görünür. `'name'` modunda tone dikkate alınmaz ve seçici gizlenir.

```ts
// Varsayılan — renk adını kaydeder
FB.colorSelector().key('brand_color');

// Renk adı + tone — "blue-500" kaydeder
FB.colorSelector().key('brand_color').format('name-tone').defaultTone(500);

// Hex değer — "#2563eb" kaydeder
FB.colorSelector().key('brand_color').format('hex').defaultTone(600);

// Paleti daralt
FB.colorSelector().key('accent').colors(['red', 'blue', 'green']).tones([400, 500, 600]);
```

Modele başlangıçta bir hex string geldiğinde, component ters arama yaparak eşleşen renk + tone seçimini geri yükler.

## Title İkonları

`FB.title()`, başlığın yanına ikon render etmek için `.icon()` ve `.iconPosition()` metotlarını destekler.

- `.iconPosition('left' | 'right')` — konum (varsayılan `'left'`).

```ts
FB.title('Genel Bilgiler').icon('pi pi-info-circle').iconPosition('left')
```

## Section / Card Gruplama

`FB.section()`, ilgili alanları görsel olarak belirgin bir kart bloğunda gruplar. Section'lar `FB.form().addFields(...)` içinde üst seviye bir field tipi olarak render edilir.

```ts
import { FB } from '@lvntr/components/FormBuilder/core';

const config = FB.form()
    .layout('vertical')
    .cols(2)
    .isCard(false)  // form-level card kapatılır; section'lar kendi card'larında render edilir
    .addFields(
        FB.section('Kişisel Bilgiler')
            .icon('pi pi-user')
            .cols(2)
            .addFields(
                FB.inputText().key('first_name').label('Ad'),
                FB.inputText().key('last_name').label('Soyad'),
                FB.inputText().key('email').label('E-posta').icon('pi pi-envelope'),
                FB.password().key('password').label('Parola').icon('pi pi-lock'),
            ),
        FB.section('Adres')
            .icon('pi pi-map-marker')
            .subtitle('İletişim adresi bilgileri')
            .cols(2)
            .addFields(
                FB.inputText().key('city').label('Şehir'),
                FB.inputText().key('postal_code').label('Posta Kodu'),
                FB.textarea().key('address').label('Açık Adres').colSpan(2), // tam satır kapla — .class('col-span-2') yerine tercih edilir
            ),
        FB.section('Tercihler')
            .icon('pi pi-cog')
            .isCard(false)  // transparent section — card arka plan, kenarlık ve gölge yok
            .cols(1)
            .addFields(
                FB.toggleSwitch().key('newsletter').label('Bülten aboneliği'),
                FB.toggleSwitch().key('notifications').label('Bildirimler'),
            ),
    )
    .build();
```

**Alan bazlı `.colSpan()` örneği** — 12 sütunlu formda tam genişlik ve yan yana alanların karıştırılması:

```ts
FB.form()
    .cols(12)
    .addFields(
        FB.inputText().key('title').label('Başlık').colSpan(12),  // tam satır
        FB.inputText().key('first_name').label('Ad').colSpan(6),
        FB.inputText().key('last_name').label('Soyad').colSpan(6),
        FB.textarea().key('notes').label('Notlar').colSpan(12),
    )
    .build();
```

**Section Builder API:**

- `FB.section(title?)` — factory metodu. `title` bir çeviri anahtarıdır (opsiyonel).
- `.title(key)` — section başlığı olarak kullanılan çeviri anahtarını set eder veya override eder.
- `.subtitle(key)` — başlığın altında gösterilen ikincil metin.
- `.icon(descriptor)` — başlığın yanında gösterilen ikon.
- `.iconPosition('left' | 'right')` — ikon konumu (varsayılan `'left'`).
- `.cols(number)` — section içindeki alanlar için grid sütun sayısı (1–12). Belirtilmezse parent form'un `cols` değerini devralır.
- `.isCard(boolean)` — `false` olduğunda section card kabuğu olmadan render edilir (arka plan, gölge veya kenarlık yok). Varsayılan `true` (card görünür).
- `.addFields(...fields)` — iç içe field tanımları. Yalnızca tek seviye iç içe geçme desteklenir.

**Notlar:**

- Section `key` değerleri gönderilen payload'da yer tutmaz — form verisi flat'tir. Yukarıdaki örnek şu veriyi üretir: `{ first_name, last_name, email, password, city, postal_code, address, newsletter, notifications }`.
- İç içe section (section içinde section) **desteklenmez** — tek seviye.
- `FB.title()` ve `FB.section()` birlikte kullanılabilir: üst seviye başlıklar için section dışında `title` field kullanın, içerikler section'lar altında gruplandırılsın.

## Card Başlık Sağ Slot

Hem form-level card (`cardTitle` ayarlandığında) hem her `FB.section()` card'ı, **başlığın sağına** action button, badge veya durum göstergesi yerleştirmek için bir slot açar.

- **Form card** — slot adı: `title-end`.
- **Section card** — slot adı: `section-${key}-title-end`. Section'da `.key('your-key')` çağırın ki slot adı kararlı olsun; aksi halde otomatik üretilen `__section_N` key'i kullanılır.

```vue
<SkForm :config="formConfig">
  <template #title-end>
    <Button icon="pi pi-refresh" text rounded @click="refresh" />
  </template>

  <template #section-address-title-end="{ values }">
    <Tag v-if="values.is_primary" severity="success" :value="$t('forms.primary')" />
  </template>
</SkForm>
```

```ts
FB.section('Adres').key('address').addFields(/* ... */)
```

Section slot scope'una `{ values }` geçilir — mevcut form değerlerinin reaktif snapshot'ı, koşullu render için kullanışlıdır.

Görsel olarak caption bloğu (title + subtitle) form içeriğinden alt çizgi ile ayrılır (`--p-surface-200` light / `--p-surface-700` dark tema değişkenleri) — böylece başlık, slot içeriği ve alt başlık form alanlarının üzerinde bütünleşik tek bir başlık bloğu olarak okunur.

## Select Benzeri Alanlarda Veri Kaynakları

Select alanları seçenekleri şu kaynaklardan alabilir:

- `options([...])` ile statik dizi
- `definitionOptions('userStatus')` ile giriş gerektiren `/definitions` kayıtları
- `optionsUrl(...)` ile uzaktan dinamik veri

`enumOptions(...)` geriye dönük uyumluluk için hâlâ duran, ancak yeni kodda tercih edilmemesi gereken deprecated bir alias'tır.

## Reaktif Alan Bağımlılıkları

`visible(fn)` ve `disabled(fn)` metotları tüm güncel form değerlerini parametre olarak alır. SkForm her değişiklikte bunları yeniden değerlendirir, böylece alanlar birbirine bağımlı olabilir.

### Başka bir alanın değerine göre disable etme

```ts
FB.form().addFields(
    FB.select()
        .key('notification_channel')
        .options([
            { label: 'Email', value: 'email' },
            { label: 'SMS', value: 'sms' },
            { label: 'Yok', value: 'none' },
        ]),
    FB.inputText()
        .key('notification_address')
        .disabled((values) => values.notification_channel === 'none'),
);
```

`notification_channel` olarak `none` seçildiğinde `notification_address` alanı disable olur.

### Başka bir alanın değerine göre gösterme/gizleme

```ts
FB.toggleSwitch().key('use_custom_domain'),
FB.inputText()
    .key('custom_domain')
    .visible((values) => values.use_custom_domain === true),
```

`custom_domain` alanı yalnızca toggle açıkken görünür.

## API'den Dinamik Seçenekler (Bağımlı Select'ler)

`optionsUrl` sabit bir string ya da güncel form değerlerini alıp URL (veya `null`) dönen bir **fonksiyon** kabul eder. SkForm dönen URL'yi izler — değiştiğinde otomatik olarak yeni seçenekleri çeker.

### Sabit URL'den seçenekleri yükleme

```ts
FB.select().key('role').optionsUrl('/api/roles/options');
```

### Bağımlı select — başka bir alana göre API'den veri çekme

```ts
FB.form().addFields(
    FB.select()
        .key('country')
        .options([
            { label: 'Türkiye', value: 'TR' },
            { label: 'Almanya', value: 'DE' },
        ]),
    FB.select()
        .key('city')
        .optionsUrl((values) => (values.country ? `/api/cities?country=${values.country}` : null)),
);
```

Nasıl çalışır:

1. Kullanıcı bir `country` seçer
2. `optionsUrl` fonksiyonu yeni değerlerle çalışır, `/api/cities?country=TR` döner
3. SkForm URL'nin değiştiğini algılar, otomatik olarak yeni seçenekleri çeker
4. `city` dropdown'ı gelen verilerle doldurulur
5. `null` döndürmek "çekme" anlamına gelir — ülke seçilene kadar select boş kalır

### disabled + bağımlı optionsUrl birlikte kullanma

```ts
FB.select()
    .key('department')
    .optionsUrl('/api/departments/options'),
FB.select()
    .key('team')
    .disabled((values) => !values.department)
    .optionsUrl((values) =>
        values.department
            ? `/api/teams/options?department=${values.department}`
            : null
    ),
```

`team` select'i departman seçilene kadar disable kalır. Seçildikten sonra API'den departmana göre filtrelenmiş takımlar çekilir.

## Yetki Kontrolü (Form-Level)

Bir formu yalnızca belirli bir yetkisi olan kullanıcıların düzenlemesine izin vermek için `.permission()` metodu kullanılır:

```ts
FB.form()
    .resource({ store: ..., update: ..., data: ..., key: 'user', id: userId })
    .permission('users.update')
    .addFields(/* ... */)
    .build();
```

Yetki `auth.permissions` Inertia shared prop'undan `useCan()` composable'ı ile çözülür. Kullanıcıda yetki yoksa:

- Tüm alanlar otomatik olarak `disabled` hale gelir (mevcut `field.disabled(values => ...)` callback'leri ile birlikte)
- Submit butonu hem üst hem de alt action alanlarında gizlenir
- `handleSubmit` ek bir güvenlik katmanı olarak herhangi bir submit'i iptal eder
- Cancel/back butonu ve özel slot action'lar görünmeye devam eder

## İyi Pratik

Alan tanımlarını, formun ait olduğu sayfa veya sekmeye yakın tutun. Backend tarafında Domain Action ve Form Request kullanın ki form katmanı iş mantığına dönüşmesin.

## En İyi Kullanım Alanları

- ayar sekmeleri
- create ve edit resource formları
- profil formları
- tekrar eden alan desenlerine sahip admin araçları

## Dahili Davranışlar

`SkForm` şunları hazır olarak yönetir:

- `dataUrl` ile ilk veriyi çekme
- definition verilerini önce yükleme
- bağımlı alan değişince dinamik select seçeneklerini yenileme
- gizli alanları doğal `<input type="hidden">` elemanları olarak render etme
- file upload alanları varsa `forceFormData` ile gönderme
- dialog için uygun cancel davranışı
- dahili veya harici modda birleşik hata gösterimi
- `permission` set edilirse formu salt-okunur moda alma
