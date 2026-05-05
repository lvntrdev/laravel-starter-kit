# Paket Dosya Aktarım Matrisi

Bu doküman, `lvntr/laravel-starter-kit` paketinin bir Laravel projesine kurulumda ve güncellemede hangi dosya gruplarını taşıdığını özetler.

> Not: `composer require` ve `composer update` paketin kendi kodunu `vendor/lvntr/laravel-starter-kit/` altına getirir. Uygulama köküne dosya kopyalama işlemi `php artisan sk:install`, `php artisan sk:update` ve isteğe bağlı `php artisan sk:publish` komutlarıyla yapılır.

## Komut Bazlı Özet

| Komut                                        | Kaynak                                    | Hedef                               | Davranış                                | Kısa açıklama                                                                                                                      |
| -------------------------------------------- | ----------------------------------------- | ----------------------------------- | --------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| `composer require lvntr/laravel-starter-kit` | Packagist / path repository               | `vendor/lvntr/laravel-starter-kit/` | Paketi projeye ekler                    | Komut sınıfları, servis provider, paket config'i ve paket içi kaynaklar vendor altına gelir; uygulama dosyaları henüz yayınlanmaz. |
| `php artisan sk:install`                     | `vendor/lvntr/laravel-starter-kit/stubs/` | Laravel proje kökü                  | İlk kurulumda stub dosyalarını kopyalar | Controller, model, route, migration, Vue sayfaları, layout, config ve dil dosyaları uygulamaya aktarılır.                          |
| `php artisan sk:install --force`             | `stubs/`                                  | Laravel proje kökü                  | Mevcut dosyaların üzerine yazar         | Yeniden kurulum veya bilinçli sıfırlama için kullanılır.                                                                           |
| `composer update lvntr/laravel-starter-kit`  | Packagist / path repository               | `vendor/lvntr/laravel-starter-kit/` | Vendor paketini günceller               | Paket kodu ve yeni stub kaynakları vendor altında güncellenir; uygulama köküne otomatik kopyalama yapmaz.                          |
| `php artisan sk:update`                      | `stubs/`                                  | Laravel proje kökü                  | Hash takipli güncelleme yapar           | Güvenli çekirdek dosyaları günceller, kullanıcı tarafından değiştirilmiş dosyaları korur, yeni dosyaları ekler.                    |
| `php artisan sk:update --dry-run`            | `stubs/`                                  | Yok                                 | Sadece önizleme yapar                   | Hangi dosyaların ekleneceğini, güncelleneceğini, atlanacağını gösterir.                                                            |
| `php artisan sk:update --force`              | `stubs/`                                  | Laravel proje kökü                  | Kullanıcı değişikliklerini de ezer      | Sadece yerel değişikliklerin üzerine yazmak istendiğinde kullanılmalıdır.                                                          |
| `php artisan sk:publish`                     | Paket kaynakları                          | Seçilen hedefler                    | İsteğe bağlı özelleştirme yayınlar      | Bileşen, dil, config veya helper gibi seçili kaynakları proje seviyesine kopyalar.                                                 |

## `sk:install` İle Aktarılan Dosya Grupları

`sk:install`, paket içindeki `stubs/` klasörünü uygulama köküne yayınlar. Mevcut durumda stub ağacında yaklaşık 411 dosya bulunur.

