# Tema Sistemi

Starter kit, **PrimeVue-token-merkezli** bir çalışma-zamanı tema sistemi sunar. Ayrı CSS bundle'ları veya derleme-zamanı anahtarları yerine aktif tema, bir veritabanı ayarından yüklenip boot sırasında uygulanan bir PrimeVue `definePreset` nesnesidir. Deployment olmadan runtime'da değiştirilebilir.

---

## Mimariye Genel Bakış

```
config/starter-kit.php          ← backend whitelist ('themes' anahtarı)
    ↕ eşlenik olmalı
stubs/resources/js/theme/presets/index.ts   ← frontend preset kaydı
        |
        ├── default.ts          ← varsayılan preset (Material tabanı + özel tokenlar)
        └── corporate.ts        ← ikinci örnek preset (teal primary)

SettingsServiceProvider         ← DB'den appearance.theme okur → config('starter-kit.theme')
HandleInertiaRequests           ← her istekte `theme`'yi Inertia shared-prop olarak paylaşır
app.ts                          ← shared-prop'tan ilk preset'i çözer → PrimeVue init (SSR-güvenli)
useTheme()                      ← hydrate sonrası usePreset() ile runtime swap
```

**İki eksen birbirinden bağımsızdır:**

- **Renk preset'i** (bu rehber) — `useTheme()` ve `appearance.theme` ayarıyla kontrol edilir.
- **Light / dark mod** — `useDarkMode()` ile kontrol edilir; `<html>` üzerindeki `.dark` class'ını değiştirir.

`setTheme()` hiçbir zaman `.dark` class'ına dokunmaz. Dark moddayken `default`'tan `corporate`'e geçmek dark mode'u etkilemez; tersi de geçerlidir.

---

## Preset Kaydı

`stubs/resources/js/theme/presets/index.ts` dosyası, frontend tarafında seçilebilir preset'lerin tek kaynağıdır.

```ts
import defaultPreset from './default';
import corporatePreset from './corporate';

export const presets = {
    default: defaultPreset,
    corporate: corporatePreset,
} as const;

export type SkThemeName = keyof typeof presets;  // 'default' | 'corporate'
export const DEFAULT_THEME: SkThemeName = 'default';

export function resolvePreset(name: string | null | undefined): SkThemePreset { … }
```

`resolvePreset`, prototip zincirine karşı güvenlidir (`Object.hasOwn`) — bilinmeyen veya prototipten miras alınan bir ad her zaman `default` preset'ine düşer.

---

## Yeni Preset Ekleme

Aşağıdaki adımların tamamı birlikte uygulanmalıdır. Bir drift-guard testi (`tests/Feature/Settings/UpdateAppearanceSettingsTest.php`), `config('starter-kit.themes')` ile frontend kaydının aynı anahtarları içerip içermediğini doğrular — yalnızca bir taraf güncellenirse bu test başarısız olur.

**1. Preset dosyasını oluşturun**

```
stubs/resources/js/theme/presets/<isim>.ts
```

PrimeVue `definePreset`'ini default olarak export edin:

```ts
// stubs/resources/js/theme/presets/ocean.ts
import { definePreset } from '@primevue/themes';
import Material from '@primevue/themes/material';

const OceanPreset = definePreset(Material, {
    semantic: {
        primary: {
            500: '#0EA5E9',
            // …
        },
    },
});

export default OceanPreset;
```

**2. Frontend kaydına ekleyin**

```ts
// stubs/resources/js/theme/presets/index.ts
import oceanPreset from './ocean';

export const presets = {
    default: defaultPreset,
    corporate: corporatePreset,
    ocean: oceanPreset,          // buraya ekleyin
} as const;
```

**3. Backend whitelist'ine aynı adı ekleyin**

```php
// config/starter-kit.php
'themes' => [
    'default'   => 'sk-setting::appearance.themes.default',
    'corporate' => 'sk-setting::appearance.themes.corporate',
    'ocean'     => 'sk-setting::appearance.themes.ocean',   // buraya ekleyin
],
```

`UpdateThemeSettingsRequest`, gönderilen preset adını `array_keys(config('starter-kit.themes'))` ile doğrular. Yalnızca frontend kaydında olup bu whitelist'te bulunmayan bir ad kaydedilemez.

**4. Çeviri anahtarlarını ekleyin** (isteğe bağlı ama önerilir)

