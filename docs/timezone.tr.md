# Saat Dilimleri

Starter kit, timestamp değerlerini UTC olarak saklar; dönüşümü yalnızca gösterim ve tarih filtresi sınırlarında yapar.

## UTC Saklama Garantisi

Laravel saklama saat dilimini UTC'ye sabitleyin:

```env
APP_TIMEZONE=UTC
APP_DISPLAY_TIMEZONE=Europe/Istanbul
```

`APP_TIMEZONE`, `config('app.timezone')` değerini yönetir ve `UTC` olarak kalmalıdır. Burada bölgesel bir saat dilimi kullanmak saklanan satırları belirsiz hale getirir ve değer sonradan formatlanırken aynı offset'in iki kez uygulanmasına yol açabilir. `APP_DISPLAY_TIMEZONE` bağımsızdır: saklama davranışını değiştirmeden sitenin gösterim fallback'ini sağlar.

Saklama kontrolünü istediğiniz zaman çalıştırabilirsiniz:

```bash
php artisan sk:doctor --only=timezone-storage
```

Kontrol, `config('app.timezone') !== 'UTC'` olduğunda başarısız olur. Yeni satırlar yazılmadan önce yapılandırmayı düzeltin; UTC dışı yapılandırmayla daha önce saklanan satırların amaçlanan anları belirsiz olduğundan uygulamaya özel inceleme gerekebilir.

## Veritabanı Bağlantısı Saat Dilimi

Uygulama saat dilimini UTC'de tutmak gereklidir, ancak MySQL veya MariaDB için tek başına yeterli değildir. Bir `TIMESTAMP` kolonu yazılırken bağlantı oturumunun saat diliminden UTC'ye, okunurken yeniden oturum saat dilimine dönüştürülür. Oturum `SYSTEM` değerini miras alıyor ve veritabanı host saati UTC+03:00 ise uygulamanın verdiği UTC duvar saati bu nedenle diskte üç saat geride saklanır. Ters dönüşüm değeri uygulamaya doğru gösterir; farklı oturum saat dilimi kullanan replikalar, `mysqldump` çıktısı ve BI/raporlama araçları ise diskteki kaymış anı görür.

`DATETIME` kolonları bu oturum dönüşümüne uğramaz ve etkilenmez.

Kit'in MySQL ve MariaDB bağlantıları için sözleşme, `config/database.php` içindeki mevcut her bağlantı dizisinde yer alan literal girdidir:

```php
'timezone' => '+00:00',
```

`sk:install`, girdi eksikse onu ekler. Bu değer bilinçli olarak bir env değişkenine bağlı değildir: doğru olan tek bir saklama değeri vardır; yapılandırılabilir yapmak bozulmayı da yapılandırılabilir hale getirirdi. Mevcut `timezone` değerlerinin üzerine asla yazılmaz, bulunmayan bağlantı dizileri atlanır; SQLite, PostgreSQL ve SQL Server bağlantıları değiştirilmez.

### Mevcut veriler için tek seferlik dönüşüm

Önce incelemeyi erken bitiren dalı kontrol edin. MySQL host saati verinin tüm ömrü boyunca UTC olduysa hiçbir byte kaymamıştır ve dönüşüm gerekmez. Oturum ayarını ve o anda çözümlendiği saati tek sorguda kontrol edin:

```sql
SELECT @@session.time_zone AS session_time_zone,
       NOW() AS session_now,
       UTC_TIMESTAMP() AS utc_now,
       TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW()) AS utc_offset_seconds;
```

`0` offset yalnızca host'un kendisi UTC olarak yapılandırılmışsa yeterlidir. `@@session.time_zone` değeri `SYSTEM` ise işletim sistemi/veritabanı host saat dilimini de doğrulayın: bölgesel bir saat dilimi şu anda UTC'de olduğu hâlde eski satırlar için farklı bir yaz saati offset'i kullanmış olabilir.