| Aktarılan yol                     | Kaynak yol                                  | Hedef yol                         | Kısa açıklama                                                                                                       |
| --------------------------------- | ------------------------------------------- | --------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| `.env.example`                    | `stubs/.env.example`                        | `.env.example`                    | Starter kit'in önerdiği env anahtarlarını içeren örnek ortam dosyası.                                               |
| `app/Actions/Fortify/`            | `stubs/app/Actions/Fortify/`                | `app/Actions/Fortify/`            | Fortify kayıt, profil, şifre ve Turnstile aksiyonları.                                                              |
| `app/Console/Commands/`           | `stubs/app/Console/Commands/`               | `app/Console/Commands/`           | `site:install`, `env:sync`, domain üretimi, API doküman sync ve bakım komutları.                                    |
| `app/Domain/`                     | `stubs/app/Domain/`                         | `app/Domain/`                     | Auth, User, Role, Setting, FileManager, Logs, SampleContent ve ortak DDD katmanları.                                |
| `app/Enums/`                      | `stubs/app/Enums/`                          | `app/Enums/`                      | Rol ve permission enum tanımları.                                                                                   |
| `app/Exceptions/`                 | `stubs/app/Exceptions/`                     | `app/Exceptions/`                 | API exception sınıfları ve JSON exception mapping altyapısı.                                                        |
| `app/Helpers/`                    | `stubs/app/Helpers/`                        | `app/Helpers/`                    | Uygulamaya ait custom helper dosyası. Paket helper'ları vendor tarafından sağlanır.                                 |
| `app/Http/Controllers/`           | `stubs/app/Http/Controllers/`               | `app/Http/Controllers/`           | Admin, API, auth, profil, locale, file manager ve servis controller'ları.                                           |
| `app/Http/Middleware/`            | `stubs/app/Http/Middleware/`                | `app/Http/Middleware/`            | Inertia, trace id, locale, security header, Turnstile ve permission middleware'leri.                                |
| `app/Http/Requests/`              | `stubs/app/Http/Requests/`                  | `app/Http/Requests/`              | API, admin, settings ve file manager validation request'leri.                                                       |
| `app/Http/Resources/`             | `stubs/app/Http/Resources/`                 | `app/Http/Resources/`             | Admin/API JSON resource sınıfları.                                                                                  |
| `app/Http/Responses/`             | `stubs/app/Http/Responses/`                 | `app/Http/Responses/`             | `ApiResponse` ve datatable response/query builder altyapısı.                                                        |
| `app/Listeners/`                  | `stubs/app/Listeners/`                      | `app/Listeners/`                  | Login sonrası son giriş bilgisini güncelleyen listener.                                                             |
| `app/Models/`                     | `stubs/app/Models/`                         | `app/Models/`                     | User, Role, Permission, Setting, Definition, Media ve File Manager modelleri.                                       |
| `app/Policies/`                   | `stubs/app/Policies/`                       | `app/Policies/`                   | Kullanıcı, rol, ayar ve dosya klasörü yetki politikaları.                                                           |
| `app/Providers/`                  | `stubs/app/Providers/`                      | `app/Providers/`                  | App, Fortify, Domain ve Settings servis provider'ları.                                                              |
| `app/Rules/`                      | `stubs/app/Rules/`                          | `app/Rules/`                      | Turnstile validation rule.                                                                                          |
| `app/Support/`                    | `stubs/app/Support/`                        | `app/Support/`                    | Scramble, media path generator, translatable rules/query helpers ve HTML sanitizer destek sınıfları.                |
| `app/Traits/`                     | `stubs/app/Traits/`                         | `app/Traits/`                     | Activity log ve media collection trait'leri.                                                                        |
| `bootstrap/app.php`               | `stubs/bootstrap/app.php`                   | `bootstrap/app.php`               | Starter kit middleware, exception ve route bootstrap bağlantıları.                                                  |
| `bootstrap/providers.php`         | `stubs/bootstrap/providers.php`             | `bootstrap/providers.php`         | Starter kit provider kayıtları.                                                                                     |
| `config/activitylog.php`          | `stubs/config/activitylog.php`              | `config/activitylog.php`          | Spatie activity log ayarları.                                                                                       |
| `config/inertia.php`              | `stubs/config/inertia.php`                  | `config/inertia.php`              | Inertia ayarları.                                                                                                   |
| `config/permission-resources.php` | `stubs/config/permission-resources.php`     | `config/permission-resources.php` | Rol/permission kaynak matrisi; güncellemede kullanıcıya ait kabul edilir.                                           |
| `config/settings.php`             | `stubs/config/settings.php`                 | `config/settings.php`             | Uygulama ayar grupları ve varsayılanları.                                                                           |
| `database/factories/`             | `stubs/database/factories/`                 | `database/factories/`             | User ve sample content factory'leri.                                                                                |
| `database/migrations/`            | `stubs/database/migrations/`                | `database/migrations/`            | Kullanıcı, cache, job, Passport, permission, media, activity log, definition, setting ve file manager tabloları.    |
| `database/seeders/`               | `stubs/database/seeders/`                   | `database/seeders/`               | Role/permission, definition ve setting seed akışı.                                                                  |
| `lang/en/`                        | `stubs/lang/en/`                            | `lang/en/`                        | İngilizce uygulama çevirileri.                                                                                      |
| `lang/tr/`                        | `stubs/lang/tr/`                            | `lang/tr/`                        | Türkçe uygulama çevirileri.                                                                                         |
| `resources/css/`                  | `stubs/resources/css/`                      | `resources/css/`                  | Tailwind 4 giriş dosyası ve starter kit tema stilleri.                                                              |
| `resources/js/app.ts`             | `stubs/resources/js/app.ts`                 | `resources/js/app.ts`             | Inertia Vue client/SSR giriş dosyası.                                                                               |
| `resources/js/components/`        | `stubs/resources/js/components/`            | `resources/js/components/`        | Auto-import kapsamındaki uygulama bileşenleri ve auth widget'ları.                                                  |
| `resources/js/composables/`       | `stubs/resources/js/composables/`           | `resources/js/composables/`       | Menü, API, dialog, dark mode, permission, flash, sidebar ve refresh bus composable'ları.                            |
| `resources/js/layouts/`           | `stubs/resources/js/layouts/`               | `resources/js/layouts/`           | Admin, auth ve default layout bileşenleri.                                                                          |
| `resources/js/pages/`             | `stubs/resources/js/pages/`                 | `resources/js/pages/`             | Admin, auth, profile, dashboard, logs, settings, users, roles ve sample content Inertia sayfaları.                  |
| `resources/js/plugins/`           | `stubs/resources/js/plugins/`               | `resources/js/plugins/`           | Permission plugin bağlantısı.                                                                                       |
| `resources/js/theme/`             | `stubs/resources/js/theme/`                 | `resources/js/theme/`             | PrimeVue tema preset'i.                                                                                             |
| `resources/js/types/`             | `stubs/resources/js/types/`                 | `resources/js/types/`             | TypeScript model ve ortak tip tanımları.                                                                            |
| `resources/views/`                | `stubs/resources/views/`                    | `resources/views/`                | Inertia app view ve vendor view override'ları.                                                                      |
| `routes/api.php` ve `routes/api/` | `stubs/routes/api.php`, `stubs/routes/api/` | `routes/api.php`, `routes/api/`   | API route ana dosyası ve modüler API route dosyaları.                                                               |
| `routes/web.php` ve `routes/web/` | `stubs/routes/web.php`, `stubs/routes/web/` | `routes/web.php`, `routes/web/`   | Inertia web route ana dosyası ve modüler web route dosyaları.                                                       |
| `routes/console.php`              | `stubs/routes/console.php`                  | `routes/console.php`              | Console route tanımları.                                                                                            |
| `package.json`                    | `stubs/package.json`                        | `package.json`                    | Doğrudan kopyalanmaz; mevcut dosyayla merge edilir. Stub dependency versiyonları kazanır, kullanıcı ekleri korunur. |
| `tsconfig.json`                   | `stubs/tsconfig.json`                       | `tsconfig.json`                   | TypeScript ve path alias ayarları.                                                                                  |
| `vite.config.ts`                  | `stubs/vite.config.ts`                      | `vite.config.ts`                  | Vite, Inertia SSR, Vue, Tailwind ve Wayfinder yapılandırması.                                                       |

