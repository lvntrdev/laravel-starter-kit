# Modül Route Registry

Starter kit, vendor-resident route gruplarını consumer uygulamalara mount etmek için **modül route registry** kullanır. İki senaryoyu ele alır: consumer'da henüz route dosyası olmayan temiz kurulumlar ve consumer'ın stub'ı override ettiği özelleştirilmiş kurulumlar.

## Nasıl Çalışır

`StarterKitServiceProvider::moduleRouteRegistry()` bir modül descriptor listesi döndürür. `registerRoutes()` bu listeyi dolaşır ve her modül için şu mantığı uygular:

1. **Override kontrolü** — `overrideStubs` listesindeki herhangi bir dosya consumer uygulamada (`base_path(...)`) mevcutsa, paket o modülü tamamen atlar. Consumer'ın route orchestrator'ı (`routes/web.php` veya `routes/api.php`) stub'ı yüklemekten sorumludur.
2. **Otomatik mount** — Override stub bulunamazsa paket, modülü tanımlı `middleware` katmanı altında `Route::middleware(...)->group($loader)` aracılığıyla mount eder.

Bu sayede hangi yol izlenirse izlensin bir modül asla iki kez kaydedilmez.

## Modül Descriptor Yapısı

Registry'deki her girişin dört alanı vardır:

```php
[
    'name'          => 'file-manager',          // modül tanımlayıcısı (log/debug için)
    'overrideStubs' => [                        // mutlak yollar; herhangi biri eşleşirse → atla
        base_path('routes/web/file-manager-route.php'),
        base_path('routes/api/file-manager-route.php'),
    ],
    'middleware'    => ['web', 'auth', 'verified'], // otomatik mount yolu için dış grup
    'loader'        => static function (): void {   // vendor route dosyasını çağıran closure
        FileManager::routes();
    },
]
```

Registry, `config/` içinde değil `StarterKitServiceProvider` içinde bulunur; çünkü `loader` bir closure'dır ve closure'lar `config:cache` tarafından serialize edilemez. Service provider metodu, her modülün middleware katmanı için tek kaynak-of-truth'tur.

## Override Mekanizması

Consumer kit'i kurduğunda `sk:install` stub route dosyalarını uygulamaya kopyalar:

- `routes/web/file-manager-route.php`
- `routes/api/file-manager-route.php`

Bu dosyalardan herhangi birinin varlığı, registry'ye consumer orchestrator'ının mount'u sahiplendiğini bildirir. Paket kenara çekilir ve modülü mount etmez.

### Modülün Route'larını Özelleştirme

Stub'ı kendi route dosyanızla değiştirin. Paket dosyayı algılar ve otomatik mount'u atlar. Dosyanız, o modülün route'larının kaydedileceği tek yer olur.

```php
// routes/web/file-manager-route.php — consumer'a ait override
// Aşağıdaki tek satırı kendi grubunuz ve controller'ınızla değiştirin.

use Lvntr\StarterKit\Facades\FileManager;

FileManager::routes(); // vendor route'larını koruyun, ya da kaldırıp kendinizinkini yazın
```

Yalnızca vendor route'larını istiyorsanız dosyayı ince tutun — tek satırlık stub, paketin route dosyasına delege eder ve `composer update` aracılığıyla geleceğe dönük bir yükseltme yolu sağlar.

### Override'ı Kaldırma (otomatik mount'a geri dönme)

Stub dosyasını silin. Registry, bir sonraki istek boot'unda otomatik mount yoluna geri döner. Bu işlem güvenlidir — middleware katmanı, stub mevcutken orchestrator'ın uyguladığıyla aynıdır.

## File Manager — Çalışan Örnek

File manager, registry'deki tek vendor-resident modüldür. Descriptor'ı:

| Alan | Değer |
| --- | --- |
| `name` | `file-manager` |
| `overrideStubs` | `routes/web/file-manager-route.php`, `routes/api/file-manager-route.php` |
| `middleware` | `web`, `auth`, `verified` |
| `loader` | `FileManager::routes()` |

Loader, `FileManager` facade'ı aracılığıyla `src/routes/file-manager.php`'yi çağırır. Bu dosya, `file-manager.` isim öneki altında tam route grubunu tanımlar.

### Share Endpoint Güvenliği (K1)

Public share endpoint'i (`GET /file-manager/share/{media}`), route dosyasının içinde `withoutMiddleware(['auth', 'verified', 'auth:sanctum', 'auth:api'])` kullanır. Bu sayede endpoint, dışarıda hangi middleware grubu altında mount edilirse edilsin (otomatik mount yolu veya consumer stub'ı) — geçerli bir signed URL'e sahip kimliği doğrulanmamış kullanıcılar tarafından erişilebilir. Endpoint yalnızca doğrudan route üzerinde tanımlanan `signed` ve `throttle:60,1` middleware'i ile korunur.

## Yeni Vendor-Resident Modül Ekleme (Reçete)

Bu reçete, route'larını consumer stub'larından vendor paketine taşıyan Faz 3 / Faz 6 modülleri için geçerlidir.

**Adım 1.** `src/routes/<module-name>.php` konumunda vendor route dosyasını oluşturun. Tüm route'ları bu dosyada tanımlayın. Burada dış auth middleware eklemeyin — dış katmanı registry yönetir.

**Adım 2.** Dosyayı require eden bir loader metodu veya facade metodu ekleyin, örneğin:

```php
// src/Facades/MyModule.php
public static function routes(): void
{
    require __DIR__.'/../routes/my-module.php';
}
```

**Adım 3.** `StarterKitServiceProvider` içindeki `moduleRouteRegistry()`'ye bir descriptor ekleyin:

```php
[
    'name'          => 'my-module',
    'overrideStubs' => [
        base_path('routes/web/my-module-route.php'),
    ],
    'middleware'    => ['web', 'auth', 'verified'],  // modül gereksinimlerine göre ayarlayın
    'loader'        => static function (): void {
        MyModule::routes();
    },
],
```

**Adım 4.** `stubs/routes/web/my-module-route.php` konumunda stub oluşturun — `MyModule::routes()`'u çağıran tek satırlık bir dosya. `sk:install` onu kurulumda consumer uygulamaya kopyalar. `sk:update` güncellemede yeniler.

**Adım 5.** Şunları kapsayan testler yazın:
- Stub yokken → vendor, tanımlı middleware katmanı altında otomatik mount yapar
- Stub varken → paket mount'u atlar (çift kayıt yok, route adı çakışması yok)
- Modüle özgü route adları doğru çözümlenir

`registerRoutes()` içinde başka bir bağlantıya gerek yoktur — döngü yeni descriptor'ı otomatik olarak alır.

## Middleware Katmanı Referansı

| Modül | Middleware | Notlar |
| --- | --- | --- |
| `file-manager` | `web`, `auth`, `verified` | Permission middleware yok; share endpoint, route dosyasında `withoutMiddleware` ile auth'u kaldırır |

Yeni modüller middleware katmanlarını descriptor içinde açıkça tanımlamalıdır. `check.permission` middleware'ini gerektiren bir modül onu `middleware` dizisine eklemelidir — registry bunu otomatik olarak çıkarsamaz.
