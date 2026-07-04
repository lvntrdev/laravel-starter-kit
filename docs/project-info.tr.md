# Proje Bilgisi

> **Aktif Geliştirme Uyarısı**
>
> Bu depo (repository) sürekli bir değişim içerisindedir. Projenin stabilitesi henüz tam olarak sağlanmamıştır. Kullanırken lütfen aşağıdaki noktaları göz önünde bulundurun:
>
> 1. **Kod Değişiklikleri:** Ana dizin yapısı veya çekirdek sınıflar radikal şekilde değişebilir.
> 2. **Güncelleme Süreci:** Güncellemeler her zaman otomatik bir geçiş (migration) sunmayabilir. Güncelleme sonrası README veya CHANGELOG dosyalarını kontrol ederek elle müdahale etmeniz gerekebilir.
> 3. **Risk:** Yapılan değişiklikler mevcut projenizde veri kaybına veya hatalara yol açabilir.

Bu starter kit, boş bir panel yerine üretime yakın bir temel sunan, admin odaklı bir Laravel 13 paketidir.

## Neler İçerir

- PHP 8.4+ ile Laravel 13 backend
- Inertia.js v3, Vue 3.5, TypeScript 5.9, Vite 7 ve `@inertiajs/vite` üzerinden SSR'a hazır uygulama akışı
- Tailwind CSS 4 ve PrimeVue 4 arayüz yapısı
- Fortify tabanlı web kimlik doğrulama, profil güvenliği, opsiyonel 2FA ve tarayıcı oturum yönetimi
- Login, register ve forgot-password akışları için Cloudflare Turnstile desteği
- Personal access token kullanan Passport tabanlı API kimlik doğrulama
- Rol ve yetki yönetimi
- Genel, auth, mail, storage, file manager, API entegrasyonları, API istemcileri, API token'ları ve System Health sekmelerine sahip ayarlar paneli
- İşlem kayıtları, definitions sistemi, ApiRoutes admin modülü ve global dosyalar çalışma alanı
- DataTable, FormBuilder ve Tabs gibi tekrar kullanılabilir builder bileşenleri
- İş mantığını temiz büyütmek için domain odaklı proje yapısı

## Minimum Gereksinimler

- PHP `8.4+`
- Composer
- Node.js `20.19+`
- npm
- MySQL veya MariaDB
- Taze bir Laravel 13 projesi ya da bu starter kit yapısına uyumlu bir proje

## Ne Zaman Kullanılmalı

Authentication, yetkilendirme, ayarlar, medya yönetimi ve admin panel altyapısını sıfırdan kurmak yerine hazır bir temel ile başlamak istediğinizde bu paket doğru tercihtir.

## SSR

SSR desteği uygulamanın içinde hazır gelir ve aynı Inertia/Vite giriş noktası üzerinden çalışır. Çalışma zamanında etkin olup olmayacağı `INERTIA_SSR_ENABLED` env değişkeniyle belirlenir (kit bu değeri v13.5.12'den itibaren vendor'dan `false` olarak varsayar, bu yüzden `config/inertia.php` artık publish edilmek zorunda değildir — yalnızca Inertia'yı daha ileri özelleştirmek için publish edin); kapalıyken uygulama aynı sayfa koduyla sorunsuz şekilde client render'a döner.

## ApiRoutes Admin Modülü

ApiRoutes modülü, adminlerin panel içinden Passport API route belgelerini incelemesine ve yeniden oluşturmasına olanak tanır.

- Frontend sayfaları: `resources/js/pages/Admin/ApiRoutes/`
- Backend domain: `app/Domain/ApiRoute/`
- Web route'ları: `routes/web/developer-route.php`, `api-routes.*` adıyla

Detaylar için [api-routes.tr.md](./api-routes.tr.md) dosyasına bakın.

## Global Files Modülü

Global Files modülü, sistem genelindeki dosyaları tam sayfa bir dosya yöneticisi deneyimiyle yönetmenizi sağlar. Bu ekran, FileManager bileşenini `global` context ile mount eder ve admin panel içinde merkezi bir medya çalışma alanı sunar.

Detaylar için [files.tr.md](./files.tr.md) dosyasına bakın.

## Dil ve Locale

Starter kit, Laravel `lang/` dosyalarını ve `laravel-vue-i18n` entegrasyonunu birlikte kullanır. Kullanıcının seçtiği arayüz dili session'da tutulur ve aktif diller ayarlar panelinden kontrol edilir.

Detaylar için [i18n.tr.md](./i18n.tr.md) dosyasına bakın.

## Önerilen Okuma Sırası

1. [install.tr.md](./install.tr.md)
2. [update.tr.md](./update.tr.md)
3. [ddd.tr.md](./ddd.tr.md)
4. [project-documentation.tr.md](./project-documentation.tr.md)