## Kurulumda Yapılan Ek Dosya İşlemleri

| İşlem                             | Etkilenen dosya/yol                                                                                                                               | Davranış                                               | Kısa açıklama                                                                              |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------ | ------------------------------------------------------------------------------------------ |
| Veritabanı env yazımı             | `.env`                                                                                                                                            | Oluşturur veya anahtarları günceller                   | Interaktif kurulumda DB bağlantı bilgilerini `.env` içine yazar.                           |
| Çakışan Laravel dosyalarını silme | `vite.config.js`, `vite.config.mjs`, `resources/js/app.js`, `resources/js/bootstrap.js`, `resources/views/welcome.blade.php`, `package-lock.json` | Varsa siler                                            | Laravel'in varsayılan iskeletiyle starter kit dosyalarının çakışmasını engeller.           |
| Paket config yayınlama            | `vendor/.../config/starter-kit.php` -> `config/starter-kit.php`                                                                                   | `vendor:publish --tag=starter-kit-config` ile yayınlar | Starter kit'in kendi config dosyasıdır.                                                    |
| App config inject                 | `config/app.php`                                                                                                                                  | AST ile eksik anahtar ekler                            | `display_timezone`, `available_languages`, `languages` anahtarlarını ekler.                |
| Filesystem config inject          | `config/filesystems.php`                                                                                                                          | AST ile eksik disk ekler                               | `do` DigitalOcean Spaces disk tanımını ekler.                                              |
| Media library config inject       | `config/media-library.php`                                                                                                                        | Yoksa vendor config'i kopyalar, sonra düzenler         | `App\Support\MediaPathGenerator` path generator olarak ayarlanır.                          |
| Bootstrap inject                  | `bootstrap/app.php`                                                                                                                               | Eksik hook'ları ekler                                  | Starter kit middleware, route ve exception bağlantılarını idempotent şekilde bağlar.       |
| Provider inject                   | `bootstrap/providers.php`                                                                                                                         | Eksik provider'ları ekler                              | Starter kit'in yayınlanan provider'larını kaydeder.                                        |
| Helper autoload inject            | `composer.json`                                                                                                                                   | `autoload.files` içine ekler                           | `app/Helpers/custom.php` autoload edilir.                                                  |
| Hash kayıt dosyası                | `storage/starter-kit/hashes.json`                                                                                                                 | Oluşturur                                              | `sk:update` sırasında kullanıcı değişikliklerini ayırt etmek için stub hash'leri saklanır. |
| Composer autoload                 | `vendor/composer/*`                                                                                                                               | Yeniden üretir                                         | Yayınlanan sınıfların aynı süreçte kullanılabilmesi için `composer dump-autoload` çalışır. |
| Migration                         | Veritabanı                                                                                                                                        | Opsiyonel çalışır                                      | Kurulum sırasında `migrate` veya `migrate:fresh` çalıştırılabilir.                         |
| Seeder                            | Veritabanı                                                                                                                                        | Opsiyonel çalışır                                      | `_01_`, `_02_`, `_03_` sıralı seeder'ları çalıştırır.                                      |
| Passport key                      | `storage/oauth-private.key`, `storage/oauth-public.key`                                                                                           | Opsiyonel üretir                                       | `passport:keys --force` çalıştırılır.                                                      |
| Frontend build                    | `node_modules/`, `package-lock.json`, `public/build/`, Wayfinder generated files                                                                  | Opsiyonel üretir/günceller                             | `npm install`, cache clear, `wayfinder:generate`, `npm run build` çalışır.                 |

