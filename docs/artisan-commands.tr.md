# Artisan Komutları

Bu döküman starter kit için komut referansıdır. DDD ile ilgili mimari notlar ayrı olarak [ddd.tr.md](./ddd.tr.md) içinde tutulur.

## Son Kullanıcı Komutları

| Komut                                     | Amaç                                                             |
| ----------------------------------------- | ---------------------------------------------------------------- |
| `php artisan sk:doctor`                   | Ortam sağlık kontrollerini çalıştırır ve sorunları raporlar      |
| `php artisan sk:install`                  | Starter kit'i projeye kurar                                      |
| `php artisan sk:update`                   | Kurulu kit dosyalarını güvenli şekilde günceller                 |
| `php artisan sk:upgrade`                  | Eski starter-kit/Laravel ana sürümünü güncel hatta yükseltir     |
| `php artisan sk:publish`                  | İsteğe bağlı bileşenleri, dil dosyalarını veya config'i yayınlar |
| `php artisan sk:eject`                    | Vendor'da çalışan bir domain'i tam özelleştirme için uygulamaya çıkarır |
| `php artisan make:sk-domain`              | Yeni bir domain iskeleti üretir                                  |
| `php artisan remove:sk-domain`            | Üretilmiş bir domain'i kaldırır                                  |
| `php artisan env:sync`                    | `.env` anahtarlarını `.env.example` içine senkronize eder        |
| `php artisan env:sync --reverse`          | `.env` içinde eksik kalan anahtarları kontrol eder               |
| `php artisan site:install`                | Lokal/dev kullanım için site verisini sıfırlayıp yeniden kurar   |
| `php artisan sk:seed-permissions --fresh` | Rol ve yetki verilerini config'ten yeniden üretir                |
| `php artisan postman:sync`                | Scramble OpenAPI spec'ini Postman'a gönderir                     |
| `php artisan apidog:sync`                 | Scramble OpenAPI spec'ini Apidog'a gönderir                      |
| `php artisan file-manager:purge-trash`    | Eski Dosya Yöneticisi çöpünü kalıcı olarak siler                 |

## `sk:doctor`

Bir dizi ortam sağlık kontrolü çalıştırır ve her birinin sonucunu raporlar.

```bash
php artisan sk:doctor
php artisan sk:doctor --json
php artisan sk:doctor --only=database,redis
```

Kapsanan kontroller: PHP extension'ları, veritabanı bağlantısı, Redis, Passport anahtarları, storage symlink, yazılabilir dizinler, queue driver, schedule çalışması, mail driver, npm build artifact'ları, config cache ve FileManager disk bağlantısı.

- `--json` tablo yerine makine okunabilir JSON çıktı üretir
- `--only=<kontroller>` virgülle ayrılmış seçili kontrolleri çalıştırır (örn. `--only=database,redis`)

Çıkış kodları:

| Kod | Anlam                                   |
| --- | --------------------------------------- |
| `0` | Tüm kontroller başarılı                 |
| `1` | En az bir kontrol WARN döndürdü         |
| `2` | En az bir kontrol FAIL döndürdü         |

## `sk:install`

İlk kurulumda kullanılır.

```bash
php artisan sk:install
php artisan sk:install --force
php artisan sk:install --no-interaction
php artisan sk:install --without-ai-skill
php artisan sk:install --without-eject
```

- `--force` mevcut yayınlanabilir dosyaların üzerine yazar
- `--no-interaction` tüm varsayılanları otomatik kabul eder; CI veya script tabanlı kurulumlar için uygundur
- `--without-ai-skill` Lvntr Starter Kit AI skill'lerinin yayınlanmasını tamamen atlar — hem Claude Code kopyaları (`.claude/skills/`) hem de Codex aynası (`.codex/skills/`). Kit'in skill bundle'ını ne Claude Code ne Codex ile kullanan consumer'lar için
- `--without-eject` ilk kurulumda varsayılan `User` ve `Role` domain eject'ini atlar; runtime vendor'da kalır ve `class_alias` ile çözülür. Bu flag'i atlarsanız `app/Domain/User/` ve `app/Domain/Role/` otomatik oluşturulur. Sahiplik takası için [install.tr.md](./install.tr.md) belgesine bakın.

