# Lvntr Starter Kit

**Laravel 12**, **Inertia.js v2**, **Vue 3**, **PrimeVue 4** ve **Tailwind CSS 4** ile geliştirilmiş, DDD (Domain-Driven Design) mimarisini takip eden kapsamlı bir Laravel admin paneli paketi. Rol tabanlı yetkilendirme, aktivite loglama, ayar yönetimi ve daha fazlasını içerir.

## Özellikler

- **DDD Mimarisi** — Action, DTO, Query, Event, Listener katmanları
- **Rol ve Yetki Yönetimi** — Spatie Permission ile dinamik kaynak bazlı yetkiler
- **Kullanıcı Yönetimi** — CRUD, avatar yükleme, soft delete, 2FA desteği
- **Aktivite Logları** — Spatie Activity Log ile model değişikliklerini takip
- **Ayarlar Paneli** — Genel, Kimlik Doğrulama, Mail, Depolama ayarları (veritabanında saklanır)
- **OAuth2 API** — Laravel Passport ile kişisel erişim token'ları ve cihaz yetkilendirme
- **Domain Oluşturucu** — `make:sk-domain` komutu ile interaktif DDD katman oluşturma
- **FormBuilder / DatatableBuilder / TabBuilder** — Tekrar kullanılabilir Vue bileşen oluşturucular
- **Çoklu Dil Desteği** — Dil dosyaları dahil, kolayca genişletilebilir
- **API Response Builder** — Tutarlı, akıcı API yanıtları ve sayfalama desteği
- **Güvenlik Başlıkları Middleware** — X-Frame-Options, HSTS, CSP ve daha fazlası

## Gereksinimler

- PHP 8.2+
- Laravel 12
- Node.js 18+
- MySQL / PostgreSQL / SQLite

## Kurulum

### 1. Paketi projeye ekleyin

**Packagist'ten (yayınlandığında):**

```bash
composer require lvntr/starter-kit
```

**Yerel dosya yolundan (geliştirme ortamı):**

Projenizin `composer.json` dosyasına ekleyin:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/lvntr/starter-kit"
        }
    ]
}
```

Ardından:

```bash
composer require lvntr/starter-kit:@dev
```

**Özel Git deposundan:**

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/your-org/starter-kit.git"
        }
    ]
}
```

Ardından:

```bash
composer require lvntr/starter-kit
```

### 2. Kurulum komutunu çalıştırın

```bash
php artisan sk:install
```

Bu interaktif sihirbaz sırasıyla şunları yapar:

1. Tüm uygulama iskeletini yayınlar (Controller, Model, Route, Vue sayfa dosyaları vb.)
2. Paket konfigürasyon dosyasını yayınlar
3. Veritabanı migration'larını çalıştırır
4. Seeder'ları çalıştırır (Roller, Yetkiler, Tanımlar, Ayarlar)
5. Passport şifreleme anahtarlarını oluşturur
6. Varsayılan admin kullanıcısını oluşturur
7. npm bağımlılıklarını kurar ve frontend asset'lerini derler

**Etkileşimsiz mod (CI/CD ortamları için):**

```bash
php artisan sk:install --no-interaction
```

**Mevcut dosyaların üzerine yaz:**

```bash
php artisan sk:install --force
```

### 3. `.env` dosyanızı yapılandırın

```env
APP_NAME="Uygulamam"
APP_URL=https://uygulamam.test

DB_CONNECTION=mysql
DB_DATABASE=uygulama_db
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Admin paneline erişin

Tarayıcınızı açın ve uygulama URL'inize gidin. Kurulum sonrası gösterilen admin bilgileriyle giriş yapın (varsayılan: `admin@demo.com` / `password`).

## Güncelleme

Paketin yeni bir sürümü yayınlandığında:

```bash
composer update lvntr/starter-kit
php artisan sk:update
```

Güncelleme komutu **hash tabanlı takip sistemi** kullanarak dosyaları güvenli şekilde günceller:

- **Çekirdek dosyalar** (BaseAction, BaseDTO, Trait'ler, Middleware, helper'lar) her zaman güncellenir
- **Kullanıcı tarafından değiştirilebilir dosyalar** (Controller, Sayfa, Route) yalnızca siz değiştirmediyseniz güncellenir
- **Yeni dosyalar** paketten otomatik olarak eklenir
- **Yeni migration'lar** tespit edilir ve isteğe bağlı olarak çalıştırılır

**Değişiklikleri uygulamadan önce önizleme:**

```bash
php artisan sk:update --dry-run
```

**Her şeyi zorla güncelle (değişikliklerinizin üzerine yazar):**

```bash
php artisan sk:update --force
```

## Opsiyonel Asset'leri Yayınlama

Paket, Vue bileşenlerini, dil dosyalarını ve konfigürasyonu varsayılan olarak kendi içinde tutar. Özelleştirme gerekiyorsa projenize yayınlayabilirsiniz:

```bash
# İnteraktif seçim
php artisan sk:publish

