# Definitions

Definitions, form, filtre ve tag alanlarında kullanılan label/value çiftleri için ortak bir lookup sistemidir.

## Saklama ve Yönetim

Definitions kayıtları veritabanında tutulur. Admin arayüzünden CRUD işlemi yapılamaz; yönetim seeder ve migration aracılığıyla gerçekleştirilir.

- Migration: `database/migrations/2026_03_12_001950_create_definitions_table.php`
- Seeder: `database/seeders/_02_DefinitionSeeder.php`

## Veritabanı Kolonları

`definitions` tablosu şu kolonlara sahiptir:

| Kolon | Tür | Notlar |
|---|---|---|
| `key` | string | indeksli; ilişkili definition'ları gruplar |
| `value` | string | saklanan değer |
| `label` | string | okunabilir görüntü etiketi |
| `explanation` | text | nullable; ek açıklama |
| `severity` | string | nullable; örn. `info`, `warning`, `danger` |
| `icon` | string | nullable; ikon tanımlayıcısı |
| `is_active` | boolean | varsayılan `true` |
| `order` | integer | varsayılan `0`; sıralamayı belirler |
| `visibility` | boolean | varsayılan `true` |
| `lang` | string | varsayılan `en`; i18n desteği sağlar |

`(key, value, lang)` üçlüsü üzerinde tekil kısıt uygulanır.

## Erişim Noktaları

- web service route: `/definitions`
- API route: `/api/v1/definitions`
- frontend composable: `useDefinition()`

## Frontend Faydaları

Definitions sayesinde:

- select seçenekleri kolay üretilir
- status tag'leri tutarlı render edilir
- aynı anlam farklı sayfa ve modüllerde ortak şekilde kullanılır

## Yaygın Metotlar

`useDefinition()` içinden:

- `load(keys)`
- `loadAll()`
- `list(key)`
- `options(key)`
- `find(key, value)`
- `clearCache()`