## `sk:update` Güncelleme Stratejisi

| Dosya tipi                                                    | Davranış                                  | Kısa açıklama                                                                              |
| ------------------------------------------------------------- | ----------------------------------------- | ------------------------------------------------------------------------------------------ |
| Güvenli çekirdek yollar                                       | Her zaman güncellenir                     | Paket sahipli, kullanıcı tarafından değiştirilmemesi beklenen altyapı dosyalarıdır.        |
| Kullanıcı tarafından değiştirilebilir stub dosyaları          | Sadece değişmemişse güncellenir           | Hash kaydı hedef dosyanın kullanıcı tarafından değiştiğini gösteriyorsa dosya korunur.     |
| Hash kaydı olmayan farklı dosyalar                            | Kullanıcıya sorulur                       | `--dry-run` sadece listeler; normal çalışmada seçilenler güncellenir.                      |
| Yeni stub dosyaları                                           | Otomatik eklenir                          | Hedefte yoksa ve kullanıcı daha önce silmiş görünmüyorsa kopyalanır.                       |
| Daha önce kurulmuş ama kullanıcı tarafından silinmiş dosyalar | Yeniden eklenmez                          | Hash kaydı varsa kullanıcı tercihi kabul edilir.                                           |
| `config/permission-resources.php`                             | Asla otomatik güncellenmez                | Kullanıcıya ait özelleştirilebilir permission matrisi kabul edilir.                        |
| Deprecated dosyalar                                           | Kaldırılır veya boş klasörse silinir      | Paket ağacından çıkarılmış eski dosyalar temizlenir.                                       |
| `package.json`                                                | Merge edilir                              | Yeni frontend bağımlılıkları eklenir; kullanıcı ekleri korunur; stub versiyonları kazanır. |
| `config/filesystems.php`                                      | Eksikse inject edilir                     | `do` disk tanımı yoksa eklenir.                                                            |
| `config/media-library.php`                                    | Eksikse publish edilir ve inject edilir   | Custom path generator ayarı eklenir.                                                       |
| Legacy helper dosyası                                         | Stok dosyaysa silinir, custom ise korunur | `app/helpers.php` yerine `app/Helpers/custom.php` autoload düzeni kullanılır.              |
| Yeni migration'lar                                            | Kullanıcı onayıyla çalıştırılabilir       | Yeni eklenen stub dosyaları içinde migration varsa `migrate --force` sorulur.              |
| Hash kayıt dosyası                                            | Güncellenir                               | Güncellenen/eklenen stub dosyalarının yeni hash'leri saklanır.                             |

