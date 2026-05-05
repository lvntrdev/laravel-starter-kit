# Güncelleme

Bu rehber, mevcut bir projede starter kit'i en güvenli şekilde nasıl güncelleyeceğinizi anlatır.

> **Hardening / güvenlik sürümleri:** Sürüm notları **publish edilmiş dosyalara** (yani `sk:install`'ın uygulamanıza kopyaladığı controller, request, policy, composable, config dosyalarına) dokunan düzenlemelerden bahsediyorsa, `sk:update` bunları lokal olarak değiştirdiyseniz (yaygın durum budur) **üzerine yazmaz**. Bu tür sürümler için [UPGRADE.tr.md](./UPGRADE.tr.md) rehberini izleyin — elle uygulamanız gereken diff formatında patch listesini ve smoke-test checklist'ini içerir.
>
> Ayrım bilinçli: `composer update` katmanı paket-içi kodu (`vendor/lvntr/laravel-starter-kit/src/`) taşır, UPGRADE rehberi ise uygulamanızın içindeki kopya katmanı taşır.

> **v13.4.1:** Bu sürüm, publish edilmiş dosya patch'lerine ek olarak üç adet kurulum-zamanı düzeltmesi de getiriyor (OAuth UUID migration'ları, Postman ayar tablosu migration'ı, Passport personal access client sağlaması) — mevcut kurulumların bir kez çalıştırması gereken komutlar için [UPGRADE.tr.md §7](./UPGRADE.tr.md) bölümüne bakın.

## Önerilen Akış

1. Mevcut çalışmanızı commit edin.
2. Paket güncellemesini önizleyin.
3. Paket güncellemesini uygulayın.
4. Migration, env senkronizasyonu ve asset build işlemlerini çalıştırın. (v13.4.1: `oauth_*` migration'larını da yeniden çalıştırın — bkz. [UPGRADE.tr.md §7.1](./UPGRADE.tr.md).)
5. Yetkileri, route'ları, auth/settings ekranlarını ve kritik sayfaları tekrar kontrol edin.

## 1. Composer Paketini Güncelleyin

```bash
composer update lvntr/laravel-starter-kit
```

## 2. Önce Değişiklikleri Önizleyin

```bash
php artisan sk:update --dry-run
```

Projede özelleştirilmiş controller, route, sayfa veya config kararları varsa gerçek güncellemeden önce `--dry-run` kullanın.

## 3. Güncellemeyi Uygulayın

```bash
php artisan sk:update
```

### `sk:update` Ne Yapar

- paket sahipli güvenli yolları her zaman günceller
- gerekirse artık kullanılmayan paket dosyalarını kaldırır
- kullanıcı tarafından değiştirilebilen dosyaları yalnızca lokal olarak değiştirilmemişse günceller
- izlenmeyen dosyalar için nasıl davranılacağını sorar
- paketle gelen yeni dosyaları ekler
- eksik filesystem ve media library config parçalarını enjekte eder
- yeni migration'ları isteğe bağlı olarak çalıştırabilir

## 4. Zorlayıcı Mod

```bash
php artisan sk:update --force
```

Bunu yalnızca paket dosyalarının yerel değişikliklerinizin üzerine bilinçli şekilde yazmasını istiyorsanız kullanın.

## 5. Güncelleme Sonrası Kontrol Listesi

Başarılı güncellemeden sonra şunları çalıştırın:

```bash
npm install
npm run build
php artisan migrate
php artisan env:sync
```

Permission kaynakları veya roller değiştiyse ayrıca şunu çalıştırın:

```bash
php artisan sk:seed-permissions --fresh
```

Güncellemeyle yeni ayar grupları veya auth davranışları geldiyse şu ekranları bir kez açıp doğrulayın:

- Ayarlar -> Auth
- Ayarlar -> Turnstile
- Ayarlar -> File Manager
- Profil güvenlik sekmeleri

## Dosya Güncelleme Stratejisi Özeti

- Güvenli çekirdek yollar otomatik olarak üzerine yazılır.
- Özelleştirilebilir dosyalar değişmediyse güncellenir, aksi halde korunur.
- `config/permission-resources.php` kullanıcıya ait bir dosya olarak kabul edilir.
- Paketle gelen yeni dosyalar otomatik eklenir.

## Özelleştirilmiş Bir Dosyayı Geri Alma

Ayrı bir `sk:rollback` komutu yok — geri alma, dosyayı barındıran tag üzerinde `sk:publish --force` ile yapılır. Bu bilinçli bir tercih: kod yolu sıfır kurulumla aynı kalır, geri alma gölge state'e güvenmez.

```bash
# Kullanılabilir tag'leri listele
php artisan sk:publish --help

# Tek bir özelleştirilebilir alanı (örn. sadece FormBuilder) paket versiyonuna sıfırla
php artisan sk:publish --tag=form --force

# Önce izole bir dizine publish edip farkı incele — kodun etkilenmez
php artisan sk:publish --tag=form --destination=/tmp/sk-compare
diff -ru resources/js/components/Lvntr-Starter-Kit/FormBuilder /tmp/sk-compare/resources/js/components/Lvntr-Starter-Kit/FormBuilder
```

`--force` öncesi commit'le — eski versiyona Git üzerinden erişebilirsin. Proje genelinde kurtarma için (config inject sorunları vb.) `php artisan sk:install` tekrar çalıştırılabilir — idempotent'tir ve AST inject'leri yalnızca anahtar eksikse yeniden uygular.

## Hangi Durumda `sk:upgrade` Kullanılmalı

Laravel 12 -> 13 gibi starter-kit veya Laravel major geçişlerinde `sk:update` yerine `sk:upgrade` kullanın. Aynı ana sürüm hattındaki paket güncellemelerinde normal akış `sk:update`'tir.

```bash
php artisan sk:upgrade
php artisan sk:upgrade --force
php artisan sk:upgrade --skip-build
```

## Hangi Dökümanlarla Birlikte Okunmalı

- ilk kurulum için [install.tr.md](./install.tr.md)
- komut detayları için [artisan-commands.tr.md](./artisan-commands.tr.md)
- daha derin mimari parçaları güncellemeden önce [project-documentation.tr.md](./project-documentation.tr.md)
