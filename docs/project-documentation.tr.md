# Proje Dökümantasyonu

Bu döküman, kurulumdan sonra starter kit'in yüksek seviyeli haritasını verir. Laravel 13, Inertia.js v3, Vue 3.5, Passport API authentication, Fortify web auth akışları ve paket destekli bir UI toolkit üzerine kurulu admin-öncelikli bir starter uygulamasıdır.

## Backend Alanları

- uygulamanıza scaffold edilen iş mantığı için `app/Domain/`; vendor-managed domain'lerin runtime katmanı paket içinde `src/Domain/` (`Lvntr\StarterKit\Domain\`) altında yaşar
- web ve API giriş noktaları için `app/Http/Controllers/`
- API yanıt biçimlendirmesi için `app/Http/Responses/`
- Eloquent modelleri için `app/Models/`
- app, domain, settings ve Fortify bootstrapping için `app/Providers/`
- modüler route yükleme için `routes/web*` ve `routes/api*`

### Ana Domain Modülleri

**Kurulum sonrası app-owned yüzey** — modül ihtiyaç duyuyorsa controller, FormRequest, model, route ve Vue sayfaları `app/` / `resources/` altına scaffold edilir. Domain runtime'ının kendisi modüle göre ayrılır:

- `Auth` tamamen app tarafındadır (`app/Domain/Auth`)
- `User` ve `Role`, `app/Domain/...` altında yalnızca app-owned `BulkActions` dilimini tutar; ana runtime vendor-resident'tır
- `Setting`, `ApiRoute` ve `ApiClient` gerektiği yerde app-owned HTTP/UI yüzeyi sağlar, fakat domain runtime vendor paketinden çalışır

**Vendor-resident runtime domain'leri (`src/Domain/`, `Lvntr\StarterKit\Domain\`)** — bu modüllerin Actions, DTOs, Queries, Events, Listeners ve Services katmanları paket içinden çalışır ve temiz kurulumda uygulamanıza kopyalanmaz. `App\Domain\<Module>\...` import'ları `class_alias` ile çalışmaya devam eder; eject ya da eski kurulumdan kalan yerel `app/Domain/<Module>/` kopyası varsa önceliklidir:

- `ActivityLog`
- `ApiClient`
- `ApiRoute`
- `FileManager`
- `Logs`
- `Media`
- `Role`
- `Session`
- `Setting`
- `Shared`
- `User`

Tam vendor-resident model ve reconcile adımları için [ddd.md](./ddd.tr.md) dosyasına bakın.

### Tipik Request Akışı

1. Route, ince tutulmuş bir controller'a gider.
2. Gerekliyse validasyon Form Request ile yapılır.
3. Veri, ilgili özellik DTO kullanıyorsa DTO'ya dönüştürülür.
4. İş mantığı action sınıflarında çalışır — scaffold edilen domain'ler için `app/Domain/.../Actions`, vendor-resident olanlar için `src/Domain/.../Actions` (vendor namespace) altında.
5. Listeleme ve filtreleme için Query sınıfları kullanılır.
6. Cevaplar Inertia veya `to_api()` ile döndürülür.

### Domain Event'leri

Vendor-resident `User`, `Role` ve `Logs` runtime'ına ait kit audit event/listener eşleşmeleri vendor FQCN'leriyle `StarterKitServiceProvider::registerEventListeners()` içinde kaydedilir:

- `UserCreated -> LogUserCreated`
- `UserUpdated -> LogUserUpdated`
- `UserDeleted -> LogUserDeleted`
- `RoleCreated -> LogRoleCreated`
- `RoleUpdated -> LogRoleUpdated`
- `RoleDeleted -> LogRoleDeleted`
- `LogFilesDeleted -> LogActivityForLogFilesDeleted`

Scaffold edilen `app/Providers/DomainServiceProvider.php` kendi uygulama event'leriniz için bırakılır. `sk:eject`, bir kit domain'ini tekrar `app/Domain/` altına kopyaladığınızda buraya binding ekleyebilir.

## Frontend Alanları

