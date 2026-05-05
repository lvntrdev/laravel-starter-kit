# Artisan Komutları

Bu döküman starter kit için komut referansıdır. DDD ile ilgili mimari notlar ayrı olarak [ddd.tr.md](./ddd.tr.md) içinde tutulur.

## Son Kullanıcı Komutları

| Komut                                     | Amaç                                                             |
| ----------------------------------------- | ---------------------------------------------------------------- |
| `php artisan sk:install`                  | Starter kit'i projeye kurar                                      |
| `php artisan sk:update`                   | Kurulu kit dosyalarını güvenli şekilde günceller                 |
| `php artisan sk:upgrade`                  | Eski starter-kit/Laravel ana sürümünü güncel hatta yükseltir     |
| `php artisan sk:publish`                  | İsteğe bağlı bileşenleri, dil dosyalarını veya config'i yayınlar |
| `php artisan make:sk-domain`              | Yeni bir domain iskeleti üretir                                  |
| `php artisan remove:sk-domain`            | Üretilmiş bir domain'i kaldırır                                  |
| `php artisan env:sync`                    | `.env` anahtarlarını `.env.example` içine senkronize eder        |
| `php artisan env:sync --reverse`          | `.env` içinde eksik kalan anahtarları kontrol eder               |
| `php artisan site:install`                | Lokal/dev kullanım için site verisini sıfırlayıp yeniden kurar   |
| `php artisan sk:seed-permissions --fresh` | Rol ve yetki verilerini config'ten yeniden üretir                |
| `php artisan postman:sync`                | Scramble OpenAPI spec'ini Postman'a gönderir                     |
| `php artisan apidog:sync`                 | Scramble OpenAPI spec'ini Apidog'a gönderir                      |
| `php artisan file-manager:purge-trash`    | Eski Dosya Yöneticisi çöpünü kalıcı olarak siler                 |

## `sk:install`

İlk kurulumda kullanılır.

```bash
php artisan sk:install
php artisan sk:install --force
php artisan sk:install --no-interaction
```

## `sk:update`

`composer update` sonrasında kullanılır.

```bash
php artisan sk:update
php artisan sk:update --dry-run
php artisan sk:update --force
```

## `sk:upgrade`

Laravel 12 -> 13 gibi starter-kit veya Laravel major geçişlerinde kullanılır.

```bash
php artisan sk:upgrade
php artisan sk:upgrade --force
php artisan sk:upgrade --skip-build
```

## `sk:publish`

Bunu yalnızca paket varlıklarının proje sahipli kopyalarına ihtiyacınız varsa kullanın.

```bash
php artisan sk:publish
php artisan sk:publish --tag=components
php artisan sk:publish --tag=datatable
php artisan sk:publish --tag=form
php artisan sk:publish --tag=tabs
php artisan sk:publish --tag=skeleton
php artisan sk:publish --tag=ui
php artisan sk:publish --tag=lang
php artisan sk:publish --tag=config
```

## `make:sk-domain`

Starter kit yapısına uygun yeni bir domain oluşturur.

```bash
php artisan make:sk-domain Product
php artisan make:sk-domain Store/Product
php artisan make:sk-domain Product --admin --api --events --fields="name:string,price:decimal"
php artisan make:sk-domain Product --from-migration=2026_03_21_create_products_table.php
```

Action, DTO, Query, Request, Route ve Vue ekranı gibi paket konvansiyonlarını hızlıca kurmak istediğinizde kullanın.

## `remove:sk-domain`

Üretilmiş bir domain'i ve ilişkili dosyalarını kaldırır.

```bash
php artisan remove:sk-domain Product
```

## `env:sync`

`.env.example` dosyasını projenin `.env` anahtarlarıyla uyumlu tutar.

```bash
php artisan env:sync
php artisan env:sync --reverse
```

`--reverse` güvenli bir kontrol modudur; dosya yazmaz, yalnızca `.env.example` içinde olup `.env` içinde eksik kalan anahtarları raporlar.

## `site:install`

Lokal geliştirmede temiz kurulum akışını tekrar çalıştırmak istediğinizde faydalıdır.

```bash
php artisan site:install
```

Komut onaydan önce hedef environment ve veritabanını gösterir, yalnızca `local` ve `setup` ortamlarında çalışır, production'a benzeyen environment adlarında ise kalıcı olarak engellenir.

v13.4.1 itibarıyla akış `passport:keys` ile varsayılan admin seed'i arasında `passport:client --personal --provider=users` adımını da koşturur; böylece sıfır kurulum sonrası kişisel access token üretebilecek çalışan bir yolunuz hazır olur, ek bir manuel adım gerekmez.

## `postman:sync`

Scramble tarafından üretilen OpenAPI spec'ini Postman'a iterek workspace koleksiyonunuzun güncel API yüzeyiyle senkron kalmasını sağlar.

```bash
php artisan postman:sync
```

`postman` ayar grubunu okur: `postman.api_key` ve `postman.workspace_id` zorunludur, başarılı gönderim sonrasında `postman.collection_id` Postman'dan dönen id ile güncellenir. Anahtar veya workspace id eksikse komut anlaşılır bir hata ile hemen durur — değerleri admin panelinde **Settings → API Clients → Postman** altından (ya da doğrudan ilgili satırları ekleyerek) doldurup komutu tekrar koşturun. Komut perde arkasında `App\Domain\ApiRoute\Actions\SyncPostmanAction` sınıfına delege edilir; ortak `OpenApiExporter` helper'ı `scramble:export` komutunu `storage/app/postman/` altında her çağrıda benzersiz bir geçici dosyaya yazar ve spec'i **değiştirmeden** Postman'e gönderir. Action önce taze koleksiyonu import eder, yeni UID'yi ayarlara yazar, sonra eski koleksiyonu best-effort siler — başarısız bir push mevcut çalışan koleksiyonu kaybetmez.

## `apidog:sync`

Aynı Scramble OpenAPI spec'ini, koleksiyonu Apidog üzerinde yansılayan ekipler için Apidog'a gönderir.

```bash
php artisan apidog:sync
```

`apidog` ayar grubunu okur: `apidog.access_token` ve `apidog.project_id` zorunludur. Değerlerden biri eksikse komut "not configured" hatası verip durur — değerleri **Settings → API Clients → Apidog** altından (ya da doğrudan ilgili satırları ekleyerek) doldurup komutu tekrar koşturun. Asıl iş `App\Domain\ApiRoute\Actions\SyncApidogAction` içinde yapılır ve `postman:sync` ile aynı `OpenApiExporter` helper'ını paylaşır — spec Apidog'a **değiştirilmeden** gönderilir, bu sayede push edilen proje gerçek sunucu kontratını aynen yansıtır.

## `file-manager:purge-trash`

Belirlenen yaştan eski soft-delete edilmiş Dosya Yöneticisi öğelerini kalıcı olarak siler.

```bash
php artisan file-manager:purge-trash
php artisan file-manager:purge-trash --days=30
```

Varsayılan süre 7 gündür. Komut yalnız File Manager media kayıtlarını (`collection_name = files`) ve çöpteki klasörleri hedefler; avatar, logo, editor upload veya diğer MediaLibrary collection'larına dokunmaz. Paketle gelen `routes/console.php` komutu günlük schedule eder.