# Vue bileşenlerini yayınla (FormBuilder, DatatableBuilder vb.)
php artisan sk:publish --tag=components

# Dil dosyalarını yayınla
php artisan sk:publish --tag=lang

# Konfigürasyon dosyasını yayınla
php artisan sk:publish --tag=config
```

## Kullanılabilir Komutlar

| Komut | Açıklama |
|---|---|
| `sk:install` | Tam kurulum sihirbazı |
| `sk:update` | Kullanıcı değişikliklerini koruyarak paket dosyalarını güncelle |
| `sk:publish` | Özelleştirme için opsiyonel asset'leri yayınla |
| `make:sk-domain` | İnteraktif olarak tam bir DDD domain'i oluştur |
| `remove:sk-domain` | Bir domain'i ve tüm dosyalarını kaldır |
| `env:sync` | .env anahtarlarını .env.example ile senkronize et |

### Domain Oluşturma

Tüm DDD katmanlarıyla yeni bir domain oluşturun:

```bash
# İnteraktif mod
php artisan make:sk-domain

# Seçeneklerle
php artisan make:sk-domain Product --fields="name:string,price:decimal" --admin --api --events --vue=full
```

Bu komut şunları oluşturur: Model, Migration, Factory, DTO, Action, Event, Listener, Controller, FormRequest, Route ve Vue sayfaları.

Bir domain'i kaldırma:

```bash
php artisan remove:sk-domain Product
```

## Mimari

### Paket Yapısı

```
lvntr/starter-kit/
├── src/                          # Çekirdek paket kodu (hiçbir zaman yayınlanmaz)
│   ├── StarterKitServiceProvider.php
│   ├── Console/Commands/         # sk:install, sk:update, make:sk-domain vb.
│   ├── Domain/Shared/            # BaseAction, BaseDTO, ActionPipeline
│   ├── Enums/                    # PermissionEnum, HasDefinition, EnumRegistry
│   ├── Http/Middleware/          # CheckResourcePermission, SecurityHeaders
│   ├── Http/Responses/           # ApiResponse builder
│   ├── Traits/                   # HasActivityLogging, HasEnumAccessors, HasMediaCollections
│   └── helpers.php               # to_api(), format_date()
├── resources/
│   ├── js/components/            # Vue bileşenleri (isteğe bağlı yayınlanabilir)
│   └── lang/                     # Dil dosyaları (isteğe bağlı yayınlanabilir)
├── stubs/                        # Kurulumda uygulamaya kopyalanır
│   ├── app/                      # Controller, Model, Domain, Provider, Enum
│   ├── config/                   # permission-resources.php, settings.php
│   ├── database/                 # Migration, Seeder, Factory
│   ├── routes/                   # Web ve API route'ları
│   ├── resources/js/             # Vue sayfaları, Layout, Composable, Tema
│   └── bootstrap/                # app.php, providers.php
└── config/
    └── starter-kit.php           # Paket konfigürasyonu
```

### Uygulama Yapısı (kurulumdan sonra)

```
app/
├── Domain/                       # DDD iş mantığı
│   ├── User/                     # Action, DTO, Query, Event, Listener
│   ├── Role/
│   ├── Auth/
│   ├── Setting/
│   ├── ActivityLog/
│   └── Shared/                   # Temel sınıflar (paket tarafından güncellenir)
├── Http/
│   ├── Controllers/Admin/        # Admin panel controller'ları
│   ├── Controllers/Api/          # REST API controller'ları
│   └── Middleware/
├── Models/
├── Enums/
└── Providers/
```

### Güncelleme Stratejisi

| Dosya Kategorisi | `sk:update` Davranışı |
|---|---|
| `Domain/Shared/`, Trait'ler, Middleware, helper'lar | Her zaman güncellenir |
| Controller, Model, Sayfa, Route | Yalnızca kullanıcı değiştirmediyse güncellenir |
| Kullanıcının özel domain'leri | Hiçbir zaman dokunulmaz |
| Paketten gelen yeni dosyalar | Otomatik olarak eklenir |

## Paket Bileşenlerini Kullanma

### Vue Bileşenleri (yayınlamadan)

Bileşenler paketten otomatik olarak çözümlenir. Vue dosyalarınızda kullanın:

```vue
<template>
    <SkForm :form="form" :builder="formBuilder" />
    <SkDatatable :builder="tableBuilder" />
    <SkTabs :builder="tabBuilder" />
</template>
```

### Çeviriler

```php
// Paket namespace'inden
__('starter-kit::admin.menu.dashboard')
__('starter-kit::message.created')
```

### Temel Sınıflar

```php
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;
use Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO;
use Lvntr\StarterKit\Enums\PermissionEnum;
use Lvntr\StarterKit\Traits\HasActivityLogging;
```

## npm Paketi (Opsiyonel)

Vue bileşenlerini npm paketi olarak import etmeyi tercih ederseniz:

```bash
npm install ./packages/lvntr/starter-kit
```

```ts
import { SkForm, SkDatatable, SkTabs } from '@lvntr/starter-kit'
```

## Lisans

MIT
