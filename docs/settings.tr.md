# Ayarlar

Settings modülü, operasyonel yapılandırmayı admin panel içinde merkezi hale getirir.

## Bölümler

- `general` — uygulama adı, site gösterim saat dilimi fallback'i, aktif arayüz dilleri, logo yükleme/silme, admin dashboard hoş geldin mesajı (opsiyonel WYSIWYG)
- `auth` — kayıt, e-posta doğrulama, şifre sıfırlama, iki faktör kullanılabilirliği, giriş denemesi limiti ve parola politikası (minimum uzunluk, geçerlilik süresi, karmaşıklık kuralları)
- `mail` — mailer, SMTP host/port, kimlik bilgileri, gönderici adres/adı
- `storage` — media disk seçimi ve S3 uyumlu / AWS kimlik bilgileri
- `file_manager` — yükleme boyutu, kabul edilen MIME listesi, ses/video toggle'ları
- `turnstile` — özellik toggle'ı, site key ve secret key
- `api_integrations` — Postman ve Apidog senkronizasyon kimlik bilgileri; tek sekme içinde iki yapılandırma kartı
- `api_clients` — Passport OAuth2 istemcilerini listeleme, oluşturma, güncelleme ve silme
- `api_tokens` — Personal Access Token listeleme, iptal etme ve tek seferlik token oluşturma
- `system_health` — sistem yöneticileri için Settings içinde `sk:doctor` sonuçları

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
- `system-health.run` — **Settings → System Health** sekmesinden `POST /system-health/run`

## Çalışma Zamanı Notları