## `sk:update` Her Zaman Güncellenen Güvenli Yollar

| Yol                                       | Kısa açıklama                        |
| ----------------------------------------- | ------------------------------------ |
| `app/Domain/Shared/Actions/`              | Ortak base action sınıfları.         |
| `app/Domain/Shared/Contracts/`            | Ortak action/pipeline contract'ları. |
| `app/Domain/Shared/DTOs/`                 | Ortak base DTO sınıfları.            |
| `app/Domain/Shared/Pipelines/`            | Ortak action pipeline altyapısı.     |
| `app/Enums/PermissionEnum.php`            | Paket permission enum tanımı.        |
| `app/Http/Middleware/SecurityHeaders.php` | Güvenlik header middleware'i.        |
| `app/Http/Middleware/AssignTraceId.php`   | Request trace id middleware'i.       |
| `app/Http/Responses/ApiResponse.php`      | Standart API response builder.       |
| `app/Helpers/sk-helpers.php`              | Paket sahipli global helper'lar.     |
| `app/Traits/`                             | Paket sahipli trait'ler.             |
| `app/Exceptions/ApiExceptionHandler.php`  | API exception mapping handler'ı.     |

## `sk:update` İle Temizlenen Eski Yollar

| Yol                                      | Kısa açıklama                             |
| ---------------------------------------- | ----------------------------------------- |
| `app/Enums/EnumRegistry.php`             | Eski enum registry yapısı.                |
| `app/Enums/Contracts/HasDefinition.php`  | Eski enum definition contract'ı.          |
| `app/Enums/Attributes/InertiaShared.php` | Eski enum attribute yapısı.               |
| `app/Enums/Contracts/`                   | Boşsa kaldırılan eski contracts klasörü.  |
| `app/Enums/Attributes/`                  | Boşsa kaldırılan eski attributes klasörü. |
| `app/Enums/IdentityType.php`             | Eski identity enum'u.                     |
| `app/Enums/YesNo.php`                    | Eski yes/no enum'u.                       |
| `app/Traits/HasEnumAccessors.php`        | Eski enum accessor trait'i.               |
| `resources/js/composables/useEnum.ts`    | Eski enum composable'ı.                   |

## FileManager Özelinde Publish / Vendor Ayrımı

FileManager güncellemelerinde en çok dikkat edilmesi gereken yer burasıdır: backend tarafının çoğu kullanıcı projesine kopyalanır, Vue bileşen tarafı ise normal akışta vendor'dan çalışır.

### Kullanıcı Projesine Publish Edilen FileManager Dosyaları

Bu dosyalar `sk:install` ile uygulama içine kopyalanır ve `sk:update` sırasında hash takipli güncellenir. Kullanıcı bu dosyalarda değişiklik yaptıysa normal `sk:update` onları atlar.