```php
// stubs/lang/en/sk-setting.php  ve  stubs/lang/tr/sk-setting.php
'appearance' => [
    'themes' => [
        'default'   => 'Varsayılan',
        'corporate' => 'Kurumsal',
        'ocean'     => 'Okyanus',    // buraya ekleyin
    ],
],
```

---

## `useTheme()` Composable

Composables barrel'ından import edin:

```ts
import { useTheme } from '@/composables';
```

Döndürülen değerler:

| Ad | Tip | Açıklama |
|---|---|---|
| `currentTheme` | `Readonly<Ref<SkThemeName>>` | Reaktif aktif preset adı. Inertia shared-prop `theme`'den başlatılır. |
| `themeNames` | `SkThemeName[]` | Kayıttaki tüm seçilebilir preset adları. |
| `setTheme(name)` | `(name: string \| null \| undefined) => SkThemeName` | Preset'i runtime'da uygular; çözümlenen adı döndürür. Bilinmeyen adlar `default`'a düşer. |

```ts
const { currentTheme, themeNames, setTheme } = useTheme();

// Preset'i runtime'da uygula (client-side, DB'ye yazılmaz)
setTheme('corporate');

// Aktif preset adını oku
console.log(currentTheme.value); // 'corporate'

// Tüm mevcut preset'leri listele
console.log(themeNames); // ['default', 'corporate']
```

`setTheme`, yalnızca `typeof document !== 'undefined'` koşulunu sağladığında `usePreset()` çağırır — SSR'da güvenlidir.

---

## Görünüm Ayarları — Admin "Görünüm" Sekmesi

**Konum:** Admin Panel → Ayarlar → Görünüm sekmesi

**Gerekli izin:** `settings.update`

Sekme iki ayrı eylem sunar:

- **Önizle** — Bir tema kartına tıklamak `setTheme()` çağırır ve preset hemen `usePreset()` üzerinden uygulanır. Bu değişiklik **kalıcı değildir**. Kaydetmeden ayrılmak daha önce kaydedilmiş temayı geri yükler (`onBeforeUnmount` üzerinden yönetilir).
- **Kaydet** — Kaydet düğmesi `PUT /settings/appearance` isteğini `{ theme: <isim> }` ile gönderir. Başarı durumunda saklanan tema güncellenir; sonraki sayfa yükleme (veya yeni bir tarayıcı sekmesi) bu temayı kullanır.

Route adı: `settings.update.appearance` (`PUT /settings/appearance`).

---

## Boot Sırasında Aktif Tema Seçimi

1. `SettingsServiceProvider::boot()` veritabanından `appearance.theme`'yi okur ve `config(['starter-kit.theme' => <isim>])` olarak set eder. Ayar satırı yoksa varsayılan `'default'`'a düşer.
2. `HandleInertiaRequests::share()` her Inertia yanıtına `'theme' => config('starter-kit.theme')` ekler.
3. `app.ts`, `props.initialPage.props.theme` üzerinden shared-prop'u alır, `resolvePreset()` ile çözer ve çözümlenen preset'i PrimeVue'nun başlangıç config'ine iletir. Bu işlem hem SSR sunucusunda (CSS'i ilk HTML'e bakar) hem de client'ta (aynı sayfadan hydrate eder) gerçekleşir — içerik yanıp sönmesi (FOUC) yoktur.
4. Hydrate sonrası `useTheme()`, `currentTheme`'yi başlatmak için aynı shared-prop'u okur.

---

## Dark Mode ile İlişki

Tema preset'leri (renk) ve dark mode (light/dark) **ortogonaldir**. Her preset, PrimeVue'nun `colorScheme` tokeni altında hem `light` hem de `dark` renk şeması tanımlar. `useDarkMode()` `<html>` üzerindeki `.dark` class'ını değiştirir; PrimeVue ilgili şemayı otomatik uygular.

```
Aktif durum = preset × mod

"default + dark"    → default'un dark colorScheme tokenları
"corporate + dark"  → corporate'in dark colorScheme tokenları
"corporate + light" → corporate'in light colorScheme tokenları
```

`setTheme()` `.dark`'a dokunmaz. `useDarkMode()` preset'e dokunmaz. İkisi bağımsız çalışır.

---

## Geriye Dönük Uyum

`appearance.theme` ayar satırı bulunmayan mevcut kurulumlar öncekiyle aynı davranışı gösterir: `SettingsServiceProvider` `'default'`'a düşer ve eski tek-preset çıktısıyla pixel-eşdeğer bir görünüm üretir. Yükseltme sonrasında görsel değişiklik olmaz.