- `SettingsController@index`, gruplanmış settings payload'ını timezone seçenekleri ve tanımlı dil listesiyle birlikte döner
- **Genel** saat dilimi, `users.timezone` değeri `null` olan kullanıcıların site fallback'idir; açıkça saat dilimi seçen kullanıcı bu değeri override eder. Tam çözüm zinciri için [Saat Dilimleri](timezone.tr.md) belgesine bakın
- yazma akışları ince tutulur: FormRequest -> DTO -> Action
- `mail`, `storage` ve `turnstile` gruplarındaki secret alanlar artık saklanan değeri değil, `null` ve buna eşlik eden `*_is_set` bayraklarını döner
- secret alanı boş gönderildiğinde mevcut veritabanı veya config tabanlı değer korunur
- logo yükleme/silme akışı, ana `SkForm` dışında küçük bir JSON yan akışı olarak çalışır
- logo yükleme/silme uçları artık standart `ApiResponse` zarfını kullanır
- Auth sekmesinde iki faktörü kapatmak, değişiklik gönderilmeden önce yöneticiye onay sorar; çünkü kullanıcı güvenliğini etkiler
- Güvenlik sekmesi üç alt sekmeye bölünmüştür: **Kimlik Doğrulama** (kayıt, e-posta doğrulama, şifre sıfırlama, iki faktör, giriş denemesi limiti), **Parola Politikası** (minimum uzunluk, geçerlilik süresi gün sayısı, karmaşıklık toggle'ları) ve **Cloudflare Turnstile**
- `auth.login_throttle = '0'` Fortify giriş rate limiter'ını **devre dışı bırakmaz** — hiçbir admin ayarı web login'ini tamamen limitsiz bırakamaz. Bunun yerine sert `login` limiter'ını daha gevşek `login-relaxed` tabanına çevirir (`stubs/app/Providers/FortifyServiceProvider.php`'de tanımlı); varsayılan `'1'`'dir (sert limiter). Bu anahtar yalnızca **web** (Fortify) login'ini yönetir — API auth route'ları bu ayardan bağımsız sabit `throttle:5,1` middleware'i taşır.
- parola politikası ayarları (`password_min_length`, `password_require_mixed_case`, `password_require_numbers`, `password_require_symbols`) `PasswordValidationRules` aracılığıyla her yeni parolaya uygulanır; mevcut parolalar hiçbir zaman geçersiz olmaz
- `auth.password_expiry_days > 0` `EnsurePasswordNotExpired` middleware'ini etkinleştirir; `password_changed_at` değeri yapılandırılan gün sayısından daha eski olan kullanıcılar, parolalarını güncelleyene kadar adanmış, guest tarzı bir parola-süresi-doldu ekranına (`password.expired` rotası) yönlendirilir; `0` ayarı geçerlilik süresini devre dışı bırakır
- parola geçerlilik süresi muaf rotalar: parola-süresi-doldu sayfası (`password.expired`), çıkış, iki faktör challenge, Fortify parola uç noktaları — redirect döngüsü oluşamaz
- `users.password_changed_at` her parola yazımında güncellenir (kayıt, sıfırlama, profil güncelleme, yönetici kullanıcı oluşturma/güncelleme); mevcut kullanıcılar migration sırasında `now()` değeriyle geri doldurulmuştur
- Turnstile ayarları login, register ve forgot-password formlarındaki challenge davranışını besler
- API entegrasyon ayarları Postman ve Apidog sync kimlik bilgilerini saklar; secret alanlar diğer sırlar gibi şifrelenir ve `*_is_set` bayraklarını kullanır
- API client ve token yönetimi, Passport admin route'larıyla çalışan ayrı Settings sekmeleridir; yeni oluşturulan secret/token değerleri yalnızca bir kez gösterilir ve sonradan geri getirilemez
- System Health bir Settings sekmesi olarak render edilir ve raporunu `sk:doctor --json` çıktısından alır
- test-mail hataları sunucu tarafında loglanır ve ham SMTP exception detayı kullanıcıya gösterilmez
- **Genel** sekmesindeki `welcome_message` alanı `FB.editor()` ile yazılır; içerik hem yazılırken (FormRequest `prepareForValidation` hook'u) hem okunurken (DashboardController defense-in-depth geçişi) `App\Support\HtmlSanitizer` üzerinden sanitize edilerek admin dashboard'da render edilir
- `SettingService::setValue()` / `setGroup()` fonksiyonları `HTML_SAFE_KEYS` whitelist'indeki anahtarları `HtmlSanitizer::clean()`'den geçirir — FormRequest, tinker, scheduled command ve queue job'lar aynı sanitizer'ı kullanır, böylece normal setting API'si üzerinden sanitize edilmemiş HTML DB'ye yazılamaz

## Auth ayar anahtarları

`auth` grubu aşağıdaki anahtarları sunar. Her kurulum için iki farklı default değer geçerlidir:

- **Seeder (yeni kurulum)** — `sk:install` sırasında `_03_SettingSeeder` tarafından yazılan değer. Yalnızca yeni kurulumlar için geçerlidir; seeder var olan bir satırın üzerine yazmaz.
- **Runtime fallback (anahtar DB'de yokken)** — anahtarın veritabanında bulunmadığı durumda `SettingsDefaultsQuery::auth()` tarafından kullanılan değer. Yükseltme yapan kurulumlar seeder çalıştırmadan önce bu değerleri kullanır. Fallback'ler v13.6.0'da getirilen sertleştirilmiş baseline'ı yansıtır: daha önce hiç seed edilmemiş kurulumlar için `email_verification` ve `two_factor` okuma yolunda etkin (`'1'`) olarak varsayılır.

| Anahtar | Tür | Seeder (yeni kurulum) | Runtime fallback (anahtar yokken) | Açıklama |
|---|---|---|---|---|
| `registration` | boolean | `'1'` | `'1'` | Yeni kullanıcıların kendileri kayıt olmasına izin ver |
| `password_reset` | boolean | `'1'` | `'1'` | E-posta tabanlı şifre sıfırlamaya izin ver |
| `email_verification` | boolean | `'0'` | `'1'` | Giriş öncesi e-posta doğrulama zorunlu |
| `two_factor` | boolean | `'0'` | `'1'` | İki faktörlü kimlik doğrulamayı etkinleştir |
| `login_throttle` | boolean | `'1'` | `'1'` | `'1'` = sert Fortify `login` limiter; `'0'` = gevşek `login-relaxed` tabanı (asla tamamen kapanmaz) |
| `password_min_length` | integer (string) | `'10'` | `10` | Minimum parola uzunluğu |
| `password_expiry_days` | integer (string) | `'0'` | `0` | Parolanın geçerlilik süresi (gün); `0` = sınırsız |
| `password_require_mixed_case` | boolean | `'1'` | `'1'` | Parolada büyük ve küçük harf zorunlu |
| `password_require_numbers` | boolean | `'1'` | `'1'` | Parolada en az bir rakam zorunlu |
| `password_require_symbols` | boolean | `'1'` | `'1'` | Parolada en az bir sembol zorunlu |

Tüm değerler veritabanında string olarak saklanır. `SettingsDefaultsQuery::auth()` metodu, frontend veya uygulama katmanına ulaşmadan önce bunları doğru PHP tiplerine dönüştürür.

## HTML-safe anahtarlar

Zengin metin içeriği tutan setting anahtarları `SettingService::HTML_SAFE_KEYS` listesinde yer alır. Hangi yoldan gelirse gelsin, bu anahtarlara yazılan değerler DB'ye ulaşmadan önce sanitize edilir. Şu an takip edilenler:

- `general.welcome_message`

Gelecekte editor ile yazılan HTML tutan bir setting eklenirse listeye yeni kayıt düşün. Frontend tarafı için [FormBuilder Editor alan API'si](formbuilder.tr.md#editor-alan-apisi) bölümüne bakın.

## İyi Pratik

Yazma işlemlerini özel Action sınıflarında tutun ve doğrulama için request sınıflarını kullanın. Ayarlar arayüzü iş mantığının yerine geçmemeli, servis katmanını yansıtmalıdır.