| Hedef yol                                                                                    | Kaynak yol                                                                    | Kısa açıklama                                                                                            |
| -------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `app/Console/Commands/PurgeFileManagerTrash.php`                                             | `stubs/app/Console/Commands/PurgeFileManagerTrash.php`                        | Çöp kutusundaki eski dosyaları temizleyen scheduled command.                                             |
| `app/Domain/FileManager/Actions/`                                                            | `stubs/app/Domain/FileManager/Actions/`                                       | Upload, rename, copy, move, delete, restore, favorite, trash ve folder işlemlerinin backend aksiyonları. |
| `app/Domain/FileManager/DTOs/`                                                               | `stubs/app/Domain/FileManager/DTOs/`                                          | File item, folder ve context veri taşıyıcıları.                                                          |
| `app/Domain/FileManager/Queries/`                                                            | `stubs/app/Domain/FileManager/Queries/`                                       | Folder tree, folder contents, favorites ve trash listeleme query'leri.                                   |
| `app/Domain/FileManager/Services/FileManagerAuthorizer.php`                                  | `stubs/app/Domain/FileManager/Services/FileManagerAuthorizer.php`             | FileManager context bazlı okuma/yazma authorization kontrolü.                                            |
| `app/Domain/FileManager/Support/ContextDefinition.php`                                       | `stubs/app/Domain/FileManager/Support/ContextDefinition.php`                  | Tekil FileManager context tanımı.                                                                        |
| `app/Domain/FileManager/Support/ContextRegistry.php`                                         | `stubs/app/Domain/FileManager/Support/ContextRegistry.php`                    | `global`, `user` ve custom context çözümleme/izin mantığı.                                               |
| `app/Domain/Setting/DTOs/FileManagerSettingsDTO.php`                                         | `stubs/app/Domain/Setting/DTOs/FileManagerSettingsDTO.php`                    | Ayarlar ekranındaki File Manager ayar payload'u.                                                         |
| `app/Http/Controllers/FileManagerController.php`                                             | `stubs/app/Http/Controllers/FileManagerController.php`                        | FileManager endpoint'lerini action/query sınıflarına bağlayan controller.                                |
| `app/Http/Requests/FileManager/`                                                             | `stubs/app/Http/Requests/FileManager/`                                        | Upload, move, copy, rename, favorite, context ve bulk delete validation request'leri.                    |
| `app/Http/Requests/Admin/Settings/UpdateFileManagerSettingsRequest.php`                      | `stubs/app/Http/Requests/Admin/Settings/UpdateFileManagerSettingsRequest.php` | Admin settings File Manager tab validation request'i.                                                    |
| `app/Models/FileFolder.php`                                                                  | `stubs/app/Models/FileFolder.php`                                             | Sanal klasör modeli.                                                                                     |
| `app/Models/FileFavorite.php`                                                                | `stubs/app/Models/FileFavorite.php`                                           | Favori dosya/klasör modeli.                                                                              |
| `app/Models/GlobalFileBucket.php`                                                            | `stubs/app/Models/GlobalFileBucket.php`                                       | Global dosya context owner modeli.                                                                       |
| `app/Models/Media.php`                                                                       | `stubs/app/Models/Media.php`                                                  | Spatie MediaLibrary model override'ı ve folder/favorite ilişkileri.                                      |
| `app/Policies/FileFolderPolicy.php`                                                          | `stubs/app/Policies/FileFolderPolicy.php`                                     | Klasör authorization policy'si.                                                                          |
| `app/Support/MediaPathGenerator.php`                                                         | `stubs/app/Support/MediaPathGenerator.php`                                    | MediaLibrary dosya yolu üretici sınıfı.                                                                  |
| `app/Traits/HasMediaCollections.php`                                                         | `stubs/app/Traits/HasMediaCollections.php`                                    | Model media collection yardımcı trait'i.                                                                 |
| `database/migrations/*file_folders*`, `*file_favorites*`, `*media*`, `*global_file_buckets*` | `stubs/database/migrations/`                                                  | FileManager'ın tablo yapısı.                                                                             |
| `lang/en/sk-file-manager.php`, `lang/tr/sk-file-manager.php`                                 | `stubs/lang/*/sk-file-manager.php`                                            | Uygulama içi FileManager çeviri anahtarları.                                                             |
| `resources/js/pages/Admin/Files/Index.vue`                                                   | `stubs/resources/js/pages/Admin/Files/Index.vue`                              | Admin dosyalar sayfası; vendor bileşeni import eder.                                                     |
| `resources/js/pages/Admin/Settings/components/FileManagerTab.vue`                            | `stubs/resources/js/pages/Admin/Settings/components/FileManagerTab.vue`       | Settings ekranındaki File Manager ayar sekmesi.                                                          |
| `routes/web/file-manager-route.php`                                                          | `stubs/routes/web/file-manager-route.php`                                     | FileManager API-like web endpoint route'ları.                                                            |
| `routes/web/files-route.php`                                                                 | `stubs/routes/web/files-route.php`                                            | `/files` Inertia sayfa route'u.                                                                          |
| `routes/console.php`                                                                         | `stubs/routes/console.php`                                                    | `file-manager:purge-trash` schedule kaydı.                                                               |
| `config/settings.php`                                                                        | `stubs/config/settings.php`                                                   | File Manager ayar grubu varsayılanları.                                                                  |
| `config/filesystems.php`                                                                     | Inject                                                                        | `do` disk tanımı eksikse installer/update tarafından eklenir.                                            |
| `config/media-library.php`                                                                   | Vendor config + inject                                                        | `MediaPathGenerator` ayarı eksikse eklenir.                                                              |