- Inertia sayfaları için `resources/js/pages/`
- ortak layout'lar için `resources/js/layouts/`
- tekrar kullanılabilir starter-kit bileşenleri için `resources/js/components/Lvntr-Starter-Kit/`
- istemci tarafı davranışları için `resources/js/composables/`
- Wayfinder tarafından üretilen yardımcılar için `resources/js/routes/` ve `resources/js/actions/`

### Inertia Sayfaları

Sayfalar `resources/js/pages/` altında bulunur. Örnekler:

- `resources/js/pages/Admin/Users`
- `resources/js/pages/Admin/Roles`
- `resources/js/pages/Admin/Settings`
- `resources/js/pages/Admin/ApiRoutes`
- `resources/js/pages/Admin/Files`
- `resources/js/pages/Admin/Logs`
- `resources/js/pages/Profile`

### Tekrar Kullanılabilir UI Toolkit

Admin panel, `@lvntr/*` alias'ı üzerinden gelen ortak UI bloklarını kullanır. Örnekler:

- `@lvntr/components/DatatableBuilder/core`
- `@lvntr/components/FormBuilder/core`
- `@lvntr/components/TabBuilder/core`
- `@lvntr/components/ui/AppDialog.vue`

## Request Desenleri

- tarayıcı sayfaları Inertia kullanır
- JSON endpoint'leri `to_api()` ve `ApiResponse` kullanır
- liste yoğun admin ekranları datatable query sınıflarını kullanır
- settings ve benzeri yazma işlemleri Form Request ve Action üzerinden akmalıdır

### Authentication Çalışma Yapısı

- Fortify, tarayıcı auth ekranlarını `resources/js/pages/Auth` altındaki Inertia sayfaları üzerinden render eder
- login pipeline'ı rate limiting, Turnstile doğrulaması, pasif kullanıcı engeli ve opsiyonel iki faktör yönlendirmesini içerir
- Passport, API tüketicileri için `/api/v1/auth/*` personal access token akışlarını yönetir

## Routing Stratejisi

Route dosyaları özelliğe göre bölünür. `routes/web.php`, `routes/web/` altındaki dosyaları yükler; `routes/api.php` ise `routes/api/` altındaki dosyaları yükler.

- public route'lar önce yüklenir
- authenticated route'lar `auth` ve `verified` altında gruplanır
- permission korumalı route dosyaları `check.permission` ile sarılır
- API route'ları `/api/v1` altında throttle ve `auth:api` kurallarıyla gruplanır

### Frontend Servis Route'ları

`routes/web/service-route.php`, giriş yapmış web kullanıcıları için yüklenir:

- `GET /definitions`, `useDefinition()` ve builder tabanlı option yüklemelerini besler
- `GET /roles/options`, admin form ve filtreleri için select seçenekleri sağlar

### Public Yardımcı Route'lar

`routes/web/public-route.php`, herkese açık hafif yardımcı route'ları taşır:

- `POST /locale`, aktif arayüz dilini session içinde günceller

### Özellik Bazlı Admin Route'ları

Bazı admin ekranları kendi route dosyalarında ayrılmıştır:

- `routes/web/developer-route.php`, `api-routes.*` ekranını yükler
- `routes/web/files-route.php`, global dosya yöneticisini `files.index` adıyla açar
- `routes/web/log-route.php`, system-admin log görüntüleyiciyi `logs.*` altında açar
- `routes/web/profile-route.php`, profil, avatar ve tarayıcı oturumları uçlarını içerir

## Ortak Yapı Taşları

- `app/Helpers/sk-helpers.php` ve `app/Helpers/custom.php` içindeki helper'lar
- `ApiException` ve `ApiExceptionHandler`
- permission middleware (`check.permission`)
- security headers middleware
- definitions sistemi

### `AdminLayout` İçindeki Global Overlay'ler

`AdminLayout.vue`, ortak overlay bileşenlerini bir kez render eder:

- `ConfirmDialogComponent`
- `ToastComponent`
- `AppDialog`
- `ImageLightbox`

### Definitions

Mevcut UI akışı, ayrı bir enum paylaşım katmanından ziyade veritabanı tabanlı definitions sistemini merkezde tutar.

