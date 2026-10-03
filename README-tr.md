# Lvntr Starter Kit

### Admin odaklı Laravel starter kit.

![CI](https://img.shields.io/github/actions/workflow/status/lvntrdev/laravel-starter-kit/ci.yml?branch=main&style=flat-square&label=CI)
![License](https://img.shields.io/badge/license-MIT-3b82f6?style=flat-square)
![Packagist Sürüm](https://img.shields.io/packagist/v/lvntr/laravel-starter-kit?style=flat-square&label=packagist)
![Downloads](https://img.shields.io/packagist/dt/lvntr/laravel-starter-kit?style=flat-square&label=downloads)

![Lvntr Starter Kit dashboard](.github/screenshots/dashboard-aura-light.jpg)

## Tanıtım

Lvntr Starter Kit; **Laravel 13**, **Inertia.js v3**, **Vue 3**, **PrimeVue 4** ve **Tailwind CSS 4** üzerine kurulmuş, tam donanımlı bir Laravel admin panel paketidir.

Resmi Laravel starter kit'leri yalnızca kimlik doğrulama iskeletiyle gelirken, bu paket daha ilk kurulumda production-ready bir admin paneli sunar: kullanıcılar, roller, yetkiler, aktivite kayıtları, ayarlar, dosya yöneticisi, 2FA ve genişletebileceğin DDD tarzı bir domain katmanı.

Her projede aynı admin ekranlarını sıfırdan yazmak istemeyip doğrudan iş mantığına odaklanmak isteyen ekipler için tasarlandı.

> **Web Sitesi & Dökümantasyon:** [starter-kit.lvntr.dev](https://starter-kit.lvntr.dev/)
> Kurulum rehberi, bileşen referansları, mimari notlar ve örnekler.

## Hızlı Tur

### İki tema, açık & koyu — yeniden derleme olmadan anında geçiş

Yerleşik **Aura** (marka renginde çerçeve içinde gömülü panel) ya da **Main** temasını seç, 26 vurgu renginden birini belirle; her kullanıcı koyu modu kendisi açıp kapatabilsin.

<table>
  <tr>
    <td width="50%"><img src=".github/screenshots/dashboard-aura-dark.jpg" alt="Aura teması, koyu mod"><br><sub><b>Aura</b> · koyu</sub></td>
    <td width="50%"><img src=".github/screenshots/dashboard-main-light.jpg" alt="Main teması, açık mod"><br><sub><b>Main</b> · açık</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src=".github/screenshots/dashboard-main-dark.jpg" alt="Main teması, koyu mod"><br><sub><b>Main</b> · koyu</sub></td>
    <td width="50%"><img src=".github/screenshots/settings-appearance.jpg" alt="Görünüm ayarları"><br><sub>Tema, vurgu rengi, logolar ve favicon Görünüm ayarlarından</sub></td>
  </tr>
</table>

### Kullanıcılar, roller & yetkiler

Server-side sayfalama, kolon gösterme/gizleme ve bulk action destekli, aranabilir ve filtrelenebilir datatable'lar; FormBuilder ile kurulan dialog formları; ve her rol için kaynak × yetenek yetki matrisi.

<table>
  <tr>
    <td width="50%"><img src=".github/screenshots/users.jpg" alt="Kullanıcı yönetimi"><br><sub>Kullanıcı yönetimi</sub></td>
    <td width="50%"><img src=".github/screenshots/users-edit.jpg" alt="Kullanıcı düzenleme dialogu"><br><sub>Avatar yüklemeli kullanıcı düzenleme dialogu</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src=".github/screenshots/roles.jpg" alt="Roller"><br><sub>Roller</sub></td>
    <td width="50%"><img src=".github/screenshots/role-permissions.jpg" alt="Rol yetki matrisi"><br><sub>Rol bazlı yetki matrisi</sub></td>
  </tr>
</table>

### Dosya yöneticisi

Klasörler, favoriler, saklama süreli çöp kutusu, görsel/video/PDF önizlemeleri, depolama kotası — yerel disk, Amazon S3, DigitalOcean Spaces ya da Hetzner Object Storage üzerinde.

<table>
  <tr>
    <td width="50%"><img src=".github/screenshots/file-manager.jpg" alt="Dosya yöneticisi"><br><sub>Dosya yöneticisi</sub></td>
    <td width="50%"><img src=".github/screenshots/settings-file-manager.jpg" alt="Dosya yöneticisi ayarları"><br><sub>Yükleme boyutu, kota, kabul edilen türler ve çöp kutusu</sub></td>
  </tr>
</table>

### Aktivite kayıtları & log görüntüleyici

Her model değişikliği, değişikliği yapan kişi ve alan bazında eski → yeni farkıyla kaydedilir. Laravel log dosyaları panelden gezilebilir; seviye, zaman ve mesaja göre filtrelenebilir.

<table>
  <tr>
    <td width="50%"><img src=".github/screenshots/activity-logs.jpg" alt="Aktivite kayıtları"><br><sub>Aktivite kayıtları</sub></td>
    <td width="50%"><img src=".github/screenshots/activity-log-detail.jpg" alt="Aktivite kaydı detayı"><br><sub>Alan bazında değişiklik detayı</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src=".github/screenshots/log-files.jpg" alt="Log dosyaları"><br><sub>Log dosyaları</sub></td>
    <td width="50%"><img src=".github/screenshots/log-viewer.jpg" alt="Log görüntüleyici"><br><sub>Seviye filtreli ve context'li log görüntüleyici</sub></td>
  </tr>
</table>

### Ayarlar paneli

Projelerde genelde `.env` içine gömülen her şey panelden düzenlenebilir — kimlik, dil ve para birimi, güvenlik politikası, SMTP, depolama sürücüsü, içerik dilleri, API client/token'ları — ayrıca `sk:doctor` kontrollerini çalıştıran bir Sistem Sağlığı sayfası.

<table>
  <tr>
    <td width="50%"><img src=".github/screenshots/settings-general.jpg" alt="Genel ayarlar"><br><sub>Genel</sub></td>
    <td width="50%"><img src=".github/screenshots/settings-security.jpg" alt="Güvenlik ayarları"><br><sub>Güvenlik — kayıt, 2FA, şifre politikası, bot koruması</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src=".github/screenshots/settings-mail.jpg" alt="Mail ayarları"><br><sub>Mail — SMTP ve test e-postası</sub></td>
    <td width="50%"><img src=".github/screenshots/settings-storage.jpg" alt="Depolama ayarları"><br><sub>Depolama sürücüsü</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src=".github/screenshots/settings-content-languages.jpg" alt="İçerik dilleri"><br><sub>Çevrilebilir alanlar için içerik dilleri</sub></td>
    <td width="50%"><img src=".github/screenshots/settings-system-health.jpg" alt="Sistem sağlığı"><br><sub>Sistem sağlığı</sub></td>
  </tr>
</table>

### Bileşen vitrini

Yerleşik bir sayfa, kitin PrimeVue + SK bileşenlerini tüm varyantlarıyla gösterir — tag'ler, butonlar, mesajlar, toast'lar ve FormBuilder formları.

![Bileşen vitrini](.github/screenshots/components.jpg)

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
    - Ayarlar paneli (Genel / Kimlik Doğrulama / Mail / Depolama / Dosya Yöneticisi / İçerik Dilleri)
    - Çok dilli içerik: Ayarlar'da yönetilen aktif diller, yeniden derleme gerekmeden tüm [Çevrilebilir Alan](./docs/translatable-fields.tr.md) formlarını anında etkiler
    - İmzalı paylaşım linki destekli, pluggable context'lere sahip Dosya Yöneticisi
    - API Client ve Personal Access Token yönetimi
    - Sistem Sağlık paneli
    - API Route tarayıcısı
    - Definitions (form ve tablolarda kullanılan DB tabanlı enum'lar)
- **Geliştirici Araçları**
    - DDD tarzı domain katmanı (Action / DTO / Query / Event / Listener)
    - FormBuilder, DatatableBuilder, TabBuilder fluent API'ları ([Çevrilebilir Alanlar](./docs/translatable-fields.tr.md) dahil)
    - `@lvntr/components` Vue komponent kütüphanesi (FormBuilder/DatatableBuilder/TabBuilder, UI primitifleri, Dosya Yöneticisi arayüzü) — npm'de yayınlanmaz; ayrı bir kurulum adımı gerekmeden Vite alias'ıyla paketin kendi `vendor/` kopyasından çözülür
    - `make:sk-domain` ile opt-in flag destekli domain iskeleti üretimi
    - Sayfa aşımı seçim desteği ile datatable bulk action API
    - `sk:update` ile güvenli güncelleme (hash tabanlı, kullanıcı değişikliklerini korur)
    - `sk:doctor` ile sistem sağlık kontrolü
    - Hassas ayarlar ve 2FA secret'ları için `APP_KEY`'den bağımsız, ayrı veri şifreleme anahtarı — `encryption:key`, `encryption:rekey`, `encryption:health` ile üret/döndür/doğrula
    - Yeniden derleme gerektirmeyen anlık geçişli yerleşik `main` ve `aura` kit temaları ile açık & koyu tema

## Nasıl Kullanılır?

Temiz bir Laravel kurulumundan başla:

```bash
composer create-project laravel/laravel my-app
cd my-app
composer require lvntr/laravel-starter-kit:^13.7
php artisan sk:install
```

> **Önce `php -v` kontrol edin — bu kit PHP 8.4+ gerektirir.** `laravel/laravel`
> iskeletinin kendisi yalnızca PHP 8.3 istediği için `create-project` 8.3'te
> sorunsuz tamamlanır ve hata daha sonra ortaya çıkar. Kiti her zaman `:^13.7`
> ile ekleyin (daha gevşek bir `:^13.0` ile değil): gevşek constraint'te
> Composer gerçek engeli bildirmek yerine sessizce, PHP 8.3'e hâlâ uyan çok
> eski bir sürüme iner.

Hepsi bu kadar. Kurulum sihirbazı migration, seeder, Passport anahtarları, varsayılan admin kullanıcısı ve frontend build işlemlerini otomatik yapar. Ayrıca `User` ve `Role` domain runtime sınıflarını `app/Domain/`'e eject eder, böylece anında proje-sahipli ve özelleştirmeye hazır olurlar. Bunun yerine vendor'da tutmak için `--without-eject` geçin.

Detaylı adım adım rehber: [starter-kit.lvntr.dev/docs/install](https://starter-kit.lvntr.dev/docs/install)

## Gereksinimler

- PHP 8.4+ (kesin taban — `spatie/laravel-activitylog:^5.0` da bunu gerektirir)
- Laravel 13
- Node.js 20.19+ (veya 22.12+) — Vite 7 engine tabanı
- MySQL veya MariaDB

## Uyumluluk & Sürümleme

Paketin major sürümü desteklenen Laravel major sürümüyle hizalanır. Her
Laravel major sürümü kendi bakım branch'ine ve `vN.x.y` tag akışına
sahiptir; mevcut consumer constraint'leri kendi major hattında kalır ve
daha yeni Laravel hedefinden kırıcı değişiklik almaz.

| Laravel | Constraint                                            | Branch  | Durum  |
|---------|-------------------------------------------------------|---------|--------|
| 13.x    | `composer require lvntr/laravel-starter-kit:^13.7`    | `13.x`  | aktif  |

`main` şu anda aktif major hattı takip eder (`13.x`). Gelecekte yeni bir
Laravel sürümü hedeflendiğinde `main` sonraki major geliştirme hattına
geçer; önceki major'un `N.x` branch'i ise backport almaya devam eder.

**Sürümün tek doğruluk kaynağı git tag'idir** — ne `composer.json` ne de
kök `package.json` bir `version` alanı taşır, dolayısıyla senkron tutulacak
bir şey yoktur. Sürümler `main` üzerinden `release.sh` ile çıkarılır; script
sürümü tag'ler ve yalnızca o tag'i push eder.

## Dökümantasyon

Kurulum, güncelleme akışı, domain scaffolding, FormBuilder / DatatableBuilder / TabBuilder API'ları, composable'lar, dosya yöneticisi, roller ve yetkiler, OAuth2 API, [AI odaklı API metadata](./docs/api-ai-metadata.tr.md), aktivite kayıtları, ayarlar — her şey resmi sitede:

**[starter-kit.lvntr.dev](https://starter-kit.lvntr.dev/)**

## Lisans

[MIT](./LICENSE)
