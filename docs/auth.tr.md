# Kimlik Doğrulama

Starter kit, web kimlik doğrulama için Laravel Fortify'ı ve API kimlik doğrulama için Passport'u birlikte kullanır.

## Web Kimlik Doğrulama

Hazır akışlar şunlardır:

- giriş
- kayıt
- şifremi unuttum
- şifre sıfırlama
- e-posta doğrulama
- şifre onayı
- iki faktör doğrulama ekranı

Bu ekranlar `resources/js/pages/Auth/` altında yer alır.

Turnstile ayarlardan etkinleştirildiğinde login, register ve forgot-password formları ortak `TurnstileWidget` bileşenini render eder ve `cf_turnstile_response` sunucu tarafında doğrulanır.

## Profil Güvenliği

Giriş yapmış kullanıcılar için ayrıca şu güvenlik araçları vardır:

- profil bilgisi güncelleme
- şifre güncelleme
- iki faktör ayarları
- recovery code görüntüleme ve yeniden üretme için şifre onayı
- tarayıcı oturum yönetimi
- avatar yükleme ve silme

Bu akışlar profil ekranı ve `routes/web/profile-route.php` içindeki ilişkili route'lar üzerinden çalışır.

## Parola Politikası

`App\Providers\AppServiceProvider::boot` içinde proje geneli bir `Password::defaults(...)` politikası kurulur. Default'a güvenen her Fortify akışı bunu otomatik devralır — register, password reset, password confirmation ve profil şifre güncelleme.

Mevcut politika:

- en az 10 karakter
- mixed case (büyük + küçük harf birlikte)
- en az bir harf, bir rakam ve bir sembol

Politika değiştiğinde mevcut kullanıcıların parolaları invalidate olmaz — yalnızca yeni parolalar güncel kurala karşı ölçülür. Kuralı gevşetmek/sıkılaştırmak için `Password::defaults(...)` closure'ını düzenleyin.

## Çalışma Zamanı Kuralları

- pasif kullanıcılar web oturumu başlatamaz; Fortify login pipeline'ı status değeri `active` olmayan hesapları engeller
- login denemeleri IP ve email/IP kombinasyonlarına göre rate-limit edilir
- iki faktör challenge akışının ayrı bir limiter'ı vardır
- iki faktör challenge'ı **tek kullanımlık** — yanlış kod, boş submit veya geçersiz recovery code challenge id'sini anında iptal eder; client yeni bir id almak için tekrar login olmak zorundadır
- forgot-password POST route'u, eşleşme anında dinamik olarak Turnstile middleware'i alır
- **API'de self-delete blokelidir.** `UserPolicy::delete` actor === target durumunda `false` dönüyor, yani `DELETE /api/v1/users/{self}` `users.delete` izni taşıyan kullanıcılar için bile 403 dönüyor. Desteklenen tek self-removal akışı Profile UI'daki password-confirmed Fortify yolu.

## API Kimlik Doğrulama

API tarafında Passport kullanılır:

- personal access token desteği
- `POST /api/v1/auth/register` ve `POST /api/v1/auth/login` public'tir ve throttle uygulanır
- `POST /api/v1/auth/two-factor-challenge` public'tir ve throttle uygulanır
- `POST /api/v1/auth/logout` ve `GET /api/v1/auth/me` için `auth:api` gerekir

### API Auth Akışı

- `register`, yalnızca email verification kapalıysa `201` ile `{ user, token }` döner
- email verification açıkken `register`, token vermeden `201` ile `{ user, requires_verification: true }` döner
- `login`, `{ user, token }`, `{ requires_verification: true }` veya `{ requires_two_factor: true, challenge }` şekillerinden birini dönebilir
- `two-factor-challenge`, API 2FA akışını `code` veya `recovery_code` ile tamamlar ve başarı durumunda `{ user, token }` döner
- istemciler, her başarılı auth yanıtında token beklemek yerine `requires_verification` ve `requires_two_factor` alanlarına göre dallanmalıdır

## Notlar

- tarayıcı tarafındaki auth deneyimi için Fortify kullanın
- harici veya token tabanlı API tüketicileri için Passport kullanın
- aynı user model kullanılsa bile web ve API auth sorumluluklarını ayrı düşünün