## `sk:update`

`composer update` sonrasında kullanılır.

```bash
php artisan sk:update
php artisan sk:update --dry-run
php artisan sk:update --force
php artisan sk:update --without-ai-skill
```

- `--without-ai-skill` bu çalışmada `.codex/skills/` AI-skill aynasının yeniden üretilmesini atlar. (Kurulum sırasındaki `--without-ai-skill` tercihi otomatik korunur — atlanan skill'ler asla yeniden eklenmez.)

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
php artisan sk:publish --tag=filemanager
php artisan sk:publish --tag=composables
php artisan sk:publish --tag=plugins
php artisan sk:publish --tag=lang
php artisan sk:publish --tag=config
php artisan sk:publish --tag=helpers
```

## `sk:eject`

Runtime'ı vendor paketinden çalışan bir domain'i tamamen özelleştirmek istediğinizde kullanılır. Eject, domain'in backend sınıflarını `app/Domain/{Name}/` altına kopyalar, namespace'lerini `App\Domain\{Name}\` olarak yeniden yazar, domain'e ait Vue sayfalarını tazeler ve event/listener binding'lerini `app/Providers/DomainServiceProvider.php` dosyasına ekleyerek audit log'un kesintisiz çalışmasını sağlar. Önce `--dry-run` ile neyin değişeceğini önizleyin.

`--force`, `--dry-run` veya `--no-interaction` verilmediği sürece komut, herhangi bir işlem yapmadan önce onay ister — eject tek yönlü bir takastır (domain, `composer update` ile kit runtime güncellemesi almayı bırakır). `sk:install`'in kendi dahili varsayılan-domain eject'i her zaman `--force` geçer; bu yüzden taze kurulum akışı bu onay istemiyle kesintiye uğramaz.

```bash
php artisan sk:eject User
php artisan sk:eject User --dry-run
php artisan sk:eject User --force
php artisan sk:eject User --no-vue
php artisan sk:eject Role --destination=/tmp/eject-preview
php artisan sk:eject ApiClient          # controller + request + resource (ApiClient + ApiToken)
php artisan sk:eject ContentLanguage    # domain + controller + request + resource
```

- `--dry-run` dosya yazmadan kopyalama/yeniden yazma/enjeksiyon planını ekranda gösterir. Her zaman önce bunu çalıştırın.
- `--force` zaten var olan dosyaların üzerine yazar — hem backend `app/Domain/{Name}/` ağacı hem de domain'in Vue sayfaları. **`--force` olmadan eject hiçbir mevcut dosyayı ezmez:** zaten var olan bir `app/Domain/{Name}/` komutu erken sonlandırır ve zaten var olan her Vue sayfası olduğu gibi bırakılıp korunan olarak raporlanır — yalnızca eksik sayfalar yazılır. Bu, `sk:install` ile gelen sayfalarda yaptığınız düzenlemeleri korur.
- `--no-vue` domain'e ait Vue sayfalarını tazelemez; yalnızca backend sınıfları eject edilir.
- `--destination=<yol>` çıktıyı uygulama köküne yazmak yerine belirtilen dizine yönlendirir. İzole test amacıyla kullanılır.
- `--skip-autoload` eject sonundaki `composer dump-autoload` çağrısını atlar. Yalnızca çağıran süreç (örneğin `sk:install`) dump'ı kendisi yapacaksa kullanın. Bu flag olmadan eject her zaman autoload'u yeniler; yenileme başarısız olursa sıfırdan farklı kod döner.

> **Çıkış kodu:** Composer'ın autoload yenilemesi başarısız olursa (örn. `composer` yok ya da hata verir), komut hatayı yazar ve dosyalar kopyalanmış olsa bile **sıfırdan farklı kod ile çıkar** — böylece CI ve scriptler bozuk autoload'ı başarılı eject sanmaz. Elle `composer dump-autoload` çalıştırıp tekrar doğrulayın.

### Eject edilebilir domain'ler

On dört domain eject edilebilir. Bu listede yer almayan domain'ler zaten uygulama sahipli olduğundan eject gerektirmez.

| Domain            | Backend sınıflar      | Vue sayfaları | Eject edilen HTTP katmanı   | Enjekte edilen event binding'ler |
| ----------------- | --------------------- | ------------- | --------------------------- | -------------------------------- |
| `User`            | evet                  | evet          | —                           | 3 (Created/Updated/Deleted)      |
| `Role`            | evet                  | evet          | —                           | 3 (Created/Updated/Deleted)      |
| `Setting`         | evet                  | evet          | controller + request'ler    | —                                |
| `Logs`            | evet                  | evet          | controller + request'ler    | 1 (FilesDeleted)                 |
| `ActivityLog`     | evet                  | evet          | controller                  | —                                |
| `ApiClient`       | evet                  | —             | ApiClient + ApiToken controller'ları + request'ler + resource'lar | — |
| `ApiRoute`        | evet                  | evet          | controller                  | —                                |
| `ContentLanguage` | evet                  | —             | controller + request'ler + resource | —                        |
| `SystemHealth`    | hayır (yalnızca controller) | —       | controller                  | —                                |
| `Definitions`     | hayır (yalnızca controller) | —       | API + Service controller'ları | —                              |
| `MediaUpload`     | hayır (yalnızca controller) | —       | controller                  | —                                |
| `Files`           | hayır (yalnızca Vue)  | evet          | —                           | —                                |
| `Session`         | evet                  | —             | —                           | —                                |
| `Media`           | evet                  | —             | —                           | —                                |

**`ApiClient` API-token akışını da eject eder:** ApiClient domain'i hem OAuth client'ı hem de kişisel erişim token'ı action'larına sahiptir; bu yüzden `sk:eject ApiClient` hem `ApiClientController`'ı hem `ApiTokenController`'ı (FormRequest'leri ve API Resource'larıyla birlikte) kopyalar ve `api-client-route.php` ile `api-token-route.php` import'larını yeniden yazar. Tek seferlik Passport client-secret gösterimi birebir korunur — eject dosyayı taşır, davranışı değiştirmez.

**`SystemHealth`, `Definitions` ve `MediaUpload` yalnızca controller'dır:** bunların `app/Domain/{Name}` backend ağacı yoktur. `SystemHealth` Artisan + `Gate`'i doğrudan controller'ından sürer; `Definitions` hem `Api\DefinitionController` hem `Service\DefinitionServiceController`'ı eject eder (ikisi de vendor `DefinitionService`'i sarar — servis vendor'da kalır); `MediaUpload` `media.destroy` route'u paylaşılan `routes/web.php`'de olan `Api\MediaUploadController`'ı eject eder. Hiçbiri FormRequest ya da `app/Domain` klasörü taşımaz; dolayısıyla bir controller kopyalanmadıkça autoload'u etkileyen sınıf eklenmez.

**Model'ler uygulama sahipli kalır — eject hiçbir Model'i taşımaz.** `App\Models\{ContentLanguage,Media,Definition,...}` uygulamanızda publish kalır ve asla vendor'a alias'lanmaz (bir `App\Models\X` alias'ı Laravel'in `XPolicy` keşfini ve route-model binding'ini bozardı). Vendor controller/domain'ler bu modellere `App\` FQCN ile başvurur; eject edilen bir `app/Domain/ContentLanguage` da o `App\Models\ContentLanguage` referansını değiştirmeden korur.

**Auth ve Helper'lar neden eject edilemiyor:** Auth ekranları zaten %100 uygulama sahipli — `sk:update` onları güncel tutar, eject gerekmez. `sk-helpers.php` global helper'ları tek bir override edilebilir dosya olarak gelir; ihtiyaç duyulmayan kısımlar silinir.

**`Files` yalnızca Vue'dur:** `Files` eject ettikten sonra FileManager backend'i (controller, FormRequests, route-registry altyapısı) vendor'da yönetilmeye devam eder. Yalnızca admin Vue sayfaları (`resources/js/pages/Admin/Files/`) uygulamanıza kopyalanır; bu sayede kullanıcı arayüzü özelleştirilebilirken backend kit güncellemelerini almayı sürdürür. Geri almak için kopyalanan `resources/js/pages/Admin/Files/` dizinini silin — `app.ts` fallback mekanizması vendor sayfaları devreye sokar.

### Namespace yeniden yazımının kapsamı

Yalnızca eject edilen domain'in kendi namespace'i yeniden yazılır. Diğer tüm vendor referansları olduğu gibi kalır:

- `Lvntr\StarterKit\Domain\User\Actions\CreateUserAction` → `App\Domain\User\Actions\CreateUserAction`
- `use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;` — **değişmez** (`Shared` base sınıfları vendor'da kalır)
- `Lvntr\StarterKit\Http\Responses\ApiResponse` — **değişmez**
- Eject edilmeyen diğer domain'ler — **değişmez**

### Güncelleme-kaybı takası

> **Uyarı:** Bir domain'i eject ettikten sonra, o domain'in vendor runtime'ını etkileyen güvenlik veya hata düzeltmelerini içeren `composer update` sürümleri kendi kopyanıza uygulanmaz. Dosyalar size ait olur — upstream değişikliklerini kendiniz uygulamanız gerekir.

`sk:update`, `app/Domain/` altındaki backend dosyalara hiç dokunmaz (bunlar hash-tracked stub değildir). `--force` ile eject edilen Vue sayfaları normal hash-tracking kurallarına tabidir: düzenlediğinizde `sk:update` onları "özelleştirilmiş" olarak işaretler ve güncellemeyi atlar.

### Eject'i geri alma (v1: manuel)

`--revert` bayrağı gelecekteki bir sürüm için planlanmaktadır. Manuel geri alma adımları:

1. `app/Domain/{Name}/` klasörünü silin.
2. `app/Providers/DomainServiceProvider.php` içinden o domain'e ait `Event::listen(...)` satırlarını kaldırın.
3. `composer dump-autoload` çalıştırın.

`StarterKitServiceProvider` içindeki `class_alias` tanımları, `App\Domain\{Name}\*` importlarını otomatik olarak tekrar vendor kopyasına yönlendirir.

## `make:sk-domain`

Starter kit yapısına uygun yeni bir domain oluşturur.

```bash
# Sadece domain (geriye dönük uyumlu)
php artisan make:sk-domain Article

# Namespace'li
php artisan make:sk-domain Store/Product

# Temel seçenekler
php artisan make:sk-domain Product --admin --api --events --fields="name:string,price:decimal"
php artisan make:sk-domain Product --from-migration=2026_03_21_create_products_table.php

# Opt-in ekstralar — tekil flag'ler
php artisan make:sk-domain Article --with-policy --with-factory

# Opt-in ekstralar — toplu syntax
php artisan make:sk-domain Article --with=policy,factory,test

# İlişki scaffold'ı
php artisan make:sk-domain Article --with-relations --relations="belongsTo:User,hasMany:Comment"

# Tam
php artisan make:sk-domain Article --with=policy,factory,seeder,test,relations --relations="belongsTo:User,morphTo:commentable"
```

Opt-in flag'ler (v2):

| Flag | Ne üretir |
| ---- | --------- |
| `--with-policy` | Policy sınıfı |
| `--with-factory` | Factory |
| `--with-seeder` | Seeder |
| `--with-test` | Feature test |
| `--with-relations` | İlişki scaffold'ı (`--relations` ile birlikte kullanılır) |
| `--with=policy,factory,test` | Toplu syntax — birden fazla opt-in tek flag'de |
| `--relations="belongsTo:User,hasMany:Comment,morphTo:commentable"` | Scaffold için ilişki tanımları |

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