Miras alınan host saati UTC değilse toplu ve ayrım yapmayan bir update çalıştırmayın. **Geri yüklenebilir bir yedek alın ve prosedürün tamamını önce veritabanının bir kopyasında prova edin.** Kit tarihsel host offset'ini bilemez. Offset'i host yapılandırması ve deployment geçmişinden kendiniz belirleyin; ardından yönünü ve miktarını gerçek oluşturulma zamanını bağımsız olarak doğrulayabildiğiniz en az bir bilinen-doğru kayıtla karşılaştırın. Host yaz saati değişiklikleri uyguladıysa veya verinin ömrü içinde saat dilimi değiştiyse etkilenen satırları dönemlere ayırın ve her dönemin offset'ini ayrı doğrulayın.

`TIMESTAMP` kolonlarını adlarına göre değil, nasıl yazıldıklarına göre sınıflandırın:

- **Uygulama tarafından yazılan `TIMESTAMP` değerleri** (`created_at`, `updated_at`, `last_login_at`, `email_verified_at`, `revoked_at` gibi) miras alınan offset ile saklandı, ancak eski oturum üzerinden doğru göründü. Bağlantı UTC'ye sabitlendikten sonra eski değerler kaymış görünür ve eski oturumun miras aldığı aynı işaretli offset kadar taşınmalıdır.
- **Veritabanı tarafından yazılan `DEFAULT CURRENT_TIMESTAMP` değerleri** MySQL tarafından doğru anda saklandı, yalnızca eski oturum üzerinden offset kadar ileri görünüyordu. UTC bağlantı ayarı tek başına gösterimlerini düzeltir. Bu kit'te **`file_favorites.created_at` ve `failed_jobs.failed_at` kolonlarını her dönüşüm update'inin dışında bırakın**. Bunları güncellemek zaten doğru olan değerleri bozar.

Gerçek geçişi uygulama yazmalarının durdurulduğu bir bakım penceresinde yapın: yeniden yedek alın, `'timezone' => '+00:00'` ekleyin, uzun ömürlü bağlantıları temizleyin/yeniden kurun, yeni bir oturumun `+00:00` raporladığını doğrulayın ve ancak bundan sonra önceden var olan, uygulama tarafından yazılmış kolonları güncelleyin. Aşağıdaki, uyarlanacak bir örnektir; doğrudan yapıştırılacak bir komut değildir:

```sql
-- YALNIZCA ÖRNEK: bağımsız doğrulanan eski offset +03:00 idi.
-- Tablo, kolon, işaretli aralık ve eski-satır koşulunu kendi verinize uyarlayın.
START TRANSACTION;

UPDATE your_table
SET created_at = DATE_ADD(created_at, INTERVAL 180 MINUTE),
    updated_at = DATE_ADD(updated_at, INTERVAL 180 MINUTE)
WHERE your_verified_legacy_row_predicate;

-- COMMIT seçmeden önce bilinen kayıtları inceleyin; eşleşmiyorsa ROLLBACK kullanın.
```

Negatif miras offset'i için negatif aralık kullanın; her tablonun veya tarihsel dönemin aynı yazma yoluna sahip olduğunu varsaymayın. Tercihen yazmaları durdurun ve trafik yeniden başlamadan önce mevcut tüm satırları dönüştürün. Config değişikliği zaten canlıya alındıysa veri seti karışıktır: değişiklikten önce yazılan satırlar offset'li, sonrasında yazılanlar doğrudur. Dönüşüm yalnız eski grubu bir cutover işareti veya bağımsız doğrulanmış başka bir koşulla uzlaştırmalıdır; geniş bir update yeni satırları bozar.

`sk:upgrade` bu veri dönüşümünü **yapmaz ve hiçbir zaman yapmayacaktır**. Yalnızca update rehberinde açıklanan güvenlik değerlendirmesi ve onay isteminden sonra `config/database.php` dosyasını yeniden yazabilir. Veriye özel offset, kolonun kaynağı ve satır sınırı kit tarafından güvenle çıkarılamaz.