- `_02_DefinitionSeeder.php`, `userStatus`, `gender`, `identityType` ve `yesNo` gibi key'leri seed eder
- `DefinitionService` (vendor-resident `Lvntr\StarterKit\Domain\Shared\Services\`, `App\Domain\Shared\Services\DefinitionService` alias'ı üzerinden erişilebilir) definition kayıtlarını locale bazlı gruplayıp cache'ler
- `useDefinition()`, bunları `GET /definitions` üzerinden tüketir
- definition kayıtları label, severity ve opsiyonel icon metadatası taşır
- `SkDatatable` ve `SkForm`, `.tag('definition').tagKey('userStatus')` ve `.definitionOptions('gender')` gibi tanımlarla bu key'lere doğrudan bağlanabilir
- `SkDatatable`, definition tag'lerini `SkTag` ile render eder; böylece DB tabanlı metadata kolon seviyesindeki `colors()`, `icons()` ve tag stil yardımcıları ile birleştirilebilir

### Flash Mesajları

Controller'lar flash mesajlarla redirect eder, `AdminLayout.vue` ise bunları PrimeVue toast olarak gösterir.

### Local Composable'lar

Projeye özel composable'lar `resources/js/composables/` altında tutulur. Admin sidebar tarafında menü tanımları `useAdminMenu()` içinde kalır; filtreleme ve aktif durum mantığı ise ortak `useMenuBuilder()` composable'ı ile paylaşılır.

## Önerilen Okuma

**Başlangıç**

- [welcome.tr.md](./welcome.tr.md) — kit nedir ve içinde ne gelir
- [project-info.tr.md](./project-info.tr.md) — stack ve yüksek seviyeli proje özeti
- [install.tr.md](./install.tr.md) — kurulum akışı
- [update.tr.md](./update.tr.md) — güncel stub'ları çekme (hash tabanlı)
- [UPGRADE.tr.md](./UPGRADE.tr.md) — sürüm yükseltme notları

**Backend & DDD**

- [ddd.tr.md](./ddd.tr.md) — domain yerleşimi ve vendor-resident model
- [auth.tr.md](./auth.tr.md) — Fortify (web) + Passport (API) kimlik doğrulama
- [roles-permissions.tr.md](./roles-permissions.tr.md) — permission resource'ları ve seed
- [api.tr.md](./api.tr.md) — API yanıt zarfı ve konvansiyonlar
- [api-clients.tr.md](./api-clients.tr.md) — Passport client & token yönetimi
- [api-routes.tr.md](./api-routes.tr.md) — API route envanteri ekranı
- [module-routes.tr.md](./module-routes.tr.md) — modüler route registry
- [definitions.tr.md](./definitions.tr.md) — ortak label/value lookup'ları
- [settings.tr.md](./settings.tr.md) — uygulama ayarları modülü
- [activity-logs.tr.md](./activity-logs.tr.md) — audit/aktivite loglama
- [logs.tr.md](./logs.tr.md) — uygulama log görüntüleyici

**Frontend & UI builder'lar**

- [formbuilder.tr.md](./formbuilder.tr.md) — FormBuilder (FB)
- [datatable.tr.md](./datatable.tr.md) — DatatableBuilder (DB)
- [tabs.tr.md](./tabs.tr.md) — TabBuilder (TB)
- [composables.tr.md](./composables.tr.md) — Vue composable'ları
- [admin-components.tr.md](./admin-components.tr.md) — admin sayfa stil rehberi
- [ui-components.tr.md](./ui-components.tr.md) — tekrar kullanılabilir UI primitive'leri
- [theme.tr.md](./theme.tr.md) — tema sistemi
- [wayfinder.tr.md](./wayfinder.tr.md) — tip güvenli route helper'ları

**Özellikler**

- [file-manager.tr.md](./file-manager.tr.md) — dosya yöneticisi
- [files.tr.md](./files.tr.md) — dosya yükleme
- [i18n.tr.md](./i18n.tr.md) — uluslararasılaştırma
- [translatable-fields.tr.md](./translatable-fields.tr.md) — çok dilli model alanları

**Araçlar**

- [artisan-commands.tr.md](./artisan-commands.tr.md) — `sk:*` komut referansı
- [claude-skills.tr.md](./claude-skills.tr.md) — paketle gelen Claude Code skill'leri
