# API İstemcileri ve Token'lar

Admin paneli, Passport OAuth2 istemcilerini ve Personal Access Token'ları yönetmek için bir arayüz sunar. Bu arayüz `authorization_code` ve `client_credentials` grant türlerini ile API tüketicileri için PAT oluşturmayı kapsar.

## Admin Sayfaları

- `/admin/api-clients` — OAuth2 istemcilerini listele, oluştur, güncelle ve sil
- `/admin/api-tokens` — Personal Access Token'ları listele ve sil; kimliği doğrulanmış kullanıcı için yeni PAT oluştur

## İstemci Türleri

| Tür | Grant | Tipik Kullanım |
| --- | --- | --- |
| Web uygulaması | `authorization_code` | Kullanıcı adına hareket eden üçüncü taraf entegrasyonları |
| Makine-makine | `client_credentials` | API'yi kullanıcı bağlamı olmadan çağıran arka uç servisler |

## Güvenlik Kuralları

- Admin arayüzünden yalnızca `confidential=true` istemci oluşturulabilir. Public istemciler desteklenmez.
- `authorization_code` istemcileri en az bir redirect URI gerektirmektedir. Tüm redirect URI'lar için HTTPS zorunludur; RFC 8252 §8.3 istisnası olarak `http://localhost` ve `http://127.0.0.1` kabul edilir.
- Redirect URI'lar `HttpsOrLocalhostUrl` kuralıyla doğrulanır. Bu kontrolden geçemeyen URI, istemci kaydedilmeden önce reddedilir.

## Tek Seferlik Secret Gösterimi

İstemci secret'ları ve PAT plaintext değerleri, oluşturma işleminin hemen ardından `OneTimeSecretModal` içinde yalnızca bir kez gösterilir. Modal, kullanıcı değeri kopyaladığını açıkça onaylayana kadar kapatılamaz. Secret'ı taşıyan yanıt, tarayıcı önbelleğini engellemek için `Cache-Control: no-store` başlığı içerir.

Modal kapatıldıktan sonra değer bir daha görüntülenemez. Değer kaybolursa istemci secret'ı rotasyona alınmalı ya da yeni bir PAT oluşturulmalıdır.

## İzinler

Aşağıdaki izinler API istemcisi ve token admin sayfalarına erişimi denetler:

| İzin | Denetlenen İşlem |
| --- | --- |
| `api-clients.create` | Yeni OAuth2 istemcisi oluşturma |
| `api-clients.read` | İstemci listesini görüntüleme |
| `api-clients.update` | Mevcut istemciyi düzenleme |
| `api-clients.delete` | İstemciyi silme |
| `api-tokens.create` | Kimliği doğrulanmış kullanıcı için PAT oluşturma |
| `api-tokens.read` | Token listesini görüntüleme |
| `api-tokens.delete` | Token'ı iptal etme |

Güncelleme sonrasında bu izinleri `config/permission-resources.php` dosyasına ekleyin ve `php artisan sk:seed-permissions --fresh` komutunu çalıştırın.

## PAT Oluşturma

`POST /admin/api-tokens`, token'ı **yalnızca o an oturum açmış admin kullanıcısı** için oluşturur. `user_id` alanı, request body'de iletilse bile dikkate alınmaz. Başka bir kullanıcı adına PAT oluşturmak için artisan komutunu kullanın:

```bash
php artisan passport:client --personal
```

ya da bir seeder veya console command içinde `$user->createToken(...)` çağrısıyla programatik olarak token üretin.

## Güncelleme Notu

API istemcisi ve token yönetimi **v13.5.3** sürümüyle eklendi. Güncelleme sonrasında migration'ı çalıştırın ve izinleri yeniden seed'leyin:

```bash
php artisan migrate
php artisan sk:seed-permissions --fresh
```

v13.5.3'te yapılan değişikliklerin tam listesi için [CHANGELOG](./CHANGELOG.tr.md) sayfasına bakın.
