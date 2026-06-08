# Log Görüntüleyici

`storage/logs/` altındaki Laravel log dosyalarını okumak, aramak ve silmek için yalnızca bakım rolüne açık bir admin bölümü. Kendi içinde tamamlanmış bir domain modülü olarak gelir; ek bağımlılık gerektirmez.

## Yetkilendirme

Tüm modül **yalnızca `system_admin` rolüne** açıktır — `config/permission-resources.php` içinde girdi yoktur. `system_admin` olmayan kullanıcılar route'a 403 alır ve menü öğesini hiç görmez; yani özellik onlara görünmez.

`routes/web/log-route.php` dosyası `routes/web.php` içindeki `$routesWithoutPermissionMiddleware` listesinde olduğu için bu grup için dinamik `check.permission` middleware'i atlanır.

## Route'lar

Route dosyası, authenticated web grubunun içinde yüklenir. İsimli route'lar:

- `logs.index` — `GET /logs` (Inertia)
- `logs.dtApi` — `GET /logs/dt` (JSON datatable feed)
- `logs.show` — `GET /logs/{filename}` (Inertia)
- `logs.entries` — `GET /logs/{filename}/entries` (JSON, sayfalı)
- `logs.destroy` — `DELETE /logs` (JSON, toplu silme)

`filename` parametreleri `where('filename', '[A-Za-z0-9._-]+\.log')` kısıtıyla sınırlandırıldığı için path traversal ve `.log` olmayan istekler controller'a hiç ulaşmaz.

## UI

`resources/js/pages/Admin/Logs/` altında iki Inertia sayfası vardır:

### `Index.vue`

`logs.dtApi` üzerinden beslenen `SkDatatable`. Kolonlar:

- `name` — dosya adı (sortable, substring araması)
- `channel_type` — `daily` (`laravel-YYYY-MM-DD.log` formatına uyanlar), `single` (`laravel.log`) veya `other`
- `size_bytes` — KB / MB / GB olarak formatlanır
- `modified_at` — relatif zaman + tooltip'te mutlak zaman
- `is_active` — dosya canlı günlük dosyası ya da son 5 saniyedir yazılıyorsa chip görünür

Satır aksiyonu: **Sil** (`is_active` ise pasif). Bulk select toolbar'ında **Seçiliyi sil** aksiyonu vardır; ikisi de `logs.destroy` üzerinden akar ve `useConfirm` ile onaylatılır.

### `Show.vue`

Tek dosya için filtre paneli + sayfalı görüntüleyici. Filtreler:

- `levels[]` — sekiz Laravel/PSR-3 seviyesinden çoklu seçim (`emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`)
- `from`, `to` — ISO tarih aralığı
- `keyword` — mesaj + stack trace içinde case-insensitive substring araması

Filtre değişiklikleri `useApi` üzerinden `logs.entries` çağrısı yapar, listeyi yeniler ve cursor'ı sıfırlar. **Daha fazla yükle**, önceki yanıttaki `next_cursor` ile devam eder. `eof` flag'i true ise butonu kapatır.

Her kayıt level chip + timestamp + mesajın baş kısmı olarak çökertilmiş gelir. Açıldığında tam mesaj, JSON pretty-print edilmiş `context` (varsa) ve stack trace görünür.

## Domain Katmanı

