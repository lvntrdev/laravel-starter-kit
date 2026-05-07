# Lvntr Starter Kit

### Admin odaklı Laravel starter kit.

![Tests](https://img.shields.io/badge/tests-passing-22c55e?style=flat-square)
![License](https://img.shields.io/badge/license-PolyForm--Noncommercial%201.0.0-f59e0b?style=flat-square)
![Packagist Sürüm](https://img.shields.io/packagist/v/lvntr/laravel-starter-kit?style=flat-square&label=packagist)
![Downloads](https://img.shields.io/packagist/dt/lvntr/laravel-starter-kit?style=flat-square&label=downloads)

> ## ⚠️ UYARI
>
> Bu depo aktif geliştirme aşamasındadır ve sık sık değişikliklere tabidir. Projenin stabilitesi henüz garanti altına alınmamıştır. Kullanmadan önce lütfen aşağıdaki noktaları göz önünde bulundurun:
>
> 1. **Kod Değişiklikleri:** Dizin yapısı veya çekirdek sınıflar, önceden haber verilmeksizin radikal değişikliklere uğrayabilir.
> 2. **Güncelleme Süreci:** Güncellemeler her zaman otomatik bir geçiş (migration) yolu sunmayabilir. Güncelleme komutlarını çalıştırmanın yanı sıra, `README` veya `CHANGELOG` dosyalarını kontrol ederek elle müdahale yapmanız gerekebilir.
> 3. **Risk:** Yapılan önemli değişiklikler, mevcut projenizde veri kaybına veya kırıcı (breaking) hatalara yol açabilir.

## Tanıtım

Lvntr Starter Kit; **Laravel 13**, **Inertia.js v3**, **Vue 3**, **PrimeVue 4** ve **Tailwind CSS 4** üzerine kurulmuş, tam donanımlı bir Laravel admin panel paketidir.

Resmi Laravel starter kit'leri yalnızca kimlik doğrulama iskeletiyle gelirken, bu paket daha ilk kurulumda production-ready bir admin paneli sunar: kullanıcılar, roller, yetkiler, aktivite kayıtları, ayarlar, dosya yöneticisi, 2FA ve genişletebileceğin DDD tarzı bir domain katmanı.

Her projede aynı admin ekranlarını sıfırdan yazmak istemeyip doğrudan iş mantığına odaklanmak isteyen ekipler için tasarlandı.

> **Web Sitesi & Dökümantasyon:** [starter-kit.lvntr.dev](https://starter-kit.lvntr.dev/)
> Kurulum rehberi, bileşen referansları, mimari notlar ve örnekler.

## Ekran Görüntüleri

![Koyu & Açık temalar](https://starter-kit.lvntr.dev/shots/dark-light.png)

![Giriş ekranı](https://starter-kit.lvntr.dev/shots/auth-login.png)

![Kullanıcı yönetimi](https://starter-kit.lvntr.dev/shots/admin-users.png)

![Roller ve yetkiler](https://starter-kit.lvntr.dev/shots/admin-permissions.png)

![Dosya yöneticisi](https://starter-kit.lvntr.dev/shots/admin-file-manager.png)

## İçinde Neler Var?

- **Kimlik Doğrulama**
    - Giriş / Kayıt / Şifre Sıfırlama
    - E-posta Doğrulama
    - İki Faktörlü Doğrulama (Fortify)
    - Laravel Passport ile OAuth2 API
- **Kullanıcı ve Erişim Yönetimi**
    - Avatar yükleme ve soft delete destekli kullanıcı CRUD
    - Roller ve dinamik kaynak bazlı yetkiler (Spatie)
    - Oturum yönetimi
- **Admin Modülleri**
    - Dashboard
    - Aktivite Kayıtları (gözatılabilir, filtrelenebilir)
    - Ayarlar paneli (Genel / Kimlik Doğrulama / Mail / Depolama / Dosya Yöneticisi)
    - İmzalı paylaşım linki destekli, pluggable context'lere sahip Dosya Yöneticisi
    - API Client ve Personal Access Token yönetimi
    - Sistem Sağlık paneli
    - API Route tarayıcısı
    - Definitions (form ve tablolarda kullanılan DB tabanlı enum'lar)
- **Geliştirici Araçları**
    - DDD tarzı domain katmanı (Action / DTO / Query / Event / Listener)
    - FormBuilder, DatatableBuilder, TabBuilder fluent API'ları
    - `make:sk-domain` ile opt-in flag destekli domain iskeleti üretimi
    - Sayfa aşımı seçim desteği ile datatable bulk action API
    - `sk:update` ile güvenli güncelleme (hash tabanlı, kullanıcı değişikliklerini korur)
    - `sk:doctor` ile sistem sağlık kontrolü
    - Açık & koyu tema

## Nasıl Kullanılır?

Temiz bir Laravel kurulumundan başla:

```bash
composer create-project laravel/laravel my-app
cd my-app
composer require lvntr/laravel-starter-kit:^13.0
php artisan sk:install
```

Hepsi bu kadar. Kurulum sihirbazı migration, seeder, Passport anahtarları, varsayılan admin kullanıcısı ve frontend build işlemlerini otomatik yapar.

Detaylı adım adım rehber: [starter-kit.lvntr.dev/docs/install](https://starter-kit.lvntr.dev/docs/install)

## Gereksinimler

- PHP 8.4+
- Laravel 13
- Node.js 18+
- MySQL veya MariaDB

## Dökümantasyon

Kurulum, güncelleme akışı, domain scaffolding, FormBuilder / DatatableBuilder / TabBuilder API'ları, composable'lar, dosya yöneticisi, roller ve yetkiler, OAuth2 API, aktivite kayıtları, ayarlar — her şey resmi sitede:

**[starter-kit.lvntr.dev](https://starter-kit.lvntr.dev/)**

## Komutlar

| Komut | Açıklama |
|---|---|
| `php artisan sk:install` | Tam kurulum: migration, seeder, Passport anahtarları, admin kullanıcısı, frontend build |
| `php artisan sk:update` | Güncel stub'ları çek (hash tabanlı, kendi değişikliklerini korur) |
| `php artisan sk:publish [--tag=...]` | Belirli asset gruplarını yayınla (component, config, stub, migration) |
| `php artisan make:sk-domain Foo` | Yeni bir DDD domain iskeleti oluştur |
| `php artisan sk:doctor` | Sistem sağlık kontrolü çalıştır |

### `sk:doctor` — Sistem Sağlık Kontrolü

12 kontrol noktasını çalıştırır: PHP extension'ları, veritabanı bağlantısı, Redis, Passport anahtarları, storage symlink, yazılabilir dizinler, queue driver, schedule, mail driver, npm build artifact'ları, config cache, FileManager disk bağlantısı.

```bash
# İnsan okunabilir çıktı
php artisan sk:doctor

# JSON çıktı (CI/CD uyumlu)
php artisan sk:doctor --json

# Yalnızca belirli kontrolleri çalıştır
php artisan sk:doctor --only=database,redis,passport-keys
```

Exit kodları: `0` OK, `1` WARN (kritik değil), `2` FAIL (müdahale gerektirir).

`/admin/system-health` admin sayfası bu kontrolleri talep üzerine çalıştırır. Erişim izni: `system.health.view`.

### `make:sk-domain` — Domain Generator

```bash
# Minimal iskelet (önceki sürümlerle aynı davranış)
php artisan make:sk-domain Post

# Opt-in extra'ları tek tek ekle
php artisan make:sk-domain Post --with-policy --with-test --with-factory

# Opt-in extra'ları toplu belirt
php artisan make:sk-domain Post --with=policy,factory,seeder,test

# İlişki scaffold'ı ile
php artisan make:sk-domain Comment --with-relations \
  --relations="belongsTo:Post,morphTo:commentable"
```

Flag referansı:

| Flag | Üretilen dosya |
|---|---|
| `--with-policy` | `PostPolicy` — `AuthServiceProvider`'a kayıtlı |
| `--with-factory` | `PostFactory` |
| `--with-seeder` | `PostSeeder` |
| `--with-test` | Pest feature test stub |
| `--with-relations` | Model'de ilişki metodları + migration foreign key'leri |
| `--with=a,b,c` | Birden fazla `--with-*` flag'i için kısayol |
| `--relations="..."` | İlişki tanımları (`--with-relations` parametresini de etkinleştirir) |

## Dosya Yöneticisi

### İmzalı Paylaşım Linkleri

Private dosyalar için zaman sınırlı, genel erişimli URL'ler oluşturur.

```bash
# config/file-manager.php anahtarları
file-manager.share.enabled           # true/false
file-manager.share.default_ttl_hours # varsayılan: 24
file-manager.share.max_ttl_hours     # varsayılan: 720
file-manager.share.allow_revoke      # true/false
```

**Paylaşım linki oluştur:**
```
POST /file-manager/share
{ "media_id": 42, "ttl_hours": 48 }

→ { "url": "https://...?expires=...&signature=...", "expires_at": "..." }
```

**Paylaşım linkini iptal et:**
```
POST /file-manager/share/revoke
{ "media_id": 42, "signed_token_hash": "..." }
```

**Paylaşılan dosyaya eriş:**
```
GET /file-manager/share/{media}?expires=...&signature=...
```

İzinler: `share-media`, `revoke-share-media`.

## Datatable Bulk Action

`SkDatatable`, `BulkAction` interface ve `BulkActionDispatcher` aracılığıyla sayfa aşımı seçimi ve toplu işlemleri destekler.

**Request payload:**
```json
{
  "action": "bulk-delete-users",
  "ids": [1, 2, 3],
  "select_all_filtered": false,
  "filter_snapshot": { "search": "...", "role": "editor" }
}
```

**Response:**
```json
{ "processed": 3, "skipped": 0, "failed": 0, "message": "3 kullanıcı silindi." }
```

`select_all_filtered: true` olduğunda action, filtre snapshot'ı alır ve etkilenen kayıtları server tarafında çözer; client'ın ID listesi göndermesi gerekmez.

`php artisan sk:publish --tag=starter-kit-stubs` komutu ile stub örneklerine ulaşabilirsin:
- `BulkDeleteUserAction` — rank-aware; kendi hesabını silmeyi engeller
- `BulkDeleteRoleAction` — sistem tarafından korunan rolleri atlar

## API Client ve Token Yönetimi

Admin route'ları: `/admin/api-clients`, `/admin/api-tokens`.

Passport `authorization_code` ve `client_credentials` grant'leri ile Personal Access Token yönetimini destekler.

İzinler:

| İzin | Kapsam |
|---|---|
| `api-clients.create` | OAuth client oluştur |
| `api-clients.read` | Client listele / görüntüle |
| `api-clients.update` | Client adı / redirect URI düzenle |
| `api-clients.delete` | Client sil |
| `api-tokens.create` | Personal Access Token oluştur |
| `api-tokens.read` | Token listele |
| `api-tokens.delete` | Token iptal et |

**Güvenlik notları:**

- Client secret ve token plaintext yalnızca oluşturma anında `OneTimeSecretModal` aracılığıyla bir kez gösterilir (kopyalanmadan kapatılamaz). Sonraki görüntülemelerde yalnızca maskeli değer gösterilir.
- `redirect_uris` HTTPS zorunluluğuna tabidir. `localhost` ve `127.0.0.1` için HTTP'ye izin verilir (RFC 8252 §8.3). Kural: `HttpsOrLocalhostUrl`.
- Tüm client'lar `confidential = true` olarak oluşturulur; UI üzerinden public (non-confidential) client oluşturulamaz.
- Personal Access Token her zaman kimliği doğrulanmış admin için oluşturulur; request body'deki `user_id` alanı kabul edilmez.

## Lisans

[PolyForm Noncommercial 1.0.0](./LICENSE)
