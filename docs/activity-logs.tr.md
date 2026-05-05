# İşlem Kayıtları

İşlem kayıtları modülü, admin aksiyonları ve model değişiklikleri için denetim görünürlüğü sağlar.

## Temel Katman

Bu yapı `spatie/laravel-activitylog` üzerine inşa edilmiştir. Proje, Spatie'nin `LogsActivity` concern'ünü kendi trait'i olan `App\Traits\HasActivityLogging` (`app/Traits/HasActivityLogging.php`) içine sarmalar. Denetlenmesi gereken modellere doğrudan Spatie'nin `LogsActivity`'si değil, bu proje trait'i eklenmelidir.

Özel trait loglama stratejisini otomatik seçer: `$fillable` tanımlayan modeller için `logFillable()`, diğerleri için `logUnguarded()` çağrılır. Boş değişiklik setleri her zaman atlanır.

## Route'lar

İşlem kayıtları modülü üç adlandırılmış route tanımlar:

- `activity-logs.index` — `GET /activity-logs`
- `activity-logs.dtApi` — `GET /activity-logs/dt`
- `activity-logs.show` — `GET /activity-logs/{activity}`

## Genelde Neleri İzler

- oluşturulan kayıtlar
- güncellenen kayıtlar
- silinen kayıtlar
- işlemi yapan kullanıcı bilgisi
- mümkünse değişen alanlar

User create, update ve delete aksiyonları artık özel domain event'leri de dispatch eder. Böylece admin tarafındaki kullanıcı yönetimi değişiklikleri, rol değişimleri ve silme olayları dahil olmak üzere audit log akışına yeniden güvenilir biçimde düşer; boş/no-op update'ler ise atlanır.

## Arayüz Katmanı

Admin panelde genelde şunlar yer alır:

- datatable tabanlı liste ekranı
- detay dialog'u veya detay ekranı
- event tipi ve ilişkili veri bazlı filtreler

## Neden Önemli

Bu modül, admin yoğun projelerde destek, denetim, debug ve operasyonel hataları izleme açısından çok değerlidir.
