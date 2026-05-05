# Yükseltme Rehberi

Bu dosya sürümler arası yükseltme talimatlarını içerir. Her bölüm bir sürüm geçişini anlatır.

---

## v13.5.0 → v13.5.1

`composer update lvntr/laravel-starter-kit` yeterli. Mevcut davranış korunur.

### Opsiyonel cleanup (frontend)

App'te `resources/js/components/Lvntr-Starter-Kit/` klasörü hala duruyorsa ve özel customization yoksa, vendor frontend'ine geçebilirsiniz:

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

### sk:sync deprecation

`php artisan sk:sync` deprecated oldu. Composer path repository (symlink) workflow'u kullananlar için gerekmiyordu zaten. `--force` ile eski davranış korunur ama önerilmez.

---

## v13.4.x → v13.5.x

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

`app/Domain/FileManager/`, `app/Domain/Shared/` gibi dosyalar artık vendor'dan da çalışıyor. Eğer bu dosyaları uygulamanızdan kaldırıp vendor versiyonunu kullanmak istiyorsanız adım adım rehber için bakın:

`docs_project/migrate-existing-project-to-vendor.tr.md` (uygulama worktree'sinde)

Bu adım tamamen isteğe bağlıdır ve hemen yapılması gerekmez.

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
