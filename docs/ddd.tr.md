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

**Yüzey sahipliği modül bazında ayrılır.** Model'ler her zaman app-owned kalır (policy discovery ve route-model binding çalışmaya devam etsin diye). **Kullanıcıya dönük** modüllerde (`User`, `Role`, Dashboard, Auth, Profile) Controller'lar, FormRequest'ler, Vue sayfaları ve route dosyaları uygulamanıza scaffold edilir. **Vendor-first davranış** modüllerinde (Files, Logs, Activity Logs, API Routes, Settings, …) HTTP + Vue yüzeyinin tamamı paketten çalışır — yalnızca Model'leri `app/`'te yaşar; sahiplenmek için `sk:eject <Module>` çalıştırın. Modül Sahipliği tablosu için [README](../README.md)'ye bakın.

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

### `make:sk-domain` temel flag'leri

Domain adı ve `--fields=` dışında, sihirbazın katman/ID/Vue seçimlerinin tamamı non-interaktif olarak geçilebilir:

| Flag | Ne yapar |
| --- | --- |
| `--fields="name:string,age:integer"` | Virgülle ayrılmış `alan:tip` çiftleri. Mevcut tipler: `string`, `integer`, `bigInteger`, `unsignedBigInteger`, `float`, `decimal`, `boolean`, `text`, `longText`, `json`, `date`, `dateTime`, `timestamp`. Atlanırsa alan alan interaktif sorulur. |
| `--id-type=id\|uuid\|ulid` | Primary key stratejisi. `id` (varsayılan) auto-increment bigint'tir; `uuid`/`ulid` model'e ilgili `HasUuids`/`HasUlids` concern'ini ekler ve migration'daki `id` kolonunu değiştirir. Atlanırsa interaktif sorulur — `--from-migration` kullanıldığında tamamen atlanır (migration dosyasından tespit edilir). |
| `--api` / `--no-api` | API controller + route'ları zorla üretir veya zorla atlar. İkisi de verilmezse (varsayılan: evet) sorulur. |
| `--admin` / `--no-admin` | Admin controller + route'ları zorla üretir veya zorla atlar. İkisi de verilmezse (varsayılan: evet) sorulur. |
| `--events` / `--no-events` | Created/Updated/Deleted event'lerini ve loglayan listener'larını zorla üretir veya zorla atlar. İkisi de verilmezse (varsayılan: evet) sorulur. |
| `--soft-deletes` / `--no-soft-deletes` | Model ve migration'da `SoftDeletes`'i zorla etkinleştirir veya zorla devre dışı bırakır. İkisi de verilmezse (varsayılan: evet) sorulur — `--from-migration` kullanıldığında tamamen atlanır (migration dosyasından tespit edilir). |
| `--vue=none\|empty\|full` | Vue sayfa üretim modu; yalnızca Admin katmanı üretiliyorsa geçerlidir (aksi halde `none`'a zorlanır). `full` Index (DataTable) + Create/Edit (FormBuilder) üretir; `empty` yalnızca boş bir Index sayfası üretir; `none` Vue üretimini atlar. Atlanırsa interaktif sorulur (varsayılan: `full`). |
| `--vue-fields` / `--no-vue-fields` | Yalnızca `--vue=full` ile anlamlıdır. Üretilen DataTable kolonlarına ve FormBuilder'a model alanlarını dahil eder ya da yalnızca id içeren bir iskelet üretir. İkisi de verilmezse ve alan varsa (varsayılan: evet) sorulur. |
| `--from-migration=<dosya adı>` | Alanları, ID tipini ve soft-delete'i `--fields`/`--id-type`/promptlar yerine var olan bir migration dosyasından ayrıştırır, örn. `--from-migration=2026_03_21_create_products_table.php`. Tam ya da kısmi dosya adı kabul edilir (`database/migrations/` altında glob ile eşleştirilir). |

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

**Toplu syntax** — `policy`, `factory`, `seeder`, `test`, `relations`'ın herhangi bir kombinasyonunu tek flag ile geçin (tekil `--with-*` flag'leri buna eklemeli olarak uygulanır):

```bash
php artisan make:sk-domain Article --with=policy,factory,test
```

**İlişki syntax'ı:**

```bash
php artisan make:sk-domain Article --with-relations --relations="belongsTo:User,hasMany:Comment,morphTo:commentable"
```

Desteklenen ilişki türleri: `belongsTo`, `hasMany`, `morphTo`. `--relations=` verilmesi `--with-relations`'ı zımnen içerir.

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