### Normalde Vendor'dan Çalışan FileManager Dosyaları

Bu dosyalar `sk:install` ile uygulama içine kopyalanmaz. `vite.config.ts` içindeki `@lvntr` alias'ı ve component auto-import ayarı sayesinde `vendor/lvntr/laravel-starter-kit/resources/js/...` üzerinden kullanılır.

| Vendor yolu                                                                                          | Nasıl kullanılır?                                                                                          | Kısa açıklama                                                                     |
| ---------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| `vendor/lvntr/laravel-starter-kit/resources/js/components/FileManager/FileManager.vue`               | `resources/js/pages/Admin/Files/Index.vue` içinde `@lvntr/components/FileManager/FileManager.vue` import'u | Ana FileManager Vue arayüzü.                                                      |
| `vendor/lvntr/laravel-starter-kit/resources/js/components/FileManager/components/`                   | `FileManager.vue` iç import'ları                                                                           | Sidebar, folder tree, file grid, breadcrumb, stats ve details dialog bileşenleri. |
| `vendor/lvntr/laravel-starter-kit/resources/js/components/FileManager/composables/useFileManager.ts` | FileManager ve editor bileşenleri tarafından import edilir                                                 | Frontend state, endpoint çağrıları, seçim, upload ve trash işlemleri.             |
| `vendor/lvntr/laravel-starter-kit/resources/js/components/FileManager/types.ts`                      | `@lvntr/components/FileManager/types`                                                                      | File/folder/context TypeScript tipleri.                                           |
| `vendor/lvntr/laravel-starter-kit/resources/js/components/FileManager/assets/folder.svg`             | FileManager bileşenleri tarafından kullanılır                                                              | Klasör görsel varlığı.                                                            |
| `vendor/lvntr/laravel-starter-kit/resources/js/components/FormBuilder/inputs/EditorInput.vue`        | Editor image upload için `/file-manager/files` endpoint'ini çağırır                                        | FileManager backend endpoint'ine bağımlı vendor bileşeni.                         |
| `vendor/lvntr/laravel-starter-kit/resources/js/components/FormBuilder/inputs/EditorImagePicker.vue`  | `useFileManager` import eder                                                                               | Editor içinde FileManager seçici mantığı.                                         |

### İsteğe Bağlı Publish Edilebilen FileManager Frontend Dosyaları

Varsayılan akışta vendor'dan çalışan bu Vue dosyaları, özelleştirme gerektiğinde proje içine alınabilir.