## Gösterim Saat Dilimi Çözümü

Backend'deki her gösterim sınırı, `resolve_display_timezone(?object $user = null): string` helper'ının sunduğu aynı zinciri kullanır:

1. `user.timezone`
2. `config('app.display_timezone')` — **Ayarlar → Genel** saat dilimi
3. `config('app.timezone')`
4. `'UTC'`

Geçersiz IANA saat dilimi tanımları exception üretmek yerine atlanır. Profil bilgileri sekmesi ile yönetici kullanıcı oluşturma/düzenleme formlarında aranabilir bir saat dilimi seçici bulunur.

`users.timezone` kolonu nullable'dır ve varsayılan değeri yoktur. `null`, **site ayarını takip et** anlamına gelir; açıkça `'UTC'` saklamakla aynı değildir. `null` değerli bir kullanıcı, Genel ayarındaki daha sonraki saat dilimi değişikliğini takip eder; UTC'yi seçmiş kullanıcı ise UTC'de kalır.

Inertia, çözümlenen saat dilimini üst seviye `timezone` prop'u olarak paylaşır. `auth.user.timezone`, `null` dahil kullanıcının ham tercihini taşımaya devam eder.

## Backend Tarih Helper'ları

İki helper farklı sözleşmelere hizmet eder:

| Helper | Çıktı | Kullanım alanı |
|---|---|---|
| `format_date($value, $type = 'datetime', ?string $timezone = null)` | `14-03-2026 08:36` gibi gösterim metni | Blade, e-posta, dışa aktarma ve diğer son sunum çıktıları |
| `to_api_date($value)` | çözümlenen saat diliminde offset içeren ISO-8601 veya `null` | API Resource'ları ve diğer makine tarafından okunabilir sınırlar |

`format_date()` mevcut gösterim formatını korur ve geriye dönük uyumludur. Artık ortak çözüm zincirini izler ve açık bir saat dilimi override'ı kabul eder; ancak sonucu parse edilebilir bir an sözleşmesi değildir. İstemcilerin değeri güvenle yeniden formatlayabilmesi için API Resource'ları `to_api_date()` kullanmalıdır.

## Frontend Formatlama

`formatDateTime`, `formatDate` veya `formatTime` fonksiyonlarını `@lvntr/components/utils/datetime` içinden import edin. Bu fonksiyonlar `Intl.DateTimeFormat` kullanır ve açık saat dilimini şu sırayla çözümler:

1. fonksiyona verilen açık saat dilimi argümanı
2. Inertia tarafından paylaşılan `timezone` prop'u
3. tarayıcı saat dilimi
4. `'UTC'`

Utility'ler null veya parse edilemeyen input için `''` döndürür. Yükseltme uyumluluğu için mevcut bir `dd-mm-yyyy HH:mm` gösterim string'i değiştirilmeden geçirilir; yeni kodun ISO-8601 alması için consumer Resource'larını `to_api_date()` kullanımına güncelleyin.

## Datatable Tarih Filtreleri

Tarih kolonlarında `DatatableQueryBuilder::dateRangeFilters($column)` kullanın. Gelen `Y-m-d` değeri UTC tarihi değil, kullanıcının çözümlenen gösterim saat dilimindeki takvim tarihidir. Factory bunu çıplak kolon üzerinde yarı açık bir UTC aralığına dönüştürür:

```text
column >= yerel gün başlangıcının UTC karşılığı
column <  sonraki yerel gün başlangıcının UTC karşılığı
```

Sonraki gün sınırının aralığa dahil edilmemesi, saniye altı hassasiyet dahil son günün tamamını kapsar. Sınırın parse edilmiş yerel günden hesaplanması 23 ve 25 saatlik DST günlerini de doğru işler. Sorgu `whereDate` yerine herhangi bir fonksiyonla sarılmamış kolonu karşılaştırdığı için kolondaki indeks kullanılabilir kalır.
