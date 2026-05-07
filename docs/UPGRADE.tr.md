# UPGRADE — Lvntr Starter Kit

Bu dosya büyük sürümler arası geçiş rehberidir. Her sürüm kendi bölümünü taşır; son sürüm en üstte. Küçük hata düzeltmeleri yalnız `CHANGELOG.md`'de listelenir — bu dosyaya sadece **publish edilmiş** (yani `sk:install` ile user app'ine kopyalanan) dosyalara dokunan değişiklikler girer, çünkü bu tip değişiklikleri `composer update` tek başına taşımaz.

---

## v13.5.0 → v13.5.3

### Özet

Bu sürümde `sk:doctor` / System Health paneli, File Manager için İmzalı Paylaşım Bağlantıları (Signed Share Link), Bulk Action API sertleştirmesi ve API İstemci UI eklendi. Yeni migration, config key ve permission adımları zorunludur.

### Yükseltme Adımları

**1. Paketi güncelleyin:**

```bash
composer update lvntr/laravel-starter-kit
```

**2. Yeni migration'ları yayınlayın ve çalıştırın:**

```bash
php artisan vendor:publish --tag=starter-kit-migrations
php artisan migrate
```

Yeni migration: `file_manager_share_revocations` tablosu (İmzalı Paylaşım Bağlantısı iptali için zorunlu).

**3. File Manager config'ini güncelleyin (yeni `share.*` key'leri):**

```bash
php artisan vendor:publish --tag=starter-kit-config --force
```

`config/file-manager.php` dosyasına şu key'ler eklenir: `share.enabled`, `share.default_ttl_hours`, `share.max_ttl_hours`, `share.allow_revoke`. Mevcut key'ler etkilenmez.

**4. Yeni stub'ları yayınlayın (DİKKAT: özelleştirilmiş stub'lar override edilir, önce diff alın):**

```bash
php artisan vendor:publish --tag=starter-kit-stubs --force
```

**5. Yeni izinleri seed'leyin ve cache'i temizleyin:**

```bash
php artisan db:seed --class=PermissionResourcesSeeder
php artisan permission:cache-reset
```

Yeni izinler: `system.health.view`, `share-media`, `revoke-share-media`, `api-clients.create`, `api-clients.read`, `api-clients.update`, `api-clients.delete`, `api-tokens.create`, `api-tokens.read`, `api-tokens.delete`

### Davranış Değişiklikleri

- **Passport istemci UI** — `confidential=false` olan authorization-code client'ları artık UI üzerinden oluşturulamaz. Mevcut DB kayıtları etkilenmez.
- **Personal Access Token mint** — `user_id` body alanı kaldırıldı. Başkası adına PAT oluşturmak için artisan komutunu kullanın.
- **`AppServiceProvider` stub** — varsa duplicate Passport scope / `Gate::before` bloğunu kaldırın; `StarterKitServiceProvider` bunları kaydetmeye devam eder.
- **`BulkActionRequest`** — ID'ler artık `string|min:1|max:64` kuralıyla doğrulanıyor. Mevcut integer-only bulk action'lar etkilenmez.

---

## v13.4.x → v13.5.0

### Özet

Bu sürümde paket runtime vendor'a taşındı. `app/` dizinindeki mevcut dosyalarınız **değişmez**; olduğu gibi çalışmaya devam eder. `composer update` tek zorunlu adımdır.

### Yükseltme Adımları

```bash
composer update lvntr/laravel-starter-kit
php artisan migrate
```

`php artisan migrate` komutu "Nothing to migrate" döner çünkü mevcut migration history'niz bu sürümün vendor migration dosyalarıyla birebir eşleşiyor.

#### Opsiyonel adımlar

```bash
# Wayfinder typed route dosyalarını yenile (diff beklenmez)
php artisan wayfinder:generate

# Stub güncellemelerini kontrol et (hash değişmişse bildirir, zorlamaz)
php artisan sk:update --dry-run
```

### Ne Değişmez

| Alan | Durum |
|------|-------|
| `app/Domain/FileManager/` dosyaları | Korunur, silinmez |
| `app/Domain/Shared/` dosyaları | Korunur, silinmez |
| `app/Traits/HasActivityLogging.php` | Korunur |
| `app/Traits/HasMediaCollections.php` | Korunur |
| `app/Helpers/sk-helpers.php` | Korunur, fonksiyonlarınız baskın kalır |
| `app/Http/Responses/ApiResponse.php` | Korunur |
| `app/Http/Middleware/CheckResourcePermission.php` | Korunur |
| Route isimleri (`file-manager.*`) | 19 route adı AYNEN |
| Migration history | "Nothing to migrate" |
| Config key'leri (`starter-kit.*`, `file-manager.*`) | Mevcut key'ler korundu |
| Frontend `@lvntr` alias | Dokunulmadı |
| Permission key'leri (`files.read`, `files.update`, vb.) | AYNEN |
| API response envelope (`success`, `status`, `message`, `data`) | AYNEN |

### Opsiyonel Cleanup

#### Backend dosyaları (vendor'a taşıma)

`app/Domain/FileManager/`, `app/Domain/Shared/` gibi dosyalar artık vendor'dan da çalışıyor. Eğer bu dosyaları uygulamanızdan kaldırıp vendor versiyonunu kullanmak istiyorsanız adım adım rehber için bakın:

`docs_project/migrate-existing-project-to-vendor.tr.md` (uygulama worktree'sinde)

Bu adım tamamen isteğe bağlıdır ve hemen yapılması gerekmez.

#### Frontend (vendor symlink'e geçiş)

App'te `resources/js/components/Lvntr-Starter-Kit/` klasörü hâlâ duruyorsa ve özel customization yoksa, vendor frontend'ine geçebilirsiniz:

1. **Vite alias** — `vite.config.ts` içinde `@lvntr/components` alias'ını vendor path'e yönlendirin:

   ```ts
   '@lvntr/components': path.resolve(__dirname, 'vendor/lvntr/laravel-starter-kit/resources/js/components/Lvntr-Starter-Kit'),
   ```

   `Components({ dirs })` plugin array'ine vendor path ekleyin:

   ```ts
   dirs: [
     'resources/js/components',
     'vendor/lvntr/laravel-starter-kit/resources/js/components/Lvntr-Starter-Kit',
   ],
   ```

   `preserveSymlinks: true` olduğundan emin olun.

2. **App kopyasını silin**:

   ```bash
   rm -rf resources/js/components/Lvntr-Starter-Kit
   ```

3. **Build smoke**:

   ```bash
   npm run build
   ```

   Exit 0 olmalı.

Customize edilmiş component'iniz varsa silmeyin; kendi `resources/js/components/<X>` altında app-specific bileşenlerinizi tutarken vendor lib'i import edebilirsiniz.

#### sk:sync deprecation

`php artisan sk:sync` deprecated oldu. Composer path repository (symlink) workflow'u kullananlar için gerekmiyordu zaten. `--force` ile eski davranış korunur ama önerilmez.

### sk:update Çıktısı

Bu sürümden itibaren `sk:update`, vendor'a taşınan runtime dosyalar için kopyalama yapmaz. Çıktıda şuna benzer bir bilgi mesajı görürsünüz:

```
[INFO] v13.5.0+: Aşağıdaki dosyalar artık vendor'da çalışıyor.
       Silmek opsiyonel:
         app/Domain/FileManager/
         app/Domain/Shared/{Actions,Contracts,DTOs,Pipelines}
         app/Traits/HasActivityLogging.php
         app/Traits/HasMediaCollections.php
         app/Helpers/sk-helpers.php
         app/Http/Responses/ApiResponse.php
         app/Http/Middleware/CheckResourcePermission.php
         app/Http/Middleware/SecurityHeaders.php
         app/Exceptions/ApiException.php
         app/Exceptions/ApiExceptionHandler.php
         app/Http/Controllers/FileManagerController.php
         app/Http/Requests/FileManager/
         app/Console/Commands/PurgeFileManagerTrash.php
```

Hash takipli stub'lar (auth/layout/user/rol/ayar/config) için mevcut diff/bildirim davranışı korundu.

### Yeni Install (v13.5.0+)

Yeni bir projede `php artisan sk:install` koştuğunuzda `app/Domain/FileManager/`, `app/Domain/Shared/`, `app/Traits/`, `app/Helpers/sk-helpers.php`, `app/Http/Responses/ApiResponse.php`, `app/Http/Middleware/CheckResourcePermission.php` dosyaları artık `app/` dizinine kopyalanmıyor. Bu modüller doğrudan `vendor/lvntr/laravel-starter-kit/src/` altından çalışıyor.

Uygulamaya publish edilen dosyalar: auth/layout Vue bileşenleri, User/Role/Setting domain iskeleti, config dosyaları, tek satır route stub'ları.

---

## v13.4.8 → v13.4.9

Bkz. [CHANGELOG.md](../CHANGELOG.md#13490---2026-05-02).

Kısa geçiş:

```bash
composer update lvntr/laravel-starter-kit
php artisan sk:update
php artisan migrate
npm install
npm run build
```

---

## v13.4.x → v13.4.10

Bkz. [CHANGELOG.md](../CHANGELOG.md#134100---2026-05-04).

```bash
composer update lvntr/laravel-starter-kit
php artisan sk:update
php artisan migrate
npm install
npm run build
```

---

## 13.4.0 → 13.4.1 — API response sertleştirme + Postman/Apidog sync + OAuth UUID fix

> **Özet:** Bu patch, baştan sona elden geçirilen API response zarfını (trace-id pipeline, merkezi exception handler, leak kapatan controller patch'leri) iki yeni API client entegrasyonu (Postman + Apidog sync) ve iki adet kurulum fix'i (OAuth migration'ları UUID-uyumlu, `site:install` artık Passport personal access client'ı otomatik oluşturuyor) ile birleştiriyor. Çoğu değişiklik **additive** (yeni alan/header, yeni admin butonları) ama **üç adet API-response davranışsal breaking** noktası UI toast metinlerini veya katı client schema'larını etkileyebilir: `abort()` raw mesaj whitelist'i + `ModelNotFoundException` mesaj formatı + `Api/AuthController` ham User → UserResource geçişi.

### 0. Kimi etkiler?

| Kullanıcı | Ne yapmalı |
| --- | --- |
| Paketi yeni kuranlar (`composer create-project` + `sk:install`) | Hiçbir şey — stubs zaten 13.4.1 sürümünde. |
| `sk:update` düzenli çalıştıranlar | `composer update` + `php artisan sk:update`. `ApiResponse`, `ApiExceptionHandler`, `AssignTraceId`, `sk-helpers.php` otomatik taşınır; **controller'lar manuel** (Adım 4). |
| Custom controller'lara sahip olanlar | Adım 4'teki patch'leri elle uygulayın — özellikle `catch (LogicException $e) → throw ApiException::...` pattern dönüşümü. |
| Sadece paket `src/` kullananlar (publish yapmadı) | `composer update lvntr/laravel-starter-kit` yeter; Bootstrap otomatik register ediyor. |
| Kendi `app/Http/Middleware/AssignTraceId.php` yazmış olanlar | Sınıf adı çakışır; paket stub'ını tercih edin veya kendi class'ınızı yeniden adlandırın. |

### 1. Upgrade öncesi hazırlık

1. **Branch + yedek:** `git checkout -b upgrade/v13.4.1 && git push`
2. **Frontend/mobile takım:** API response formatındaki additive alanları (`trace_id` body, `X-Request-ID` header, `X-Correlation-ID` echo, 429 `Retry-After`) onlara haber verin — strict şema kullananlar eklesinler.
3. **QA:** Hata mesajları UI'da toast olarak gösteriliyorsa, **Adım 2'deki davranışsal breaking**'leri kısa bir QA pass'inden geçirin (abort() mesajları, model-not-found mesaj formatı, auth me/login response şekli).
4. **Ortam kontrolü:** `composer test` + `npm run build` mevcut sürümde geçiyor mu?

### 2. Davranışsal breaking değişiklikler

Status kodları değişmedi; zarf alan listesi değişmedi; sadece **`message` metni** ve **auth payload içindeki `data.user` alan listesi** etkilenebilir.

#### 2.1 `abort($code, 'custom message')` artık mesajı dışa sızdırmıyor

```diff
- // Eskiden: body.message = "SQL error: table users missing col xyz"
- abort(400, 'SQL error: table users missing col xyz');
+ // Artık: body.message = "Bad request."  (iç detay düşer)
+ abort(400, 'SQL error: ...');   // Bu mesaj artık client'a gitmez.
```

**Neden:** `HttpExceptionInterface` dalı artık `$e->getMessage()` yerine sabit `defaultMessageForStatus()` kullanıyor (K3). İç mesajlar `APP_DEBUG=true` iken `debug.message` alanına düşer.

**Geçiş yolu:** Client'a mesaj göstermek istediğiniz kontrollü durumlarda:

```php
// Eski
abort(400, 'Invalid coupon code.');

// Yeni (handler'dan geçer, trace_id + correlation headers eklenir)
throw \App\Exceptions\ApiException::badRequest('Invalid coupon code.');
```

#### 2.2 `ModelNotFoundException` mesajı model adını içeriyor

```diff
- body.message: "The requested resource was not found."
+ body.message: "User not found."          // veya Role, Product, …
```

**Neden:** `ApiExceptionHandler::modelNotFoundMessage` artık `class_basename($e->getModel())` ile resolve ediyor (K4 — önceki AGENTS.md vaadini karşılıyor). Güvenlik etkisi yok — model sınıf adı zaten URL'den tahmin edilebilir.

**Geçiş yolu:** Frontend'de message string'ine karşı eşleşme yapan kod varsa regex'i gevşetin (`/(not found|bulunamadı)/i` gibi) veya status kodu (404) üzerinden dallanın.

#### 2.3 `Api/AuthController` ham User → `UserResource`

```diff
  POST /api/v1/auth/login (default kind)
  POST /api/v1/auth/register (no-verification path)
  POST /api/v1/auth/two-factor-challenge
  GET  /api/v1/auth/me

- data.user: {
-     id: 1, first_name: "...", email: "...",
-     status: "active", email_verified_at: "...",
-     two_factor_confirmed_at: null,
-     avatar_url: "...", created_at: "...", updated_at: "..."
- }
+ data.user: <UserResource::toArray() çıktısı, app/Http/Resources/Admin/User/UserResource.php>
```

**Neden:** Ham Eloquent serializasyonu `$hidden`'a güvenmek zorundaydı; gelecekte eklenen hassas bir alan unutulsa sessizce sızardı. `UserResource` artık kontrat — hangi alan client'a gidiyor açıkça yazılı.

**Geçiş yolu:** `UserResource`'un döndürdüğü alan listesini kontrol edin (`app/Http/Resources/Admin/User/UserResource.php`). Ham model'in vardı ama Resource'ta olmayan alana bağımlıysanız, Resource'a ekleyin veya kendi `AuthUserResource` yazıp AuthController'da kullanın.

### 3. Paket güncellemesi

```bash
composer update lvntr/laravel-starter-kit --with-all-dependencies
php artisan sk:update              # Otomatik: ApiResponse + ApiExceptionHandler + sk-helpers + AssignTraceId
npm install                         # Değişmedi ama alışkanlık
```

`sk:update` çıktısı şu dosyaları otomatik günceller:
- `app/Http/Responses/ApiResponse.php`
- `app/Exceptions/ApiExceptionHandler.php`
- `app/Helpers/sk-helpers.php`
- `app/Http/Middleware/AssignTraceId.php` (**yeni** — dosya yoksa oluşturulur)
- `app/Http/Middleware/SecurityHeaders.php` (dokunulmadı ama listede)

> **Önemli:** `AssignTraceId.php` dosyası `sk:update` sonrası mevcut değilse, paket `Bootstrap::middleware()` `App\Http\Middleware\AssignTraceId` sınıfına referans veriyor ve **ilk API request'te ClassNotFoundException atar**. `sk:update` başarılı olduysa sorun yok; emin olmak için: `ls app/Http/Middleware/AssignTraceId.php`.

### 4. Manuel controller patch'leri (publish edilmiş custom'lar için)

`sk:update` controller'ları otomatik güncellemez — birçok projede custom metodlar eklenmiş oluyor. Aşağıdaki 11 leak pattern'ini elle temizleyin. Pattern evrensel:

```diff
- catch (LogicException $e) {
-     return to_api(null, $e->getMessage(), 422);
- }
+ catch (LogicException $e) {
+     throw \App\Exceptions\ApiException::unprocessable($e->getMessage());
+ }
```

**Etkilenen dosyalar:**

| Dosya | Satır / metot |
|---|---|
| `app/Http/Controllers/FileManagerController.php` | `bulkDelete`, `createFolder`, `renameFolder`, `moveItem`, `deleteFolder`, `upload`, `deleteFile` — 7 adet |
| `app/Http/Controllers/Api/UserController.php` | `destroy` — `to_api(null, 'Unauthenticated.', 401)` → `throw ApiException::unauthorized()`; `to_api(null, $e->getMessage(), 400)` → `throw ApiException::badRequest(...)` |
| `app/Http/Controllers/Api/Auth/AuthController.php` | `login` — `to_api(null, 'Invalid email or password.', 401)` → `throw ApiException::unauthorized(...)`; `twoFactorChallenge` — aynısı "Invalid or expired two-factor code." için |

Her controller'ın başına `use App\Exceptions\ApiException;` eklemeyi unutmayın. Son olarak `destroy`'dakine benzer yerlerde `return to_api(status: 204);` `try` bloğunun **dışına** taşınır (Adım 2'deki çıkış akışı değişimi):

```diff
- try {
-     $action->execute($user, (string) $performedById);
-     return to_api(status: 204);
- } catch (\LogicException $e) {
-     return to_api(null, $e->getMessage(), 400);
- }
+ try {
+     $action->execute($user, (string) $performedById);
+ } catch (\LogicException $e) {
+     throw ApiException::badRequest($e->getMessage());
+ }
+
+ return to_api(status: 204);
```

### 5. Api/AuthController UserResource geçişi (publish edilmişse)

Adım 2.3'te anlatılan davranış değişimini uygulamak için `Api/Auth/AuthController.php` patch'i:

```diff
 use App\Domain\Auth\Actions\TwoFactorChallengeAction;
 use App\Domain\Auth\DTOs\LoginDTO;
 use App\Domain\Auth\DTOs\RegisterDTO;
+use App\Exceptions\ApiException;
 use App\Http\Controllers\Controller;
 use App\Http\Requests\Api\Auth\LoginRequest;
 use App\Http\Requests\Api\Auth\RegisterRequest;
 use App\Http\Requests\Api\Auth\TwoFactorChallengeRequest;
+use App\Http\Resources\Admin\User\UserResource;
 use App\Http\Responses\ApiResponse;

 public function register(...): ApiResponse
 {
     $result = $action->execute(...);
+    $userPayload = new UserResource($result['user']->loadMissing('roles'));

     if ($result['requires_verification']) {
         return to_api(
-            ['user' => $result['user'], 'requires_verification' => true],
+            ['user' => $userPayload, 'requires_verification' => true],
             'Registration successful. ...',
             201,
         );
     }

-    return to_api($result, 'Registration successful.', 201);
+    return to_api(
+        ['user' => $userPayload, 'token' => $result['token'], 'requires_verification' => false],
+        'Registration successful.',
+        201,
+    );
 }

 // login default branch
-    default => to_api(
-        ['user' => $result['user'], 'token' => $result['token']],
-        'Login successful.',
-    ),
+    default => to_api(
+        [
+            'user' => new UserResource($result['user']->loadMissing('roles')),
+            'token' => $result['token'],
+        ],
+        'Login successful.',
+    ),

 // me
-    return to_api($request->user());
+    return to_api(new UserResource($request->user()->loadMissing('roles')));

 // twoFactorChallenge
-    return to_api($result, 'Login successful.');
+    return to_api(
+        [
+            'user' => new UserResource($result['user']->loadMissing('roles')),
+            'token' => $result['token'],
+        ],
+        'Login successful.',
+    );
```

### 6. MakeDomainCommand scaffold (publish edilmişse)

`app/Console/Commands/MakeDomainCommand.php` publish edilmişse, yeni scaffold template'i için iki nokta:

```diff
 use {$dtoNamespace}\\{$this->dn}DTO;
+use App\Exceptions\ApiException;
 use App\Http\Controllers\Controller;
 ...

 public function destroy({$this->dn} \${$v}, Delete{$this->dn}Action \$action): ApiResponse|JsonResponse
 {
     try {
         \$action->execute(\${$v});
-
-        return to_api(status: 204);
     } catch (\LogicException \$e) {
-        return to_api(null, \$e->getMessage(), 400);
+        throw ApiException::badRequest(\$e->getMessage());
     }
+
+    return to_api(status: 204);
 }
```

Testiniz `tests/Feature/Console/MakeDomainCommandTest.php`'de yeni scaffold'u doğruluyorsa assertion güncellenmeli:

```diff
 expect(file_get_contents(app_path("Http/Controllers/Api/{$domain}Controller.php")))
-    ->toContain('return to_api(null, $e->getMessage(), 400);');
+    ->toContain('throw ApiException::badRequest($e->getMessage());');
```

### 7. Kurulum zamanı fix'leri (OAuth + Postman ayarları + Passport personal client)

Bu üç adım, **13.4.1 öncesi kurulmuş tüm mevcut install'lar** için geçerli. API response işinden bağımsız çalışır — controller publish etsen de etmesen de `sk:update` sonrası çalıştır.

#### 7.1 OAuth migration'ları UUID-uyumlu hale getirildi

Üç Passport migration'ı artık `foreignUuid` / `nullableUuidMorphs` kullanıyor (önceden `foreignId` / `nullableMorphs`). Bu, starter kit'in `users.id` için gönderdiği `char(36)` primary key ile eşleşiyor. Patch uygulanmazsa Passport ilk access token insert denemesinde `SQLSTATE 1265: Data truncated for column 'user_id'` hatasıyla API login akışını bozar.

Taze kurulumlar bunu `site:install` sırasında otomatik alır. **Mevcut install'lar için** üç migration'ı canlı veri üzerinde yeniden çalıştır:

```bash
# 1. Üç migration'ı rollback et (veri kaybı yok — oauth_* tabloları
#    her token üretimde yeniden doluyor):
php artisan migrate:rollback --path=database/migrations/2026_03_04_205119_create_oauth_auth_codes_table.php
php artisan migrate:rollback --path=database/migrations/2026_03_04_205120_create_oauth_access_tokens_table.php
php artisan migrate:rollback --path=database/migrations/2026_03_04_205122_create_oauth_clients_table.php

# 2. Yeni şema ile yeniden çalıştır:
php artisan migrate
```

Rollback mümkün değilse (schema fork'unda zaten `char(36)` user_id satırları varsa), kolonu manuel olarak düzelt:

```sql
ALTER TABLE oauth_access_tokens MODIFY user_id CHAR(36) NULL;
ALTER TABLE oauth_auth_codes    MODIFY user_id CHAR(36) NOT NULL;
ALTER TABLE oauth_clients       MODIFY owner_id CHAR(36) NULL;
```

Bir login testiyle doğrula — Adım 9 (Regresyon testi).

#### 7.2 Postman / Apidog kimlik bilgileri `.env`'den ayarlar tablosuna taşındı

Postman'i üç `.env` anahtarıyla bağlayan önceki versiyon kaldırıldı. Yapılandırma artık `postman` / `apidog` settings gruplarında duruyor ve `api_key` / `access_token` alanları `config/settings.php → sensitive_keys` aracılığıyla DB'de encrypted tutuluyor.

`.env` içinde `POSTMAN_API_KEY`, `POSTMAN_WORKSPACE_ID` veya `POSTMAN_COLLECTION_ID` varsa bir kerelik olarak ayarlar tablosuna taşı, sonra `.env`'den sil:

```bash
php artisan tinker --execute '
use App\Models\Setting;
Setting::setValue("postman.api_key", env("POSTMAN_API_KEY"));
Setting::setValue("postman.workspace_id", env("POSTMAN_WORKSPACE_ID"));
Setting::setValue("postman.collection_id", env("POSTMAN_COLLECTION_ID"));
echo "migrated";
'
```

Ardından her iki dosyadan (`.env` ve `.env.example`) üç anahtarı da sil. Admin UI'da **Settings → API Clients → Postman** saklanan değerleri gösterir (gizli alanlar maskeli); anahtarı ileride rotate etmek için buradan yönet. Apidog aynı şekilde **Settings → API Clients → Apidog** üzerinden yapılandırılır (Access Token + Project ID).

#### 7.3 Passport personal access client (`site:install` içindeki yeni adım)

`site:install` artık `passport:keys` ile admin-user seed adımları arasında `passport:client --personal --provider=users`'ı otomatik çalıştırıyor. Mevcut install'ında personal access client yoksa (belirti: API login'de `RuntimeException: Personal access client not found for 'users'`), bir kerelik oluştur:

```bash
php artisan passport:client --personal --provider=users --name="$(php artisan config:show app.name)" --no-interaction
```

`oauth_clients` tablosuna `revoked=0` olan tek bir satır düşer. API token üretimi anında çalışmaya başlar — uygulama yeniden başlatmaya gerek yok.

### 8. Yeni additive özellikler — kod değişikliği gerekmez

Bu özellikler **otomatik devreye girer**, client'a yeni alanlar/header'lar gelir. Frontend takımını bilgilendirin:

| Özellik | Nerede görünür |
|---|---|
| `trace_id` (UUID) | Her JSON body (success ve error), ayrıca `X-Request-ID` header |
| `X-Correlation-ID` | Client `X-Request-ID` gönderirse sanitize edilip echo'lanır |
| `Retry-After` | 429 Too Many Requests response'ta |
| `simplePaginate()` desteği | `to_api(Model::simplePaginate(...))` artık type error vermeden çalışır; `meta.has_more` verir |
| "Postman'e Gönder" butonu | API Rotaları sayfası → yapılandırma tamamsa OpenAPI spec'ini Postman'e push eder |
| "Apidog'a Gönder" butonu | API Rotaları sayfası → yapılandırma tamamsa OpenAPI spec'ini Apidog'a push eder |
| Settings → API Clients tabı | Postman + Apidog kimlik bilgileri; `postman.api_key` / `apidog.access_token` DB'de encrypted |

### 9. Regresyon testi — opsiyonel ama tavsiye edilir

Paket `tests/Feature/Api/ApiResponseTest.php` içinde envelope şekli + exception mapping + trace_id + 204 + Retry-After + debug guard için 16 testlik bir kontrat dosyası shipliyor. App'inizde yoksa şuradan kopyalayın:

```bash
cp vendor/lvntr/laravel-starter-kit/tests/examples/ApiResponseTest.php \
   tests/Feature/Api/ApiResponseTest.php
php artisan test --compact --filter=ApiResponseTest
```

Beklenen: 16 test, 57 assertion geçer. Fail olursa test API middleware group'ta `AssignTraceId`'in aktif olup olmadığını kontrol edin.

### 10. Geri alma (rollback)

Sürüm geri çevrilirse:

```bash
git revert <upgrade-commit>
composer install
php artisan sk:update --force   # publish edilmiş dosyaları eski sürüme döndürür
```

`AssignTraceId.php` dosyası 13.4.x'te yoktu — rollback'ten sonra silin veya `Bootstrap.php`'nin eski sürümü sınıfı referans etmiyorsa bırakın (no-op).

---

## 13.3.x → 13.4.0 — Güvenlik hardening sprint'i

> **Özet:** Üç-katlı paralel kod inceleme bulguları sonrası ~37 bulgu kapatıldı (HIGH: 13 → 1 manuel, MEDIUM: 14, LOW: 4). Değişikliklerin büyük kısmı güvenlik (auth bypass, brute-force, XSS, log injection) ve veri bütünlüğü (DB transaction eksiklikleri). Yeni kurulumlar bu düzeltmeleri otomatik alır; **mevcut kurulumlar** bu dokümandaki patch listesini uygulamalıdır.

### 0. Kimi etkiler?

| Kullanıcı | Ne yapmalı |
| --- | --- |
| Paketi yeni kuranlar (`composer create-project` + `sk:install`) | Hiçbir şey — stubs zaten yeni sürümde. |
| Mevcut consumer app çalıştıranlar | Bu dokümandaki **Adım 1-8**'i takip edin. |
| Sadece paket `src/` kullananlar (publish yapmadı) | `composer update lvntr/laravel-starter-kit` yeter. |

### 1. Upgrade öncesi hazırlık

1. **Branch + yedek:** `git checkout -b upgrade/v13.4.0 && git push`
2. **DB yedeği:** Production için snapshot / dump.
3. **Ortam kontrolü:** `composer test` + `npm run build` mevcut sürümde geçiyor mu?
4. **PR sürecine dahil edin:** Bu güncellemenin büyük kısmı patch-stili; code review gerektirir.

### 2. Paket güncellemesi

```bash
composer update lvntr/laravel-starter-kit --with-all-dependencies
npm install
```

Bu adım Tier-1 değişiklikleri (paket `src/` içi) otomatik taşır:
- `SecurityHeaders` HSTS `preload` direktifi (`src/Http/Middleware/SecurityHeaders.php`)
- `MakeDomainCommand` / stub iyileştirmeleri

Kalan tüm değişiklikler publish edilmiş dosyalarda olduğu için **sizin app'inizdeki kopyayı** güncellemeniz gerekiyor.

---

### 3. HIGH — Güvenlik ve veri bütünlüğü patch'leri

Bunları **aynı sırada** uygulayın. Her biri bağımsız olarak çalışır ama sıralı commit temiz bir history oluşturur.

#### 3.1 (BE-H1) `UserPolicy::delete` + `Api\UserController::destroy` null guard

**Dosya:** `app/Policies/UserPolicy.php`

`delete()` metodundaki self-match dalını değiştirin:

```diff
     public function delete(User $actor, User $user): bool
     {
         if ($actor->is($user)) {
-            return true;
+            return false;
         }

         if (! $this->canManage($actor, $user)) {
             return false;
         }

         return $actor->can('users.delete');
     }
```

**Dosya:** `app/Http/Controllers/Api/UserController.php`

`destroy` metoduna null guard ekleyin:

```diff
     public function destroy(Request $request, User $user, DeleteUserAction $action): ApiResponse|JsonResponse
     {
         Gate::authorize('delete', $user);

+        $performedById = $request->user()?->id;
+        if ($performedById === null) {
+            return to_api(null, 'Unauthenticated.', 401);
+        }
+
         try {
-            $action->execute($user, (string) $request->user()?->id);
+            $action->execute($user, (string) $performedById);
             return to_api(status: 204);
```

**Test:** `DELETE /api/v1/users/{kendi_id}` 403 dönmeli (policy'de reddedildi), expired token ile 401 dönmeli.

---

#### 3.2 (BE-H2) `CreateRoleAction` + `UpdateRoleAction` DB transaction

**Dosya:** `app/Domain/Role/Actions/CreateRoleAction.php`

```diff
 use App\Models\Role;
 use Illuminate\Support\Facades\Auth;
+use Illuminate\Support\Facades\DB;
 ...
     public function execute(RoleDTO $dto): Role
     {
-        $role = Role::create($dto->toArray());
-        $role->syncPermissions($dto->permissions);
+        $role = DB::transaction(function () use ($dto): Role {
+            $role = Role::create($dto->toArray());
+            $role->syncPermissions($dto->permissions);
+
+            return $role;
+        });

         RoleCreated::dispatch($role, Auth::id());
         return $role;
     }
```

**Dosya:** `app/Domain/Role/Actions/UpdateRoleAction.php`

```diff
 use Illuminate\Support\Facades\Auth;
+use Illuminate\Support\Facades\DB;
 ...
         $oldPermissions = $role->permissions->pluck('name')->sort()->values()->all();

-        $role->update($data);
-        $role->refresh();
-        $role->syncPermissions($dto->permissions);
+        $role = DB::transaction(function () use ($role, $data, $dto): Role {
+            $role->update($data);
+            $role->refresh();
+            $role->syncPermissions($dto->permissions);
+
+            return $role;
+        });
```

---

#### 3.3 (BE-H3) `UpdateAuthSettingsAction` 2FA revoke transaction

**Dosya:** `app/Domain/Setting/Actions/UpdateAuthSettingsAction.php`

```diff
 use App\Models\User;
+use Illuminate\Support\Facades\DB;

 ...
     public function execute(AuthSettingsDTO $dto): void
     {
-        $wasTwoFactorEnabled = Setting::getValue('auth.two_factor', '1') === '1';
-        $isTwoFactorDisabled = $dto->twoFactor === '0';
-
-        Setting::setGroup('auth', $dto->toArray());
-
-        if ($wasTwoFactorEnabled && $isTwoFactorDisabled) {
-            $this->revokeAllTwoFactorAuth();
-        }
+        DB::transaction(function () use ($dto): void {
+            $wasTwoFactorEnabled = Setting::getValue('auth.two_factor', '1') === '1';
+            $isTwoFactorDisabled = $dto->twoFactor === '0';
+
+            Setting::setGroup('auth', $dto->toArray());
+
+            if ($wasTwoFactorEnabled && $isTwoFactorDisabled) {
+                $this->revokeAllTwoFactorAuth();
+            }
+        });
     }
```

---

#### 3.4 (BE-H4) `LogoutUserAction` null-safe

**Dosya:** `app/Domain/Auth/Actions/LogoutUserAction.php`

```diff
     public function execute(User $user): void
     {
-        $user->token()->revoke();
+        $user->token()?->revoke();
     }
```

Tek karakter — ama production'da active token olmayan kullanıcı logout isteğinde 500 hatası üretiyor.

---

#### 3.5 (BE-H5) FileManager N+1 düzeltmesi

**Dosyalar:** `app/Domain/FileManager/Actions/BulkDeleteAction.php` ve `DeleteFolderAction.php`.

Her iki dosyada `collectDescendantIds` metodunu değiştirin — owner scope'unda tek sorguyla `parent_id` haritasını çekip PHP tarafında BFS yapacak. Değişiklik hacmi büyük olduğu için tam yeni sürümleri `vendor/lvntr/laravel-starter-kit/stubs/app/Domain/FileManager/Actions/BulkDeleteAction.php` ve `DeleteFolderAction.php` dosyalarından kopyalayın.

**Ana değişiklikler:**
- `BulkDeleteAction`'a `buildChildrenMap(FileManagerContextDTO $context): array` eklendi. `collectDescendantIds($folder, $childrenByParent)` bu haritayı parametre alır.
- `DeleteFolderAction::collectDescendantIds`'e context parametresi eklendi; owner'a ait tüm klasör satırlarını tek sorguda çekip dolaşıyor.

50 seviyelik klasör ağacında 50 query → 1 query.

---

#### 3.6 (BE-H6) SMTP encryption `'none'` düzeltmesi

**Dosya:** `app/Providers/SettingsServiceProvider.php`

```diff
             if (array_key_exists('encryption', $mail)) {
-                config(['mail.mailers.smtp.encryption' => $mail['encryption']]);
+                // Laravel's SMTP mailer expects null (not the string "none") to send without TLS.
+                $encryption = $mail['encryption'] === 'none' ? null : $mail['encryption'];
+                config(['mail.mailers.smtp.encryption' => $encryption]);
             }
```

---

#### 3.7 (GV-H2 + GV-H3) `ApiExceptionHandler` — message leak + X-Request-ID injection

**Dosya:** `app/Exceptions/ApiExceptionHandler.php`

İki değişiklik:

**A) `handle()` metodunda trace ID üretimini değiştirin:**

```diff
     private static function handle(Throwable $e, Request $request): JsonResponse
     {
-        // 1. Trace ID — use client-provided value or generate a new one
-        $traceId = $request->header('X-Request-ID', (string) Str::uuid());
+        // 1. Trace ID — always server-generated to prevent log / header injection.
+        //    Any client-supplied X-Request-ID is accepted as correlation metadata
+        //    only after being sanitised and length-capped.
+        $traceId = (string) Str::uuid();
+        $clientRequestId = self::sanitizeClientRequestId($request->header('X-Request-ID'));

         // 2. Status + Message mapping
         [$status, $message] = self::resolve($e);

         // 3. Logging — 500+ non-validation errors
         if ($status >= 500 && ! ($e instanceof ValidationException)) {
             Log::error("[API {$status}] {$message}", [
                 'trace_id' => $traceId,
+                'client_request_id' => $clientRequestId,
                 'exception' => get_class($e),
                 ...
             ]);
         }
```

**B) `resolve()` metodundaki `default` dalını ve sınıfa yeni metodu ekleyin:**

```diff
-            // Unexpected errors
             default => [
                 500,
-                config('app.debug') ? $e->getMessage() : 'A server error occurred.',
+                'A server error occurred.',
             ],
         };
     }

+    /**
+     * Accept a client-provided X-Request-ID only if it matches a safe charset
+     * (letters, digits, dash, underscore, dot) and is ≤ 128 chars long.
+     */
+    private static function sanitizeClientRequestId(mixed $value): ?string
+    {
+        if (! is_string($value) || $value === '') {
+            return null;
+        }
+
+        $trimmed = substr($value, 0, 128);
+
+        return preg_match('/^[A-Za-z0-9._-]+$/', $trimmed) === 1 ? $trimmed : null;
+    }
```

---

#### 3.8 (FE-H1) Axios CSRF defaults

**Dosya:** `resources/js/app.ts`

Dosyanın en üstüne, import'ların hemen ardına ekleyin:

```diff
 import '../css/app.css';
 import 'primeicons/primeicons.css';
 import { createInertiaApp, usePage } from '@inertiajs/vue3';
+import axios from 'axios';
 import { i18nVue } from 'laravel-vue-i18n';
 ...
 import { PermissionPlugin } from '@/plugins/permission';

+// Axios defaults — send session + XSRF cookies on every request so Fortify
+// endpoints that rely on the web session (2FA, sessions, password-confirm)
+// stay CSRF-protected. XSRF cookie/header names match Laravel's defaults.
+axios.defaults.withCredentials = true;
+axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
+axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';
+axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
+axios.defaults.headers.common['Accept'] = 'application/json';
```

---

#### 3.9 (FE-H2) `TwoFactorTab` QR SVG XSS fix

**Dosya:** `resources/js/pages/Profile/components/TwoFactorTab.vue` (ya da legacy yol `pages/Profile/TwoFactorTab.vue`)

**A) `<script setup>` içinde — `qrCodeSvg` ref'inin altına ekleyin:**

```diff
     const qrCodeSvg = ref('');
     const setupKey = ref('');
     const recoveryCodes = ref<string[]>([]);
     const showRecoveryCodes = ref(false);

+    /**
+     * Render the Fortify QR SVG through an <img src="data:..."> element
+     * rather than v-html. An <img> sandbox neutralises any inline <script>
+     * or event handlers that a compromised intermediary could smuggle in.
+     */
+    const qrCodeDataUrl = computed<string>(() => {
+        if (!qrCodeSvg.value) return '';
+        try {
+            const encoded = window.btoa(unescape(encodeURIComponent(qrCodeSvg.value)));
+            return `data:image/svg+xml;base64,${encoded}`;
+        } catch {
+            return '';
+        }
+    });
```

**B) Template'te `v-html` bloğunu değiştirin:**

```diff
-                            <!-- eslint-disable vue/no-v-html -- QR SVG from trusted server -->
-                            <div class="inline-block rounded-lg bg-white p-4" v-html="qrCodeSvg" />
-                            <!-- eslint-enable vue/no-v-html -->
+                            <div class="inline-block rounded-lg bg-white p-4">
+                                <img
+                                    v-if="qrCodeDataUrl"
+                                    :src="qrCodeDataUrl"
+                                    :alt="$t('sk-profile.two_factor_scan')"
+                                    class="h-48 w-48"
+                                />
+                            </div>
```

---

#### 3.10 (FE-H3) `useDefinition.load()` error handling

**Dosya:** `resources/js/composables/useDefinition.ts`

`load()` ve `loadAll()` metodlarını `vendor/lvntr/laravel-starter-kit/stubs/resources/js/composables/useDefinition.ts` dosyasındaki yeni sürümle değiştirin. Ana değişiklik: `fetch` çağrısı `try/catch` içinde, `res.ok` kontrol ediliyor, hata durumunda `loaded.value` false bırakılıyor, console'a log atılıyor.

---

### 4. MEDIUM — Yetkilendirme, performans, UX

#### 4.1 (BE-M1) FormRequest `authorize(): true` temizliği

Aşağıdaki dosyalarda `return true;` yerine ilgili permission kontrolünü koyun:

| Dosya | Permission |
| --- | --- |
| `app/Http/Requests/Admin/User/StoreUserRequest.php` | `users.create` |
| `app/Http/Requests/Api/User/StoreUserRequest.php` | `users.create` |
| `app/Http/Requests/Admin/Role/StoreRoleRequest.php` | `roles.create` |
| `app/Http/Requests/Admin/Settings/UpdateAuthSettingsRequest.php` | `settings.update` |
| `app/Http/Requests/Admin/Settings/UpdateGeneralSettingsRequest.php` | `settings.update` |
| `app/Http/Requests/Admin/Settings/UpdateMailSettingsRequest.php` | `settings.update` |
| `app/Http/Requests/Admin/Settings/UpdateStorageSettingsRequest.php` | `settings.update` |
| `app/Http/Requests/Admin/Settings/UpdateFileManagerSettingsRequest.php` | `settings.update` |
| `app/Http/Requests/Admin/Settings/UpdateTurnstileSettingsRequest.php` | `settings.update` |
| `app/Http/Requests/Admin/Settings/SendTestMailRequest.php` | `settings.update` |

```diff
     public function authorize(): bool
     {
-        return true;
+        return $this->user()?->can('users.create') ?? false;
     }
```

(Permission adını uygun olanla değiştirin.)

Ek olarak `app/Http/Requests/DestroySessionsRequest.php`:

```diff
-        return true;
+        return $this->user() !== null;
```

**Auth / public endpoint'lere dokunmayın:** `Api/Auth/LoginRequest.php`, `RegisterRequest.php`, `TwoFactorChallengeRequest.php` public kalır.

**FileManager endpoint'lerine dokunmayın:** `FileManagerRequest.php` ve alt sınıflar context-tabanlı yetkilendirme kullanır.

---

#### 4.2 (BE-M4) TwoFactorChallenge brute-force hardening

**Dosya:** `app/Domain/Auth/Actions/TwoFactorChallengeAction.php`

Üç başarısız dala da `Cache::forget($cacheKey)` ekleyin — challenge artık tek kullanımlık:

```diff
         if ($code !== null && $code !== '') {
             $valid = $this->provider->verify(...);

             if (! $valid) {
+                Cache::forget($cacheKey);
+
                 return null;
             }
         } elseif ($recoveryCode !== null && $recoveryCode !== '') {
             $match = collect($user->recoveryCodes())->first(...);

             if ($match === null) {
+                Cache::forget($cacheKey);
+
                 return null;
             }

             $user->replaceRecoveryCode($match);
         } else {
+            Cache::forget($cacheKey);
+
             return null;
         }
```

Route tarafındaki `throttle:5,1` zaten mevcut.

---

#### 4.3 (BE-M7 + BE-M12) `SettingService` transaction + cache

**Dosya:** `app/Domain/Setting/SettingService.php`

Tüm dosyayı `vendor/lvntr/laravel-starter-kit/stubs/app/Domain/Setting/SettingService.php` dosyasından kopyalamak en kolayı. Özetle:

1. `DB` facade import'u eklendi.
2. `getValue()` ve `getGroup()` artık `allGrouped()` cache'i üstünden okuyor — tekil sorgu yok.
3. `setGroup()` `DB::transaction(...)` içine alındı.

Davranış aynı, performans ve atomisite yükseldi.

---

#### 4.4 (BE-M8) `MoveItemRequest` validation sıkılaştırma

**Dosya:** `app/Http/Requests/FileManager/MoveItemRequest.php`

```diff
 <?php

 namespace App\Http\Requests\FileManager;

+use Illuminate\Validation\Rule;
+
 class MoveItemRequest extends FileManagerRequest
 {
     public function rules(): array
     {
+        $itemType = $this->input('item_type');
+
+        $itemIdRules = ['required'];
+        if ($itemType === 'file') {
+            $itemIdRules = ['required', 'integer', 'min:1'];
+        } elseif ($itemType === 'folder') {
+            $itemIdRules = ['required', 'uuid'];
+        }
+
         return [
             ...$this->contextRules(),
-            'item_type' => ['required', 'string', 'in:folder,file'],
-            'item_id' => ['required'],
+            'item_type' => ['required', 'string', Rule::in(['folder', 'file'])],
+            'item_id' => $itemIdRules,
             'target_folder_id' => ['nullable', 'uuid'],
         ];
     }
 }
```

---

#### 4.5 (BE-M9) `DeleteFolderRequest` FormRequest

**Yeni dosya:** `app/Http/Requests/FileManager/DeleteFolderRequest.php`

```php
<?php

namespace App\Http\Requests\FileManager;

class DeleteFolderRequest extends FileManagerRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->contextRules();
    }
}
```

**Dosya:** `app/Http/Controllers/FileManagerController.php`

Use satırına ekleyin + metod signature değiştirin:

```diff
 use App\Http\Requests\FileManager\BulkDeleteRequest;
+use App\Http\Requests\FileManager\DeleteFolderRequest;
 use App\Http\Requests\FileManager\MoveItemRequest;
 ...

-    public function deleteFolder(Request $request, FileFolder $folder, DeleteFolderAction $action): ApiResponse
+    public function deleteFolder(DeleteFolderRequest $request, FileFolder $folder, DeleteFolderAction $action): ApiResponse
     {
-        $context = $this->contextFromRequest($request);
+        $context = $request->context();
         $this->authorizer->authorizeWrite($context);
```

---

#### 4.6 (BE-M10) `uploadAvatar` Gate::authorize tutarlılığı

**Dosya:** `app/Http/Controllers/Admin/UserController.php`

```diff
     public function uploadAvatar(UploadAvatarRequest $request, User $user, UploadMediaAction $action): ApiResponse
     {
+        Gate::authorize('update', $user);
+
         $action->execute($user, $request, 'avatar');
```

---

#### 4.7 (FE-M1) `useDialog` timer leak

**Dosya:** `resources/js/composables/useDialog.ts`

Tam sürüm için `vendor/lvntr/laravel-starter-kit/stubs/resources/js/composables/useDialog.ts`'e bakın. Değişiklikler:

1. `state`'in altına module seviyesinde `let closeTimer: ReturnType<typeof setTimeout> | null = null;` eklendi.
2. `open()` başında `clearTimeout(closeTimer)` + `closeTimer = null`.
3. `close()` başında da aynı clear, sonra `closeTimer = setTimeout(..., 300)`, timeout body'sinde `closeTimer = null`.

---

#### 4.8 (FE-M2) `useImageLightbox` timer leak

`useDialog` ile aynı pattern. `vendor/lvntr/laravel-starter-kit/stubs/resources/js/composables/useImageLightbox.ts`'den kopyalayın.

---

#### 4.9 (FE-M4) `SkForm` isDirty guard — veri kaybı koruması

**Dosya:** `resources/js/components/Lvntr-Starter-Kit/FormBuilder/SkForm.vue` (veya paket importu kullanıyorsanız bu değişiklik `composer update` ile gelir — paket kaynağı düzeltildi).

`watch(derivedDefaults, …)` bloğuna isDirty dalı ekleyin:

```diff
     watch(derivedDefaults, (newValues, oldValues) => {
         if (!isInternalMode.value) {
             return;
         }
         if (oldValues && shallowRecordEqual(newValues, oldValues)) {
             return;
         }
+        if (internalForm.isDirty) {
+            internalForm.defaults(newValues);
+            return;
+        }
         restoringDefaults.value = true;
```

---

#### 4.10 (FE-M6) `SkDatatable` urlFilters api.get

**Dosya:** `resources/js/components/Lvntr-Starter-Kit/DatatableBuilder/SkDatatable.vue`

```diff
     if (urlFilters.length) {
         onMounted(async () => {
-            await Promise.all(
+            await Promise.allSettled(
                 urlFilters.map(async (f) => {
-                    const res = await fetch(f.optionsUrl!, {
-                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
-                        credentials: 'same-origin',
-                    });
-                    const json = await res.json();
-                    urlOptions[f.key] = json.data ?? json;
+                    try {
+                        const data = await api.get<FilterOption[]>(f.optionsUrl!);
+                        urlOptions[f.key] = data ?? [];
+                    } catch {
+                        urlOptions[f.key] = [];
+                    }
                 }),
             );
         });
     }
```

Aynı dosyada `let activeMenuItems = ref<MenuItem[]>([]);` → `const activeMenuItems = ref<MenuItem[]>([]);` (FE-M9).

---

#### 4.11 (FE-M7) `TwoFactorTab` router.reload await

**Dosya:** `resources/js/pages/Profile/components/TwoFactorTab.vue`

```diff
     async function enableTwoFactor() {
         twoFactorProcessing.value = true;

         if (!props.twoFactorEnabled) {
             await axios.post('/user/two-factor-authentication');
-            router.reload({ only: ['twoFactorEnabled', 'twoFactorConfirmed'] });
+            await new Promise<void>((resolve) => {
+                router.reload({
+                    only: ['twoFactorEnabled', 'twoFactorConfirmed'],
+                    onFinish: () => resolve(),
+                });
+            });
         }

         await loadQrAndSetupKey();
```

---

#### 4.12 (FE-M8) `as any` cast'leri temizleyin

**Dosya:** `resources/js/pages/Profile/components/ProfileInfoTab.vue`

```diff
-        :avatar-url="(user as any)?.avatar_url"
+        :avatar-url="user?.avatar_url"
```

**Dosya:** `resources/js/pages/Admin/Users/components/UserForm.vue`

```diff
-            :avatar-url="(formRef.remoteData as any)?.avatar_url"
+            :avatar-url="(formRef.remoteData as { avatar_url?: string | null } | null)?.avatar_url"
```

---

### 5. Config / Env hardening

#### 5.1 (GV-M1) `.env.example` ve `.env`'de LOG_LEVEL

**Dosya:** `.env.example`

```diff
-LOG_LEVEL=debug
+LOG_LEVEL=error
```

Production `.env`'lerde de `LOG_LEVEL=error` ya da `warning` olduğundan emin olun.

---

#### 5.2 (GV-M2) Tinker `require` → `require-dev`

**Dosya:** `composer.json`

```diff
     "require": {
         "php": "^8.3",
         "laravel/framework": "^13.0",
         "laravel/pulse": "^1.7",
-        "laravel/tinker": "^2.10.1 || ^3.0",
         "lvntr/laravel-starter-kit": "@dev"
     },
     "require-dev": {
         ...
         "laravel/sail": "^1.41",
+        "laravel/tinker": "^2.10.1 || ^3.0",
         "mockery/mockery": "^1.6",
```

Sonra: `composer update`.

---

#### 5.3 (GV-M3, GV-M4) `.env.example` — Turnstile & Passport key placeholder'ları

**Dosya:** `.env.example`

Passport bölümünün altına ekleyin:

```
# Passport OAuth2 keys — prefer loading via env in production instead of
# committing the key files at storage/oauth-*.key. Run `php artisan passport:keys`
# once, move the generated strings into these env vars, then delete the files.
# PASSPORT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"
# PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"

# Cloudflare Turnstile (bot / captcha). When TURNSTILE_ENABLED=false the
# `turnstile` middleware becomes a no-op, so leaving the keys empty during
# development is safe.
TURNSTILE_ENABLED=false
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
```

---

#### 5.4 (GV-M5) `HandleInertiaRequests` — appEnv / appDebug scope

**Dosya:** `app/Http/Middleware/HandleInertiaRequests.php`

```diff
             'appVersion' => InstalledVersions::getPrettyVersion('lvntr/laravel-starter-kit'),
-            'appEnv' => config('app.env'),
-            'appDebug' => config('app.debug'),
+            'appEnv' => fn () => app()->environment('production') ? null : config('app.env'),
+            'appDebug' => fn () => app()->environment('production') ? false : (bool) config('app.debug'),
```

Eğer frontend'te `appEnv === 'production'` kontrolü yapan kod varsa artık `null` bekleyecek şekilde güncellenmeli.

---

#### 5.5 (GV-M7) CORS preflight cache

**Dosya:** `config/cors.php`

```diff
-    'max_age' => 0,
+    // Cache preflight (OPTIONS) results in the browser for 2 hours so SPA /
+    // mobile clients don't re-run the CORS handshake on every mutating call.
+    'max_age' => 7200,
```

---

#### 5.6 (GV-L1) `Password::defaults` policy

**Dosya:** `app/Providers/AppServiceProvider.php`

```diff
 use Illuminate\Support\Facades\Event;
 use Illuminate\Support\ServiceProvider;
+use Illuminate\Validation\Rules\Password;

 class AppServiceProvider extends ServiceProvider
 {
     ...
     public function boot(): void
     {
         Event::listen(Login::class, UpdateLastLogin::class);
+
+        Password::defaults(function () {
+            return Password::min(10)
+                ->mixedCase()
+                ->letters()
+                ->numbers()
+                ->symbols();
+        });
     }
 }
```

**Uyarı:** Bu değişiklik mevcut kullanıcıların şifrelerini geçersiz KILMAZ, ama yeni kayıt / şifre değiştirme akışlarında artık 10+ karakter, karışık büyük/küçük, rakam ve sembol zorunlu.

---

### 6. GV-H1 — Passport private key rotasyonu (KRİTİK, MANUEL)

Bu adım destructive işlemler içerir; **iş günü dışında, takım onayı + rollback planıyla** uygulayın.

```bash
# 1. git-filter-repo kur (filter-branch deprecated)
brew install git-filter-repo          # veya: pipx install git-filter-repo

# 2. Key dosyalarını history'den sil
cd /yolu/starter-kit-app
git filter-repo --path storage/oauth-private.key --invert-paths
git filter-repo --path storage/oauth-public.key  --invert-paths

# 3. Yeni key üret (geçici olarak dosya kalsın)
php artisan passport:keys --force

# 4. İçeriği .env'e geçir, dosyaları sil
# (PASSPORT_PRIVATE_KEY ve PASSPORT_PUBLIC_KEY — config/passport.php zaten okuyor)
rm storage/oauth-private.key storage/oauth-public.key

# 5. Aktif token'ları purge et
php artisan passport:purge

# 6. Force push (takım onayı şart)
git push --force-with-lease origin <branch>
```

**Dikkat:**
- Tüm ekibin force-push sonrası `git fetch && git reset --hard origin/<branch>` yapması gerekir.
- CI / CD sunucularında kayıtlı repo kopyaları da temizlenmeli.
- `PASSPORT_*` env değerleri production vault / secrets manager'a eklenmeli (git'e ASLA commit edilmemeli).

---

### 7. Doğrulama

```bash
# Backend
composer install
php artisan migrate --force
php artisan sk:seed-permissions --fresh
vendor/bin/pint --dirty --format agent

# Frontend
npm install
npm run build

# Tests
php artisan test --compact
npm run test
```

Her şey yeşile dönene kadar commit etmeyin. Bir test başarısız olursa ilgili patch'i izole edip hot-fix yapın; bu sürümdeki diğer patch'lere ertelemeyin — hepsi bağımsız.

### 8. Son kontrol — smoke test senaryoları

- [ ] Login → 2FA challenge → kod yanlış → tek hakkı tüketir (BE-M4).
- [ ] API `DELETE /api/v1/users/{kendi_id}` 403 döner (BE-H1).
- [ ] Role create + permission atama: DB'ye yansıyor (BE-H2).
- [ ] Settings > Auth sayfasından 2FA kapat: tüm kullanıcıların 2FA secret'ları temizleniyor + setting kaydedildi (BE-H3).
- [ ] Büyük klasör (50+ seviye) bulk delete: sayfa timeout olmuyor (BE-H5).
- [ ] SMTP encryption "none" seçili: mail gönderimi başarılı (BE-H6).
- [ ] `APP_DEBUG=true` iken 500 hatası alan API endpoint: response `message` generic; detay `debug` bloğunda (GV-H2).
- [ ] `X-Request-ID: ../etc/passwd` header'ı ile istek: response header `X-Request-ID` UUID formatında; log'da `client_request_id: null` (GV-H3).
- [ ] 2FA kurulum sayfası: QR kod `<img>` olarak render, `v-html` yok (FE-H2).
- [ ] Dialog aç/kapat/aç hızlı yapınca içerik silinmiyor (FE-M1).
- [ ] FormBuilder formu açıldıktan sonra parent prop değişirse: kullanıcının yazdığı input silinmiyor (FE-M4).

---

## Sorun giderme

### "422 Unprocessable Content" — yeni FormRequest authorize
Yeni `authorize()` kontrolü sert. İlgili permission'ın user'a atanmış olduğundan emin olun: `php artisan sk:seed-permissions --fresh` çalıştırın.

### 2FA doğrulamasında "challenge expired"
BE-M4 sonrası tek deneme hakkı var. 6 haneli kodu yanlış girerseniz tüm akış baştan başlar — Fortify OTP uygulamasındaki yeni kodu (30 saniyede bir rotates) alıp login'e yeniden girin.

### Axios istekleri 419 dönmüyor ama session yok
FE-H1 sonrası `withCredentials = true`. Eğer front-end'iniz farklı bir domain'den geliyorsa (subdomain dahil) `config/cors.php` içinde `supports_credentials => true` olmalı + allowed_origins wildcard içermemeli.

### Dashboard boş görünüyor
`appEnv` / `appDebug` artık prod'da `null` / `false` — Vue template'te koşullu rendering varsa fallback değer kullandığından emin olun.

---

## Önceki sürümler

- **13.3.3** (2026-04-20) — Windows build fix: Builder `core/` import'ları için sibling `core.ts` barrel. Detaylar: [CHANGELOG.md](CHANGELOG.md).
- **13.3.2** (2026-04-19) — Güvenlik hardening + user audit + API auth parity. Detaylar: [CHANGELOG.md](CHANGELOG.md).

Tam değişiklik tarihçesi için [CHANGELOG.md](CHANGELOG.md)'ye bakın.