| Komut                                             | Hedef                                        | Etki                                                                        |
| ------------------------------------------------- | -------------------------------------------- | --------------------------------------------------------------------------- |
| `php artisan sk:publish --tag=components`         | `resources/js/components/Lvntr-Starter-Kit/` | Tüm starter kit Vue bileşenlerini, FileManager dahil, proje içine kopyalar. |
| `php artisan sk:publish --tag=components --force` | `resources/js/components/Lvntr-Starter-Kit/` | Daha önce yayınlanmış bileşenlerin üzerine yazar.                           |

> Pratik sonuç: FileManager backend bug fix'leri çoğu zaman `sk:update` ile uygulama dosyalarına taşınmak zorundadır. FileManager Vue UI bug fix'leri ise bileşenleri publish etmediyseniz `composer update` sonrası vendor'dan otomatik gelir; publish ettiyseniz artık sizin kopyanız çalışır ve manuel güncelleme gerekir.

## İsteğe Bağlı `sk:publish` Aktarımları

Bu komut kurulum/güncelleme akışının zorunlu parçası değildir; proje seviyesinde özelleştirme gerektiğinde kullanılır.

| Tag          | Kaynak                                      | Hedef                                                         | Kısa açıklama                                                        |
| ------------ | ------------------------------------------- | ------------------------------------------------------------- | -------------------------------------------------------------------- |
| `config`     | `config/starter-kit.php`                    | `config/starter-kit.php`                                      | Paket config'ini proje seviyesine yayınlar.                          |
| `lang`       | `resources/lang/`                           | `lang/vendor/starter-kit/`                                    | Paket içi çevirileri override etmek için yayınlar.                   |
| `components` | `resources/js/components/`                  | `resources/js/components/Lvntr-Starter-Kit/`                  | Tüm Lvntr Starter Kit Vue bileşenlerini özelleştirmek için yayınlar. |
| `datatable`  | `resources/js/components/DatatableBuilder/` | `resources/js/components/Lvntr-Starter-Kit/DatatableBuilder/` | Sadece DatatableBuilder bileşenlerini yayınlar.                      |
| `form`       | `resources/js/components/FormBuilder/`      | `resources/js/components/Lvntr-Starter-Kit/FormBuilder/`      | Sadece FormBuilder bileşenlerini yayınlar.                           |
| `tabs`       | `resources/js/components/TabBuilder/`       | `resources/js/components/Lvntr-Starter-Kit/TabBuilder/`       | Sadece TabBuilder bileşenlerini yayınlar.                            |
| `skeleton`   | `resources/js/components/Skeleton/`         | `resources/js/components/Lvntr-Starter-Kit/Skeleton/`         | Sadece skeleton/loading bileşenlerini yayınlar.                      |
| `ui`         | `resources/js/components/ui/`               | `resources/js/components/Lvntr-Starter-Kit/ui/`               | Dialog, toast, avatar, tag ve benzeri UI bileşenlerini yayınlar.     |
| `helpers`    | `src/sk-helpers.php`                        | `app/Helpers/sk-helpers.php`                                  | Paket global helper dosyasını proje seviyesine yayınlar.             |

## Pratik Okuma

| Senaryo                                                    | Hangi komut çalışır?                                             | Dosya etkisi                                                    |
| ---------------------------------------------------------- | ---------------------------------------------------------------- | --------------------------------------------------------------- |
| Paketi ilk kez projeye ekleme                              | `composer require`                                               | Sadece `vendor/` güncellenir.                                   |
| Starter kit'i uygulamaya kurma                             | `php artisan sk:install`                                         | Stub dosyaları ve config inject'leri uygulama köküne aktarılır. |
| Paket sürümünü güncelleme                                  | `composer update lvntr/laravel-starter-kit`                      | Sadece `vendor/` altındaki paket güncellenir.                   |
| Yeni sürümdeki uygulama stub değişikliklerini alma         | `php artisan sk:update`                                          | Uygulama dosyaları hash takipli olarak güncellenir/eklenir.     |
| Yerel özelleştirilmiş dosyaları paket haline geri döndürme | `php artisan sk:update --force` veya seçili `sk:publish --force` | İlgili dosyalar paket versiyonuyla ezilir.                      |
