# Ayarlar

Settings modülü, operasyonel yapılandırmayı admin panel içinde merkezi hale getirir.

## Bölümler

- `general` — uygulama adı, timezone, aktif arayüz dilleri, logo yükleme/silme, admin dashboard hoş geldin mesajı (opsiyonel WYSIWYG)
- `auth` — kayıt, e-posta doğrulama, şifre sıfırlama ve iki faktör kullanılabilirliği
- `mail` — mailer, SMTP host/port, kimlik bilgileri, gönderici adres/adı
- `storage` — media disk seçimi ve S3 uyumlu / AWS kimlik bilgileri
- `file_manager` — yükleme boyutu, kabul edilen MIME listesi, ses/video toggle'ları
- `turnstile` — özellik toggle'ı, site key ve secret key
- `api_clients` — Postman ve Apidog senkronizasyon kimlik bilgileri; tek sekme içinde iki yapılandırma kartı

## Saklama Modeli

Ayarlar veritabanında tutulur ve setting service katmanı üzerinden çözülür.

Hassas anahtarlar `config/settings.php` ile şifrelenebilir.

Mevcut örnekler:

- `mail.password`
- `storage.spaces_secret`
- `storage.aws_secret`
- `turnstile.secret_key`
- `postman.api_key`
- `apidog.access_token`

Secret değerler frontend'e geri gönderilmez. Settings payload'ı, ham secret string'i döndürmeden arayüzün bir değerin var olup olmadığını gösterebilmesi için `*_is_set` boolean alanlarını kullanır.

## Route Yüzeyi

Admin modülü şu route'ları sunar:

- `settings.index`
- `settings.update.general`
- `settings.update.auth`
- `settings.update.mail`
- `settings.update.storage`
- `settings.update.fileManager`
- `settings.update.turnstile`
- `settings.update.postman` — `PUT /settings/postman`
- `settings.update.apidog` — `PUT /settings/apidog`
- `settings.testMail`
- `settings.upload.logo` — `POST settings/logo`
- `settings.delete.logo` — `DELETE settings/logo`

## Çalışma Zamanı Notları

- `SettingsController@index`, gruplanmış settings payload'ını timezone seçenekleri ve tanımlı dil listesiyle birlikte döner
- yazma akışları ince tutulur: FormRequest -> DTO -> Action
- `mail`, `storage` ve `turnstile` gruplarındaki secret alanlar artık saklanan değeri değil, `null` ve buna eşlik eden `*_is_set` bayraklarını döner
- secret alanı boş gönderildiğinde mevcut veritabanı veya config tabanlı değer korunur
- logo yükleme/silme akışı, ana `SkForm` dışında küçük bir JSON yan akışı olarak çalışır
- logo yükleme/silme uçları artık standart `ApiResponse` zarfını kullanır
- Auth sekmesinde iki faktörü kapatmak, değişiklik gönderilmeden önce yöneticiye onay sorar; çünkü kullanıcı güvenliğini etkiler
- Turnstile ayarları login, register ve forgot-password formlarındaki challenge davranışını besler
- test-mail hataları sunucu tarafında loglanır ve ham SMTP exception detayı kullanıcıya gösterilmez
- **Genel** sekmesindeki `welcome_message` alanı `FB.editor()` ile yazılır; içerik hem yazılırken (FormRequest `prepareForValidation` hook'u) hem okunurken (DashboardController defense-in-depth geçişi) `App\Support\HtmlSanitizer` üzerinden sanitize edilerek admin dashboard'da render edilir
- `SettingService::setValue()` / `setGroup()` fonksiyonları `HTML_SAFE_KEYS` whitelist'indeki anahtarları `HtmlSanitizer::clean()`'den geçirir — FormRequest, tinker, scheduled command ve queue job'lar aynı sanitizer'ı kullanır, böylece normal setting API'si üzerinden sanitize edilmemiş HTML DB'ye yazılamaz

## HTML-safe anahtarlar

Zengin metin içeriği tutan setting anahtarları `SettingService::HTML_SAFE_KEYS` listesinde yer alır. Hangi yoldan gelirse gelsin, bu anahtarlara yazılan değerler DB'ye ulaşmadan önce sanitize edilir. Şu an takip edilenler:

- `general.welcome_message`

Gelecekte editor ile yazılan HTML tutan bir setting eklenirse listeye yeni kayıt düşün. Frontend tarafı için [FormBuilder Editor alan API'si](formbuilder.tr.md#editor-alan-apisi) bölümüne bakın.

## İyi Pratik

Yazma işlemlerini özel Action sınıflarında tutun ve doğrulama için request sınıflarını kullanın. Ayarlar arayüzü iş mantığının yerine geçmemeli, servis katmanını yansıtmalıdır.
