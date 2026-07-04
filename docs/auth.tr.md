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

Parola politikası, **Ayarlar → Güvenlik → Parola Politikası** yönetici sekmesi tarafından yönetilir. Kurallar `auth.*` ayar anahtarları olarak saklanır ve `PasswordValidationRules` tarafından runtime'da uygulanır.

| Ayar anahtarı | Uyguladığı kural |
|---|---|
| `auth.password_min_length` | Minimum karakter sayısı (varsayılan: `8`) |
| `auth.password_require_mixed_case` | Büyük ve küçük harf zorunlu |
| `auth.password_require_numbers` | En az bir rakam zorunlu |
| `auth.password_require_symbols` | En az bir sembol zorunlu |

Her Fortify akışı aktif kuralları otomatik devralır — kayıt, şifre sıfırlama, şifre onayı ve profil şifre güncelleme. Yönetici kullanıcı oluşturma/güncelleme akışları da aynı kuralları uygular.

Politika değiştiğinde mevcut kullanıcıların parolaları geçersiz olmaz — yalnızca yeni gönderilen parolalar güncel kurala karşı ölçülür.

### Parola geçerlilik süresi

`auth.password_expiry_days` değerini `0`'dan büyük bir değere ayarlamak `EnsurePasswordNotExpired` middleware'ini etkinleştirir. `password_changed_at` zaman damgası yapılandırılan gün sayısından daha eski olan kimlik doğrulanmış kullanıcılar, parolalarını güncelleyene kadar adanmış, guest tarzı bir parola-süresi-doldu ekranına (`password.expired` rotası) yönlendirilir. `0` değeri (varsayılan) geçerlilik süresini tamamen devre dışı bırakır.

`password_changed_at`, her parola yazımında otomatik olarak güncellenir: kayıt, şifre sıfırlama, profil güncelleme ve yönetici kullanıcı oluşturma/güncelleme. Mevcut kullanıcılar, migration çalıştığında `now()` değeriyle geri doldurulduğundan, deploy tarihinden itibaren geçerlilik sürecini başlatırlar; anında süresi dolmuş duruma düşmezler.

## Çalışma Zamanı Kuralları

- pasif kullanıcılar web oturumu başlatamaz; Fortify login pipeline'ı status değeri `active` olmayan hesapları engeller
- login denemeleri IP ve email/IP kombinasyonlarına göre rate-limit edilir; `auth.login_throttle = '1'` (varsayılan, sert limiter) olduğunda Fortify rate limiter aktiftir; Ayarlar → Güvenlik'ten `'0'` yapıldığında limiter tamamen kalkmaz, daha gevşek bir `login-relaxed` tabanına geçilir — hiçbir admin ayarı web login'ini tamamen limitsiz bırakamaz. API auth route'ları bu ayardan bağımsız kendi sabit `throttle:5,1` middleware'ini taşır.
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

## API İstemcileri ve Token'lar

Admin paneli, Passport OAuth2 istemcilerini ve Personal Access Token'ları (PAT) yönetmek için bir arayüz sunar:

- `/admin/api-clients` — OAuth2 istemcilerini listele, oluştur, güncelle ve sil
- `/admin/api-tokens` — Personal Access Token'ları yönet

İstemci secret'ları ve PAT değerleri yalnızca oluşturma anında dismiss edilemeyen bir modal içinde bir kez gösterilir; plaintext olarak hiçbir zaman saklanmaz. Ayrıntılı belge için bkz. [API İstemcileri ve Token'lar](./api-clients.tr.md).

## Notlar

- tarayıcı tarafındaki auth deneyimi için Fortify kullanın
- harici veya token tabanlı API tüketicileri için Passport kullanın
- aynı user model kullanılsa bile web ve API auth sorumluluklarını ayrı düşünün
