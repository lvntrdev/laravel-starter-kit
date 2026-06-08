# DDD

Bu döküman, starter kit'in kullandığı domain odaklı yapıyı açıklar. Controller merkezli değil domain merkezli bir DDD düzeni uygulanır.

## Amaç

Temel amaç, iş kurallarını büyüyen controller dosyalarından çıkarıp öngörülebilir domain klasörlerine taşımaktır.

## Domain Yapısı

Kurulumdan sonra tipik yapı şöyledir:

```text
app/Domain/
├── Auth/
├── Role/
│   └── BulkActions/
└── User/
    └── BulkActions/
```

Tamamen paket tarafından yönetilen ve vendor'dan çalışan domain'ler, fresh install'da `app/`'e scaffold edilmez. Ayrıntılar için aşağıdaki [Vendor-resident domain'ler](#vendor-resident-domainler) bölümüne bakın.

### Vendor-resident domain'ler

Aşağıdaki domain'lerin **runtime katmanı** (Actions, DTOs, Queries, Events, Listeners, Services) paket içinde yer alır (`src/Domain/`, `Lvntr\StarterKit\Domain\`). Kurulumda uygulamanıza kopyalanmaz.

| Domain | Vendor namespace |
|---|---|
| `FileManager` | `Lvntr\StarterKit\Domain\FileManager\` |
| `Shared` | `Lvntr\StarterKit\Domain\Shared\` |
| `ActivityLog` | `Lvntr\StarterKit\Domain\ActivityLog\` |
| `Logs` | `Lvntr\StarterKit\Domain\Logs\` |
| `Session` | `Lvntr\StarterKit\Domain\Session\` |
| `Media` | `Lvntr\StarterKit\Domain\Media\` |
| `ApiClient` | `Lvntr\StarterKit\Domain\ApiClient\` |
| `ApiRoute` | `Lvntr\StarterKit\Domain\ApiRoute\` |
| `Role` | `Lvntr\StarterKit\Domain\Role\` |
| `Setting` | `Lvntr\StarterKit\Domain\Setting\` |
| `User` | `Lvntr\StarterKit\Domain\User\` |

**Import uyumluluğu:** `App\Domain\<Module>\...` import yollarını kullanan controller ve provider'lar çalışmaya devam eder — `StarterKitServiceProvider`, bunları vendor namespace'ine çözen `class_alias` girişlerini kaydeder. Yerel `app/Domain/<Module>/` kopyası varsa her zaman öncelik alır (dosya diskte varken guard alias'ı atlar).

**Tüketici yüzeyi `app/`'te kalır:** Kullanıcıya dönük modüllerde Controller'lar, FormRequest'ler, Model'ler, Vue sayfaları ve route dosyaları hâlâ uygulamanıza scaffold edilir. Yalnızca runtime / iş-mantığı katmanı vendor tarafından yönetilir.

**Mevcut uygulama kopyaları:** projeniz bir domain vendor'a taşınmadan önce kurulduysa mevcut `app/Domain/<Module>/` dosyalarınız korunur ve çalışmaya devam eder. Bunları silmek isteğe bağlıdır — reconcile adımları için [UPGRADE.md](./UPGRADE.tr.md) belgelerine bakın.

Bir domain içinde genelde şu katmanlar bulunur:

- yazma işlemleri ve use-case akışı için `Actions`
- doğrulanmış veriyi taşımak için `DTOs`
- listeleme ve datatable sorgu mantığı için `Queries`
- domain olayları için `Events`
- loglama gibi yan etkiler için `Listeners`
- soyutlama gerektiğinde `Repositories` veya `Contracts`

## Request Akışı

Tipik akış:

1. Controller isteği alır.
2. Form Request doğrulama yapar.
3. DTO veriyi normalize eder.
4. Action iş kuralını çalıştırır.
5. Gerekirse Event fırlatılır.
6. Listener controller'ı şişirmeden yan etkileri işler.
7. Yanıt `to_api()` ya da Inertia redirect ile döner.

## Temel Kurallar

- controller'ları ince tutun
- doğrulamayı Form Request içinde tutun
- karmaşık yazma işlemlerini Action içine alın
- tekrar kullanılan liste mantığını Query içinde tutun
- yan etkileri Listener katmanına taşıyın
- kit seviyesindeki domainler arası ortak kodu `src/Domain/Shared` altında tutun; `app/Domain/Shared` yolunu yalnızca proje sahipli veya eject edilmiş kod için kullanın

## Neden Faydalı

- büyük admin projelerinde okunabilirliği artırır
- iş kurallarını test etmeyi kolaylaştırır
- zaman içinde refactor sürecini güvenli hale getirir
- web ve API controller'ları arasında tekrarları azaltır

## İlgili Komutlar

Domain yapısı scaffolding komutlarıyla desteklenir, ancak komut referansı [artisan-commands.tr.md](./artisan-commands.tr.md) içinde tutulur. Bu dosya özellikle DDD anlatımını komut dökümanından ayrı tutmak için vardır.

### `make:sk-domain` v2 opt-in flag'leri

Komutun flag'siz çağrılması v13.5.x davranışını korur (geriye dönük uyumlu).

**Tek tek flag'ler:**

| Flag | Üretilen dosya |
| --- | --- |
| `--with-policy` | Policy sınıfı |
| `--with-factory` | Factory |
| `--with-seeder` | Seeder |
| `--with-test` | Feature test |
| `--with-relations` | İlişki scaffold'ı (`--relations` ile birlikte kullanılır) |

**Toplu syntax** — birden fazla opt-in'i tek flag ile:

```bash
php artisan make:sk-domain Article --with=policy,factory,test
```

**İlişki syntax'ı:**

```bash
php artisan make:sk-domain Article --with-relations --relations="belongsTo:User,hasMany:Comment,morphTo:commentable"
```

Desteklenen ilişki türleri: `belongsTo`, `hasMany`, `hasOne`, `belongsToMany`, `morphTo`, `morphMany`.

**Örnekler:**

```bash
# Sadece domain — v13.5.x davranışı, geriye dönük uyumlu
php artisan make:sk-domain Article

# Policy ve factory ile
php artisan make:sk-domain Article --with-policy --with-factory

# Toplu syntax
php artisan make:sk-domain Article --with=policy,factory,test

# İlişkilerle
php artisan make:sk-domain Article --with-relations --relations="belongsTo:User,hasMany:Comment"

# Tam
php artisan make:sk-domain Article --with=policy,factory,seeder,test,relations --relations="belongsTo:User,morphTo:commentable"
```