Logs domain'i **vendor-resident**'tir — runtime katmanı paket içinden çalışır (`src/Domain/Logs/`, namespace `Lvntr\StarterKit\Domain\Logs\`) ve uygulamanıza scaffold edilmez. `App\Domain\Logs\...` import'ları `class_alias` ile çalışmaya devam eder.

```
src/Domain/Logs/   (Lvntr\StarterKit\Domain\Logs\)
├── DTOs/
│   ├── LogFileDTO.php          # name, path, size_bytes, modified_at, channel_type, is_active
│   ├── LogEntryDTO.php         # timestamp, level, env, message, context, stack, is_raw
│   ├── LogEntryFilterDTO.php   # levels, from, to, keyword, cursor, per_page
│   └── DeleteLogFilesDTO.php   # filenames[]
├── Queries/
│   ├── LogFileQuery.php        # storage/logs/'i listeler; filtre/sıralama/sayfalama in-memory
│   └── LogEntryQuery.php       # tek dosyayı cursor sayfalama ile stream eder
├── Actions/
│   └── DeleteLogFilesAction.php # active-file koruması ile toplu silme
├── Events/
│   └── LogFilesDeleted.php     # silinen filenames[] + causer id taşır
├── Listeners/
│   └── LogActivityForLogFilesDeleted.php  # her batch için bir spatie/activitylog kaydı yazar
└── Services/
    └── LaravelLogParser.php    # stateless satır parser'ı; çok satırlı stack trace farkındalığı var
```

`LogFilesDeleted → LogActivityForLogFilesDeleted` eşleşmesi `StarterKitServiceProvider::registerEventListeners()` (vendor) içinde register edilir; her iki taraf da vendor FQCN'ine bağlanır, böylece dispatch edilen event registration anahtarıyla eşleşir.

### Streaming Kayıt Okuyucu

`LogEntryQuery::paginate()` dosyayı `fopen('rb')` ile açar ve satır başına 64KB ile sınırlı `fgets()` döngüsünü kullanır. Cursor, bir sonraki kayıt başlığının başladığı byte offset'idir; sayfanın devamına geri dönmek tek `fseek` ile olur. Bellek kullanımı dosya boyutundan bağımsız olarak sabit kalır.

Eşleşmeyen satırlar konumlarına göre işlenir:

- bir başlığın ardından gelen satırlar — mevcut kaydın `stack` alanına eklenir; çok satırlı exception'ların bütünlüğü korunur
- ilk başlıktan önceki (veya hiç Laravel-format başlığı içermeyen dosyalarda kalan) satırlar — buffer'lanır ve tek bir raw `LogEntryDTO` olarak basılır (`is_raw = true`, `level = 'raw'`, sentinel epoch-0 timestamp). UI raw entry'lerin zaman damgasını gizler ve gri chip ile gösterir; böylece içerik sessizce kaybolmak yerine görünür kalır. Yapısal filtre uygulandığı an (level / from / to / keyword) raw entry'ler doğal olarak listeden düşer.

### Aktif Dosya Koruması

Bir dosya iki koşuldan **biri** sağlanırsa "aktif" sayılır ve silinmez:

- bugünün günlük dosyasıdır (`laravel-{today}.log`), veya
- `mtime`'ı son 5 saniye içindedir (başka bir kanal şu an dosyaya yazıyor olabilir).

Aktif dosyalar `failed[]` listesinde `reason: 'active_file_protected'` ile döner; böylece kısmi toplu silme geri kalan dosyalar için başarılı olur.

### Path-Traversal Koruması

Güvenli dosya adı regex'i `^[A-Za-z0-9._-]+\.log$` üç farklı yerde zorlanır: route parameter kısıtı, `DeleteLogFilesRequest` validasyonu ve `DeleteLogFilesAction` (defence in depth). Geri kalan her şey `log.invalid_filename` döner.

## Activity Log

Bir silme batch'i en az bir dosya silebildiğinde `LogFilesDeleted` dispatch edilir. Listener `log_name = 'system'`, subject yok, silinen dosyalar `properties.filenames` altında ve causer olarak mevcut kullanıcı ile bir `spatie/activitylog` kaydı yazar. Kayıt mevcut **Admin → Activity Logs** sayfasında otomatik görünür.

## i18n

Tüm metinler `lang/en/sk-log.php` ve `lang/tr/sk-log.php` altında. Menü key'i (`sk-menu.logs`) mevcut menü çeviri dosyalarında. Action'ın döndüğü hata sebep kodları (`invalid_filename`, `not_found`, `active_file_protected`, `delete_failed`) bire bir UI tarafındaki `sk-log.reason_*` key'lerine eşlenir.

## Wayfinder

Route'lar tiplidir: `import logs from '@/routes/logs'` ile `logs.index.url()`, `logs.show.url({ filename })` vs. erişilir. Frontend'de URL hardcoded değildir.

## Kapsam Dışı (v1)

Yapılmadı; ihtiyaç duyulursa follow-up issue olarak açılabilir:

- live tail / WebSocket streaming
- tüm log dosyalarında cross-file arama
- zaman tabanlı toplu temizlik ("N günden eski dosyaları sil") — günlük kanal bunu zaten yapıyor
- `.zip` export / indirme
- Laravel-dışı log formatları (Apache, JSON channel)
- `system_admin` dışında kullanıcı tabanlı yetki ayrımı
