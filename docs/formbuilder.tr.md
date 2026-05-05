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
- `cols(number)`
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

`hidden(true)`, alanı gönderilen payload içinde tutarken görünür bir kontrol yerine gizli input olarak render eder.

```ts
FB.inputText().key('user_id').default(currentUserId).hidden();
```

## Kullanılabilir Field Builder'lar

- `FB.inputText()`
- `FB.inputNumber()`
- `FB.inputOtp()`
- `FB.inputMask()`
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
- `FB.slot()`

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
        includeSymbols: true,
        includeNumbers: true,
        includeUppercase: true,
        includeLowercase: true,
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

- `preset('minimal' | 'standard' | 'full')` — toolbar düzeni. `minimal` bold / italic / link; `standard` başlıkları, listeleri, hizalamayı ve rengi ekler; `full` tablo, task list, görsel gömme ve horizontal rule'u aktive eder. Varsayılan `'standard'`.
- `placeholder(string)` — editor boşken gösterilen çeviri anahtarı.
- `minHeight(string)` — editor gövdesi için CSS `min-height` (varsayılan `'180px'`).
- `imageUpload({ folderName?, maxSizeKb?, acceptedMimes? })` — inline görsel upload'ını konfigüre eder. `folderName`, bu editör üzerinden yüklenen her görseli mevcut FileManager context'inde tek bir root-level klasör altında gruplar (örn. her welcome-message görseli "Welcome Message" altına gider). Server-side `folder_name` validator'ı ile aynı regex: yalnızca harf, rakam, boşluk, tire, altçizgi.

```ts
FB.editor()
    .key('welcome_message')
    .preset('standard')
    .placeholder('sk-setting.general.welcome_message_placeholder')
    .imageUpload({ folderName: 'Welcome Message' });
```

### Sanitize edilmiş içeriği render etme

Editor çıktısını admin UI'ın başka bir yerinde render ederken `sk-prose` container'ına sarın; böylece typography extension'ları tutarlı çözülür:

```vue
<div class="sk-prose" v-html="welcomeMessage" />
```

Server tarafında, frontend'e paylaşmadan önce her okumayı `HtmlSanitizer::sanitize()` üzerinden geçirin (defense-in-depth — yazma yolu da sanitize'liyor ama drift etmiş bir DB satırı veya sanitize öncesi eski bir kayıt tarayıcıya asla ulaşmamalı).

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
- `translatableLayout('inline' | 'tabs')` — alt alta alanlar veya tab'lı locale panelleri.
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
- FormRequest'lerde `App\Support\HasTranslatableRules` kullanın.
- Datatable arama/sıralama ve resource çıktısı için `App\Support\TranslatableQueryHelpers` kullanın.

Tam backend ve frontend rehberi için [Çevrilebilir Alanlar](./translatable-fields.tr.md) dokümanına bakın.

## ColorSelector Alan API'si

`FB.colorSelector()`, Tailwind renk paletinden seçim yapılan ve isteğe bağlı tone seçici içeren bir alan üretir.

- `colors(string[])` — kullanılabilir renk adları. Varsayılan: tüm Tailwind paletleri.
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
