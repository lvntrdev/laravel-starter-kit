# Roller ve Yetkiler

Yetki sistemi `spatie/laravel-permission` üzerine kuruludur ve `config/permission-resources.php` dosyasından beslenir.

## Temel Fikir

Permission kayıtları config içindeki resource isimleri ve ability'lerden üretilir. Üç kaynak katkıda bulunur:

- `resources` — standart CRUD tarzı kaynaklar
- `sub_resources` — ana kaynağın altında kapsamlı varyantlar
- `custom_permissions` — resource modeline uymayan tek seferlik girişler

Mevcut projeden örnekler:

- `users.read`
- `users.update`
- `roles.update`
- `settings.update`
- `files.read`
- `files.create`
- `files.update`
- `files.delete`
- `activity-logs.read`
- `pulse.read`
- `api-docs.read`

Alt kaynaklar da desteklenir:

- `users:student.read`
- `users:guardian.update`

## Ana Config Bölümleri

- `resources` — resource isimlerini ve hangi CRUD ability'lerin üretileceğini tanımlar
- `sub_resources` — kendi permission string'lerine sahip iç içe varyantlar
- `custom_permissions` — resource modeli dışındaki rastgele permission girişleri
- `permission_groups` — Roller admin UI'ı için permission'ları gruplar
- `role_groups` — UI'da rolleri gruplar
- `role_permissions` — hangi rollerin varsayılan olarak hangi permission'ları alacağını seed eder
- `display_names` — Roller admin UI'ı için okunabilir etiketler

## Varsayılan Roller

- `system_admin`
- `admin`
- `user`

`system_admin`, normal permission kısıtlarını aşacak şekilde tasarlanmıştır.

Varsayılan rollerin yetkileri de `config/permission-resources.php` içinde tanımlıdır.

### FileManager ability'leri

FileManager'ın built-in `global` context'i dört bağımsız ability kontrol eder; birine sahip olmak diğerlerini vermez:

| Yetki | İzin verdiği işlemler |
| --- | --- |
| `files.read` | Tree'de gezinme, favorileri/çöpü listeleme ve dosya indirme |
| `files.create` | Dosya yükleme, klasör oluşturma ve dosya kopyalama |
| `files.update` | Öğeleri yeniden adlandırma/taşıma, favorileri değiştirme, çöpten geri yükleme ve paylaşma/iptal context kontrolünü geçme |
| `files.delete` | Öğeleri silme, çöpü boşaltma ve çöpteki öğeleri kalıcı silme |

Rol atamalarını değiştirdikten sonra seed edilmiş yetki verisini dört-ability sözleşmesiyle eşlemek için `php artisan sk:seed-permissions` çalıştırın.

## User Yönetiminde Rol Hiyerarşisi

Kullanıcı yönetimi hem admin paneli hem de API tarafında rol hiyerarşisini dikkate alır:

- `RoleSelectOptionsQuery`, mevcut kullanıcının atayabileceği rolleri döner
- `system_admin` tüm rolleri atayabilir
- `system_admin` olmayan kullanıcılar sadece kendi seviyelerindeki veya daha alt seviyedeki rolleri görür
- direct permission sahibi olup hiç rolü olmayan kullanıcılar en düşük seviye kabul edilir ve admin user akışında rol atayamaz
- `UpdateUserRequest::authorize()`, kullanıcıda `users.update` olsa bile daha düşük seviyedeki bir aktörün daha üst seviyedeki hedefi düzenlemesini engeller
- `UserDatatableQuery` (hem admin kullanıcı listesi hem de `GET /api/v1/users` tarafından kullanılır), minimum rol `sort_order`'ı aktörünkinden düşük olan kullanıcıları gizler — yani `users.read` izni olan ama `system_admin` olmayan bir API tüketicisi üst-rank kullanıcıları enumerate edemez
- `Admin/RoleController::data` (`edit`'in JSON kardeşi), `Admin/RoleController::edit` ve `destroy` aynı `CanManageRoleQuery` kontrolünü çalıştırır — alt-rank bir admin, prefetch endpoint'i üzerinden de üst-rank rol JSON'unu okuyamaz

## Permission'ları Yeniden Üretme

`database/seeders/_01_RolePermissionSeeder.php` şu işlemleri yapar:

- config'te tanımlı permission'ları oluşturur
- sub-resource permission'larını oluşturur
- custom permission'ları oluşturur
- artık config'te olmayan orphan permission'ları siler
- varsayılan rolleri oluşturur ve günceller

Permission config'i değiştirdikten sonra seed verisini yeniden oluşturun:

```bash
php artisan sk:seed-permissions --fresh
```

Admin panelde ayrıca sadece `system_admin` kullanıcılarının çalıştırabildiği bir permission sync aksiyonu vardır.

### Matrisi paket güncellemeleriyle aynı hizada tutmak

`sk:update`, `config/permission-resources.php` dosyasına asla yazmaz — dosya sizindir ve içine merge eden bir updater er ya da geç projenin kendi yetkilendirme modelini ezerdi. Bunun sonucu şudur: kitin sonraki bir sürümde eklediği kaynak veya yetenek mevcut kuruluma kendiliğinden ulaşmaz ve bunun ilk belirtisi genelde daha önce çalışan bir ekranda alınan 403'tür.

Güncellemeden sonra sorun:

```bash
php artisan sk:doctor --only=permission-matrix
```

Kontrol, paketin gönderdiği ama sizin config'inizde tanımlı olmayan tüm kaynak ve yetenekleri listeler (kendi eklediğiniz kaynaklar asla raporlanmaz). Listelenen girdileri elle ekleyip `php artisan sk:seed-permissions` çalıştırın.

## Otomatik Route-to-Permission Eşleme

`Lvntr\StarterKit\Http\Middleware\CheckResourcePermission` (vendor: `vendor/lvntr/laravel-starter-kit/src/Http/Middleware/CheckResourcePermission.php`), route isimlerini otomatik olarak permission string'lerine dönüştürür.

Örnekler:

- `users.index -> users.read`
- `users.store -> users.create`
- `users.edit -> users.update`
- `users.destroy -> users.delete`

Route middleware içinde açık bir permission verilirse o değer doğrudan kullanılır.

## Sub-Resource Desteği

Middleware, `type` query parametresi ile sub-resource permission'larını da destekler.

Örnek:

- route permission: `users.read`
- mevcut URL: `/users?type=student`
- çözülmüş permission: `users:student.read`

Bu davranış sadece ilgili scoped permission veritabanında varsa uygulanır.

## Frontend Kullanım

### Composable

Sayfa ve bileşenlerde `@/composables/useCan` kullanılır:

```ts
const { can, canAny, hasRole } = useCan();
```

### Vue Direktifleri

Frontend permission plugin'i şu direktifleri kaydeder:

- `v-can`
- `v-role`

Örnekler:

```vue
<Button v-can="'users.create'" />
<Button v-can:any="['users.create', 'users.update']" />
<div v-role="'system_admin'">Sadece sistem yöneticileri için</div>
```

### FormBuilder Form-Level Yetki

`SkForm` ayrıca `.permission('users.update')` zincir metoduyla tüm formu salt-okunur moda alabilir. Kullanıcı o yetkiye sahip değilse tüm alanlar disabled olur ve submit butonu gizlenir. Detaylar için [FormBuilder Rehberi](./formbuilder.tr.md#yetki-kontrolu-form-level) bölümüne bakın.

### DataTable Row Action'ları

`SkDatatable` row action'larının ve menu action'larının her birinde `.visible(() => can('users.update'))` gibi bir callback tanımlanınca buton kullanıcı yetkili değilse hiç render edilmez.

## Middleware Eşlemesi

Proje, route niyetini permission kontrolüne çevirir. `users.index` gibi bir route adı çoğu zaman `users.read` kontrolüne karşılık gelir. `check.permission` middleware'i ile korunan route'lar bu otomatik çözümlemeden yararlanır.

Çözümlenen permission veritabanında yoksa middleware'in davranışı `app()->environment()` değerine göre değişir (v13.6.9'dan beri varsayılan fail-closed'dır):

- `local`: isteğe izin verir ve eksik permission seed edilsin diye warning log yazar — günlük geliştirme, henüz seed edilmemiş bir permission yüzünden bloklanmasın diye
- diğer tüm ortamlar — `production`, `staging`, `uat`, `demo`, `testing` vb.: isteği `403` ile reddeder, böylece unutulmuş bir permission satırı public bir host'ta route'u sessizce açığa çıkarmaz

**Opt-out:** v13.6.9 öncesindeki "production dışı her ortamda izin ver" davranışını geri getirmek için `config('starter-kit.permissions.allow_unmapped')` değerini `true` yapın (env `STARTER_KIT_ALLOW_UNMAPPED_PERMISSIONS=true`). Bu bayrak ne olursa olsun `production` her zaman reddeder. Tam migration notu için [UPGRADE.tr.md](./UPGRADE.tr.md) dosyasına bakın.

### Unmapped vs. Unresolved

Yukarıdaki iki başarısızlık modu birbirine karıştırılmaya müsaittir ama ayrı config anahtarları tarafından yönetilir:

- **`allow_unmapped`** — route adından bir izin TÜRETİLDİ (`admin.users.index` → `users.read`), ama o adda bir satır veritabanında seed edilmemiş. Yukarıda anlatıldı.
- **`allow_unresolved`** (config `starter-kit.permissions.allow_unresolved`, env `STARTER_KIT_ALLOW_UNRESOLVED_ROUTES`, varsayılan `true`) — HİÇBİR izin türetilemedi: route'un adı yok, adı iki segmentten az, ya da action segmenti middleware'in ability haritasında yok. Geçmişte bu durum tamamen sessizce geçerdi; varsayılan `true` iken hâlâ geçer, ama middleware artık route'u adlandıran, throttle edilmiş bir uyarı logu basar; böylece boşluk görünür olur. `false` yapıldığında istek reddedilir. `php artisan sk:doctor --only=unresolved-routes` şu an bu durumda olan her route'u listeler.

**Production asimetrisi, bilinçli:** `allow_unmapped`'in aksine — ki production onu her zaman reddet'e sabitler — `allow_unresolved`, `false`'a çevrildikten sonra production'da da uygulanmaya devam eder. Unmapped bir izin host'a özgü bir *veri* boşluğudur (satırı seed ederek düzeltilir); çözülemeyen bir route ise route tablosu ile ability haritası arasındaki *yapısal* bir uyuşmazlıktır, yalnızca route'u yeniden adlandırarak ya da kod göndererek düzeltilebilir — bu yüzden kaçış kapısının, flip aksi hâlde bir route'u kilitleyebileceği host'ta hâlâ mevcut olması gerekir.

`starter-kit.permissions.unrestricted_routes`, bilinçli olarak izinsiz kalacak route-adı desenlerini listeler (`Str::is` wildcard'ları, örn. `'api.v1.auth.*'`): bunlar `allow_unresolved` ne olursa olsun uyarısız geçer ve asla reddedilmez. Yalnızca unresolved ekseninde devreye girer — izni zaten çözülen bir route'u asla muaf tutamaz — ve istek başına bir kez kontrol edilir; bu yüzden desenleri dar tutun (ağaç yerine tek tek endpoint listeleyin) ki sonradan eklenen route'lar sessizce muaf kalmasın.

**Değişecek olan:** `allow_unresolved` bugün varsayılan olarak `true`'dur ve bir sonraki minor sürümde varsayılanı `false` olacaktır. Değişimden önce izlenecek sıralı düzeltme yolu için [UPGRADE.tr.md](./UPGRADE.tr.md) dosyasına bakın.

### Octane / Long-Running Worker Ortamları

`CheckResourcePermission`, seed edilmiş permission isim listesini request veya worker ömrü boyunca değil, kısa bir TTL (60 saniye) ile `Cache::remember()` üzerinden cache'ler. Hem `php artisan sk:seed-permissions` hem de Roles ekranının permission sync'i (`RoleController::syncPermissions()` → `SyncPermissionsAction`), seed işleminden hemen sonra `CheckResourcePermission::flushCache()` çağırır; böylece yeni seed edilen bir permission, TTL'in dolmasını beklemeden anında geçerli olur.

Bu sayede kit Octane'de de ekstra bir şey yapmadan güvenlidir — Octane (Swoole / RoadRunner) ya da standart PHP-FPM fark etmeksizin, bir `RequestReceived` listener'ına ya da manuel cache temizleme workaround'una gerek yoktur.

**Kalan bir uyarı:** cache store'unuz merkezi olarak paylaşılan değil de process başına ayrı ise (örneğin `array` driver), seed/sync işlemini gerçekleştirmemiş bir worker, 60 saniyelik TTL boyunca hâlâ eski (stale) permission listesini sunmaya devam edebilir — kısa TTL zaten tam olarak bu pencereyi sınırlamak için var.

## Login Sırasında Status Kontrolü

API login (`POST /api/v1/auth/login`) sadece credential doğruluğunu değil, kullanıcının `status` alanını da doğrular. `LoginUserAction`:

1. `Auth::attempt()` ile credentials'i kontrol eder.
2. Başarılıysa `user.status` alanına bakar.
3. `active` dışındaki durumlarda (`inactive`, `banned`) `Auth::logout()` çalıştırır ve `null` döner.

Controller bu durumda `401 Invalid email or password` cevabı verir — yani banned/inactive hesaplar geçerli şifreyle bile token alamaz.

## Menü Filtreleme

`useAdminMenu()`, projeye özel admin navigasyon ağacını tanımlar; `useMenuBuilder()` ise görünen öğeleri kullanıcının permission ve role bilgisine göre filtreler.

Query parametresi dikkate alan aktif menü mantığı da `useMenuBuilder()` içinde olduğu için `/users?type=student` gibi linkler doğru menüyü aktif gösterebilir.

## Yeni Korumalı Alan Eklerken Pratik Akış

1. `config/permission-resources.php` içine resource ve ability tanımını ekle.
2. `php artisan sk:seed-permissions --fresh` komutunu çalıştır.
3. Route'ları `check.permission` ile koru.
4. Frontend tarafında gereken yerde `useCan()` veya `v-can` kullan.

## Yetkilendirme Katmanları

Starter kit **üç katmanı üst üste** kullanır. Birbirlerinin yerine geçmezler — ihtiyacın olan granülariteyi karşılayan katmanı seç.

| Katman | Konum | Granülarite | Örnek |
| --- | --- | --- | --- |
| 1. Route middleware | Route tanımlarında `check.permission` | Rota başına, geniş permission (`users.read`) | `Route::get('/admin/users', …)->middleware('check.permission')` |
| 2. Laravel Policy | `app/Policies/*Policy.php` | Model örneği başına, opsiyonel satır bazlı kurallar | `$this->authorize('update', $role)` |
| 3. FileManager ContextRegistry | `Lvntr\StarterKit\Domain\FileManager\Support\ContextRegistry` (vendor) | Pluggable FileManager context'i başına (owner model + özel kurallar) | Context kaydı sırasında verilen closure |

### Hangisini ne zaman kullanmalıyım

- **Sadece middleware** flat admin CRUD için yeterlidir — izni olan herkes her satıra erişebilir.
- **Policy ekle** satır bazlı kural gerektiğinde (self-ownership, state-tabanlı kontrol, tenant scope). Policy'ler otomatik keşfedilir: `App\Models\Foo` → `App\Policies\FooPolicy`.
- **FileManager context kaydet** bir domain'in kendi modeline bağlı files tab'ı açması gerektiğinde (kullanıcılar, organizasyonlar, projeler). Context'in authorize closure'ı `read`, `create`, `update` ve `delete` erişimini yönetir, controller'da logic tekrarlanmaz.

### Policy kalıbı

Policy metodları ilk argüman olarak authenticated `User`'ı, ikinci argüman olarak hedef modeli alır. Kontrolleri permission-öncelikli yaz; self/state mantığını yalnızca gerekirse ekle.

```php
namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('roles.read');
    }

    public function update(User $actor, Role $role): bool
    {
        // Gerekirse satır bazlı kuralları buraya ekle (örn. tenant scope).
        return $actor->can('roles.update');
    }
}
```

Kit; `User`, `Role`, `Setting` ve `FileFolder` için policy'ler sağlar. Bu policy'ler **eklemelidir (additive)** — controller hiç `authorize()` çağırmasa bile middleware korumalı rotalar çalışmaya devam eder.

### FileManager ContextRegistry

`ContextRegistry`, pluggable bir yetkilendirme hook'u açar: her dosya context'i (örn. `user` veya host uygulamanın eklediği özel `project` context'i) `read`, `create`, `update` veya `delete` alan bir closure sağlar. Kit deprecated `write` adını hiçbir zaman göndermez. Varsayılan user-owned context `read` işlemini `UserPolicy@view`'a, tüm mutasyonları `UserPolicy@update`'e delege eder — yani tek bir policy hem açık `authorize()` çağrılarını hem de files tab guard'ını yönetir.

Closure'ı ince tut; gerçek kuralları Policy'ye delege et ki mantık tek yerde kalsın.
