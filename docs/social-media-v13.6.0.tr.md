# Lvntr Starter Kit v13.6.0 Sosyal Medya İçerik Brief'i

Bu doküman, `lvntr/laravel-starter-kit` v13.6.0 sürümünü tek bir post yerine birkaç parçalık sosyal medya serisi olarak anlatmak için hazırlanmıştır.

Kaynak kapsam: `v13.5.11 -> v13.6.0` aralığındaki release/changelog ve upgrade notları. Ana mesaj: v13.6.0, starter kit'i daha az kopyalanan scaffold, daha çok vendor'dan güncellenen runtime ve daha kontrollü özelleştirme modeline taşıyan büyük bir mimari sürümdür.

## Ana Anlatı

**Kısa konumlandırma**

Lvntr Starter Kit v13.6.0, Laravel admin panel geliştirmede "her şeyi app'e kopyala" yaklaşımından çıkıp vendor-first bir modele geçiyor. Kit'in çekirdek runtime'ı paket içinde güncelleniyor; proje gerçekten sahiplenmek istediği alanları `sk:eject` ile kendi koduna alıyor.

**Tek cümlelik mesaj**

v13.6.0 ile Lvntr Starter Kit daha temiz kurulum, vendor'dan güncellenen runtime, override edilebilir tema sistemi ve daha üretime hazır admin deneyimi sunuyor.

**Hedef kitle**

- Laravel ile admin panel geliştiren ekipler
- SaaS, CRM, ERP, iç operasyon paneli geliştiren yazılımcılar
- Her projede kullanıcı, rol, yetki, dosya, ayar ve log ekranlarını yeniden yazmak istemeyen ekipler
- Starter kit kullanan ve güncelleme/özelleştirme yüzeyini kontrol altında tutmak isteyen geliştiriciler

**Öne çıkarılacak değerler**

- Daha az app içi dosya kalabalığı
- `composer update` ile daha fazla upstream iyileştirme alma
- Local-first override: yerel dosya varsa proje kazanır, yoksa vendor kopyası çalışır
- `sk:eject` ile bilinçli sahiplenme
- Tema, layout ve CSS tarafında slot bazlı özelleştirme
- Yeni ayarlar, güvenlik, görünüm ve admin deneyimi iyileştirmeleri

## Doğruluk Notları

İçeriklerde şu ifadeler güvenle kullanılabilir:

- "Vendor-first runtime modeli genişledi."
- "Composables, permission plugin, backend helper/middleware, domain runtime, migration ve çevirilerin önemli kısmı vendor'dan çalışıyor."
- "`sk:eject` ile vendor-resident modüller app'e alınabiliyor."
- "Varsayılan import yolları büyük ölçüde korunuyor; local-first resolver yerel özelleştirmeyi önceliklendiriyor."
- "Yeni tema sistemi `main`, runtime `aura` ve custom build-time override yaklaşımını destekliyor."
- "Ayarlar panelinde görünüm, güvenlik, parola politikası, içerik dilleri, mail ve depolama alanlarında genişleme var."

Kaçınılması gereken ifadeler:

- "Hiç breaking change yok." Yanlış olur; tema dizin yapısı ve bazı import/wiring adımları için upgrade notları var.
- "Tüm modüller vendor'a taşındı." Yanlış olur; User, Role, Dashboard, Auth, Profile ve modeller app-owned kalır.
- "Güncellemede hiçbir manuel adım yok." Yanlış olabilir; özelleştirilmiş kurulumlarda upgrade notlarına bakmak gerekir.
- "UI tamamen değişti." Fazla iddialı olur; tema mimarisi değişirken varsayılan `main` deneyiminde görsel kırılma hedeflenmez.

## Seri Planı

Önerilen seri: 7 post + 2 kısa video/Reels fikri.

Yayın sırası:

1. Sürüm duyurusu ve ana hikaye
2. Vendor-first mimari
3. `sk:eject` ve kontrollü sahiplik
4. Tema, layout ve CSS sistemi
5. Ayarlar, güvenlik ve görünüm yönetimi
6. Admin deneyimi ve bileşen iyileştirmeleri
7. Güncelleme rehberi ve kapanış

## Post 1: v13.6.0 Duyurusu

**Amaç**

Sürümün büyük resmini anlatmak: bu yalnızca birkaç küçük özellik değil, starter kit'in çalışma modelini temizleyen mimari bir sürüm.

**Format**

LinkedIn carousel, Instagram carousel, X thread.

**Hook**

Lvntr Starter Kit v13.6.0 yayında: daha temiz scaffold, vendor-first runtime, daha kontrollü özelleştirme.

**Carousel akışı**

Slide 1:

Lvntr Starter Kit v13.6.0

Daha az kopya dosya. Daha fazla vendor'dan güncellenen runtime.

Slide 2:

Bu sürümün ana fikri:

Kit'in çekirdek davranışları paket içinde çalışsın. Proje yalnızca gerçekten özelleştirmek istediği alanları sahiplenip düzenlesin.

Slide 3:

Vendor'a taşınan alanlardan bazıları:

- Kit composable'ları
- Permission plugin
- Backend helper ve middleware'ler
- Domain runtime katmanları
- Migration ve çeviri dosyaları
- Admin davranış modüllerinin önemli bölümü

Slide 4:

Import yolları korunuyor.

Local dosyan varsa proje kazanır. Yoksa vendor kopyası çalışır. Böylece özelleştirme ve güncelleme aynı modelde buluşur.

Slide 5:

Yeni tema sistemi:

- `main`
- runtime `aura`
- custom build-time override
- slot bazlı CSS katmanları

Slide 6:

Admin panel tarafında da güçlenme var:

- Görünüm ayarları
- Güvenlik ve parola politikası
- İçerik dilleri
- Sistem sağlığı
- File manager trash
- Bileşen showcase'leri

Slide 7:

v13.6.0, Lvntr Starter Kit'i daha sürdürülebilir bir admin panel temeline taşıyor.

**Caption**

Lvntr Starter Kit v13.6.0 yayında.

Bu sürümde ana odağımız, starter kit'i "her şeyi app'e kopyalayan" yapıdan daha sürdürülebilir bir vendor-first modele taşımak oldu.

Artık kit'in birçok runtime parçası vendor paketinden çalışıyor; proje yalnızca gerçekten sahiplenmek istediği bölümleri `sk:eject` ile kendi koduna alıyor.

Sonuç: daha temiz kurulum, daha kontrollü özelleştirme ve upstream güncellemeleri daha rahat alma.

**Görsel yönlendirme**

- İlk slide'da ürün adı ve sürüm numarası net olsun.
- Arka planda admin panel ekran görüntüsü veya sade kod/admin UI karışımı kullanılabilir.
- "Vendor", "App", "Override", "Eject" kavramları basit diagram ile gösterilebilir.

**CTA**

Detaylar için changelog ve upgrade notlarını inceleyin: `docs/CHANGELOG.tr.md` ve `docs/UPGRADE.tr.md`.

## Post 2: Vendor-First Runtime

**Amaç**

Teknik kitleye v13.6.0'ın en önemli mimari kararını anlatmak.

**Hook**

Starter kit'te en büyük maliyetlerden biri: kopyalanan dosyaların zamanla bayatlaması.

**Carousel akışı**

Slide 1:

Problem:

Starter kit kurulur, yüzlerce dosya app'e kopyalanır, sonra upstream iyileştirmeleri almak zorlaşır.

Slide 2:

v13.6.0 yaklaşımı:

Runtime vendor'da yaşar. Proje local override ile gerektiği kadar sahiplenir.

Slide 3:

Frontend tarafında:

15 kit composable'ı vendor'a taşındı. Import yolu aynı kalır: `@/composables/<name>`.

Slide 4:

Permission plugin de aynı modele geçti.

`v-can` ve `v-role` direktifleri vendor'dan çözülebilir; local dosya varsa yine proje kazanır.

Slide 5:

Backend tarafında:

Helper'lar, middleware'ler, validation rule'ları ve domain runtime katmanları vendor'dan çalışır.

Slide 6:

Config tarafında:

Bazı üçüncü parti config'ler artık app'e kopyalanmak yerine runtime'da güvenli default'larla uygulanır.

Slide 7:

Kazanç:

Daha az dosya kalabalığı, daha az drift, daha kolay upstream güncelleme.

**Caption**

v13.6.0'ın en büyük değişimi vendor-first runtime modeli.

Starter kit'in çekirdek davranışları artık daha fazla vendor paketinde yaşıyor. App tarafı, gerçekten özelleştirilmesi gereken dosyaları tutuyor.

Bu model hem kurulum ağacını temizliyor hem de `composer update` ile daha fazla düzeltmenin doğrudan alınmasını sağlıyor.

**Görsel yönlendirme**

- Önce/sonra dosya ağacı görseli
- "Before: copied stubs" / "After: vendor runtime + local override" şeması
- Composer update okları

**CTA**

Projende hangi dosyanın app-owned, hangisinin vendor-resident olduğunu görmek için module ownership tablosuna bak.

## Post 3: `sk:eject` ile Kontrollü Sahiplik

**Amaç**

Vendor-first modelin özelleştirmeyi kısıtlamadığını, tersine daha bilinçli hale getirdiğini anlatmak.

**Hook**

Vendor'dan çalışması, özelleştiremeyeceğin anlamına gelmiyor.

**Carousel akışı**

Slide 1:

Vendor-first ama kilitli değil.

Lvntr Starter Kit'te sahiplenmek istediğin modülü `sk:eject` ile app'e alabilirsin.

Slide 2:

`sk:eject <module>` ne yapar?

Vendor-resident modülün backend ve/veya Vue katmanını uygulamana kopyalar.

Slide 3:

Ne zaman kullanılır?

- Modül davranışını değiştireceksen
- Controller/FormRequest/Vue sayfasında kalıcı özelleştirme yapacaksan
- Upstream yerine proje sahipliğini tercih ediyorsan

Slide 4:

Ne zaman kullanmamalı?

Sadece küçük ayar, tema veya text override için modül sahiplenmek gerekmeyebilir.

Slide 5:

Trade-off:

Eject edilen dosya artık sana ait olur. Upstream güncellemeleri otomatik olarak o dosyaya akmaz.

Slide 6:

Bu yüzden v13.6.0'ın modeli net:

Varsayılan: vendor'dan çalış.

Gerektiğinde: bilinçli şekilde eject et.

**Caption**

v13.6.0 ile gelen vendor-first model özelleştirmeyi kapatmıyor.

Tam tersine, özelleştirmeyi daha bilinçli bir karara dönüştürüyor: bir modül sana gerçekten ait olacaksa `sk:eject` ile app'e alıyorsun. Değilse vendor'da kalıyor ve upstream güncellemeleri almaya devam ediyor.

Bu, starter kit kullanan projeler için uzun vadede daha temiz bir sahiplik modeli sağlıyor.

**Görsel yönlendirme**

- Karar ağacı: "Customize deeply?" -> yes -> `sk:eject`, no -> vendor
- Terminal komutu mockup'ı
- App/vendor klasörleri arasında oklar

**CTA**

Eject etmeden önce modülün gerçekten proje sahipliğine ihtiyaç duyup duymadığını kontrol et.

## Post 4: Tema, Layout ve CSS Sistemi

**Amaç**

Yeni tema sisteminin geliştirici ve tasarım tarafındaki değerini anlatmak.

**Hook**

Tema değiştirmek için tüm admin layout'unu fork'lamana gerek kalmamalı.

**Carousel akışı**

Slide 1:

v13.6.0 ile tema sistemi yeniden düzenlendi.

Amaç: daha kontrollü, slot bazlı ve sürdürülebilir override.

Slide 2:

Layout tarafında:

`AppShell.vue` yapısal kabuğu taşır. `AdminLayout.vue` ise daha ince bir kompozisyon haline gelir.

Slide 3:

CSS tarafında:

`themes/main/` altında token, layout ve component slot'ları ayrıldı.

Slide 4:

Custom tema:

`themes/custom/` içine yalnızca değiştirmek istediğin slot'u koy. Geri kalan her şey `main`'den gelir.

Slide 5:

Runtime tema:

`aura` teması Ayarlar -> Görünüm üzerinden runtime'da etkinleşebilir. Yeniden build gerektirmez.

Slide 6:

Build-time tema:

Kendi özel teman için `VITE_SK_THEME=<isim>` ile slot resolver devreye girer.

Slide 7:

Sonuç:

Tema özelleştirme artık tek parça büyük CSS dosyası yerine kontrollü, slot bazlı bir sistem.

**Caption**

v13.6.0 ile tema ve layout sistemi daha modüler hale geldi.

Admin kabuğu `AppShell.vue` ile ayrışıyor, CSS katmanları `themes/main/` altında slot'lara bölünüyor ve custom tema yaklaşımı yalnızca ihtiyacın olan parçayı override etmene izin veriyor.

Bu özellikle SaaS ve iç panel projelerinde marka uyarlamasını daha temiz hale getiriyor.

**Görsel yönlendirme**

- `themes/main/` klasör yapısını sade diagram olarak göster
- `main -> custom override -> fallback` akışı
- Ayarlar -> Görünüm ekran görüntüsü

**CTA**

Tema override yapmadan önce `docs/theme.tr.md` içindeki slot listesini incele.

## Post 5: Ayarlar, Güvenlik ve Görünüm Yönetimi

**Amaç**

v13.6.0'ın son kullanıcı/admin tarafındaki pratik yeniliklerini anlatmak.

**Hook**

Admin panel sadece CRUD ekranlarından ibaret değil. Ayarlar, güvenlik ve marka yönetimi de ilk günden hazır olmalı.

**Carousel akışı**

Slide 1:

v13.6.0 ile Ayarlar ekranı güçlendi.

Görünüm, güvenlik, mail, depolama ve içerik dilleri daha kapsamlı hale geldi.

Slide 2:

Görünüm ayarları:

Logo, favicon, tema, accent rengi ve marka kimliği yönetimi için yeni alanlar.

Slide 3:

Güvenlik:

Parola politikası, parola geçerlilik süresi ve bot protection ayarları daha net bir yapı altında.

Slide 4:

Mail:

Reply-to ve gönderim limiti gibi operasyonel ayarlar panelden yönetilebilir.

Slide 5:

İçerik dilleri:

Çok dilli içerik yönetimi için Content Languages modülü eklendi.

Slide 6:

Sistem sağlığı:

Health check ekranı Ayarlar içinde daha erişilebilir bir sekmeye taşındı.

Slide 7:

Sonuç:

Projeye özel admin ayarları için daha güçlü ve daha düzenli bir temel.

**Caption**

v13.6.0 yalnızca mimari bir sürüm değil.

Admin kullanıcının dokunduğu Ayarlar deneyimi de genişliyor: görünüm ve marka kimliği, parola politikası, bot protection, mail ayarları, depolama, içerik dilleri ve sistem sağlığı daha düzenli bir yapıda toplanıyor.

Bu, starter kit'in "kur ve iş mantığına geç" hedefini güçlendiriyor.

**Görsel yönlendirme**

- Ayarlar sekmeleri ekran görüntüsü
- Görünüm ayarları için renk/accent swatch'ları
- Güvenlik tab'ı için form odaklı görsel

**CTA**

Yeni kurulumlarda Ayarlar panelini ilk yapılandırma adımlarına dahil et.

## Post 6: Admin Deneyimi ve Bileşen İyileştirmeleri

**Amaç**

UI/UX ve geliştirici deneyimi tarafındaki iyileştirmeleri daha görünür hale getirmek.

**Hook**

Küçük görünen admin detayları, her gün kullanılan panelde büyük fark yaratır.

**Carousel akışı**

Slide 1:

v13.6.0 admin deneyimini de geliştiriyor.

Yeni ekranlar, daha tutarlı bileşenler ve daha iyi geliştirici showcase'leri.

Slide 2:

Component Showcase:

Button, Message, Toast, Tag ve Form bileşenleri tek yerde incelenebilir.

Slide 3:

Datatable:

Kolon görünürlük sistemi ve toolbar deneyimi yenilendi.

Slide 4:

FormBuilder:

Canlı showcase, daha tutarlı boolean alanları ve translatable alanların daha net tasarımı.

Slide 5:

File Manager:

Çöp kutusu ayarları, koleksiyon kapsamlı trash ve trash'teki medyaya erişim güvenliği.

Slide 6:

API Routes ve Logs:

API route ekranı, entegrasyon modalı ve günlük detay sayfası daha kullanışlı hale getirildi.

Slide 7:

Header ve profil:

Bildirim, mesaj, geliştirici popover'ları; güvenlik ve oturum ekranlarında daha iyi deneyim.

**Caption**

v13.6.0'da yalnızca altyapı değil, günlük admin panel kullanımı da iyileşiyor.

Component showcase'leri, datatable görünürlük sistemi, FormBuilder güncellemeleri, File Manager trash davranışı, API Routes entegrasyon modalı ve log detay ekranı bu sürümde öne çıkan parçalar arasında.

Bu iyileştirmeler özellikle ekip içinde starter kit'i genişleten geliştiriciler için daha hızlı referans ve daha tutarlı UI anlamına geliyor.

**Görsel yönlendirme**

- Component showcase ekranı
- Datatable toolbar/kolon görünürlüğü close-up
- File Manager trash akışı
- Logs detay ekranı

**CTA**

Bileşen geliştirirken önce showcase ekranlarını referans al.

## Post 7: Güncelleme ve Geçiş Mesajı

**Amaç**

Kullanıcılara sürümün değerini anlatırken, upgrade disiplinini de doğru kurmak.

**Hook**

v13.6.0 büyük bir sürüm. Güncellemeden önce changelog ve upgrade notlarını okuyun.

**Carousel akışı**

Slide 1:

v13.6.0'a geçerken ana akış:

`composer update`

`php artisan sk:update`

`php artisan migrate`

`npm install`

`npm run build`

Slide 2:

Standart kurulumlarda amaç:

Vendor-resident dosyalar vendor'dan çalışsın, app tarafı daha temiz kalsın.

Slide 3:

Özelleştirilmiş dosyalar varsa:

`sk:update` onları korur ve raporlar. Bu noktada karar sana ait.

Slide 4:

Derin özelleştirme gerekiyorsa:

`sk:eject <module>` ile modülü açıkça sahiplen.

Slide 5:

Tema tarafında:

Custom slot kullanıyorsan yeni tema dizin yapısını ve build script wiring'ini kontrol et.

Slide 6:

Unutma:

Vendor-first modelin amacı kontrolü azaltmak değil, sahiplik sınırlarını netleştirmek.

**Caption**

v13.6.0 büyük bir sürüm olduğu için güncelleme öncesinde `CHANGELOG` ve `UPGRADE` notlarını okumak önemli.

Özelleştirme yapmadıysan geçiş daha düz ilerler. Özelleştirdiğin dosyalar varsa `sk:update` onları korur; sonra vendor'da kalma, local override veya `sk:eject` arasında karar verebilirsin.

Bu sürümün asıl amacı: güncellenebilir paket runtime'ı ve net proje sahipliği.

**Görsel yönlendirme**

- Terminal komutları için sade kod kartı
- "Unmodified -> vendor", "Modified -> preserve", "Need ownership -> eject" akış diyagramı
- Upgrade checklist görseli

**CTA**

Güncellemeden önce `docs/UPGRADE.tr.md` içindeki `v13.5.11 -> v13.6.0` bölümünü okuyun.

## Kısa Video / Reels Fikirleri

### Video 1: "Vendor-first ne demek?"

**Süre**

30-45 saniye.

**Akış**

1. Eski model: starter kit dosyaları app'e kopyalanır.
2. Problem: dosyalar zamanla upstream'den kopar.
3. v13.6.0 modeli: runtime vendor'da, override local-first.
4. Gerekirse `sk:eject` ile sahiplen.
5. Kapanış: daha temiz scaffold, daha net sahiplik.

**Ekran önerisi**

Sol tarafta dosya ağacı, sağ tarafta terminal ve kısa diagram.

### Video 2: "Tema override artık slot bazlı"

**Süre**

30-45 saniye.

**Akış**

1. `themes/main/` klasör ağacını göster.
2. `themes/custom/components/datatable.css` gibi tek slot override örneği göster.
3. Geri kalan slot'ların `main`'den geldiğini anlat.
4. Ayarlar -> Görünüm üzerinden `aura` geçişini göster.

**Ekran önerisi**

Code editor + admin panel yan yana.

## Hazır Kısa Metinler

**Kısa duyuru**

Lvntr Starter Kit v13.6.0 yayında. Bu sürüm vendor-first runtime modelini genişletiyor, tema/layout sistemini slot bazlı hale getiriyor ve admin ayarları ile geliştirici deneyimini güçlendiriyor.

**Teknik odaklı**

v13.6.0 ile composable'lar, permission plugin, backend helper/middleware katmanı, domain runtime parçaları, migration ve translation dosyalarının önemli bölümü vendor'dan çalışıyor. Local-first resolver sayesinde özelleştirilmiş dosya varsa proje kazanıyor.

**Ürün odaklı**

Yeni sürümle Lvntr Starter Kit daha temiz kurulum, daha az dosya kalabalığı, daha güçlü ayarlar paneli ve daha sürdürülebilir özelleştirme modeli sunuyor.

**Tema odaklı**

v13.6.0 tema sistemini yeniden düzenliyor: `main`, runtime `aura`, custom override ve slot bazlı CSS katmanları ile admin paneli markaya uyarlamak daha kontrollü hale geliyor.

**Güncelleme odaklı**

v13.6.0'a geçmeden önce `CHANGELOG` ve `UPGRADE` notlarını okuyun. Özelleştirilmiş dosyalar korunur; derin sahiplik gerekiyorsa `sk:eject` ile modülü app'e alın.

## Hashtag Önerileri

- #Laravel
- #LaravelStarterKit
- #VueJS
- #InertiaJS
- #PrimeVue
- #TailwindCSS
- #AdminPanel
- #SaaSDevelopment
- #WebDevelopment
- #PHP

## Görsel Üretim Checklist'i

- Sürüm numarası her postta net görünsün: `v13.6.0`.
- "Vendor-first" kavramı soyut kalmasın; app/vendor/local override diagramı kullanılsın.
- Teknik carousel'lerde terminal komutları kısa tutulmalı.
- Ayarlar ve tema postlarında gerçek admin ekran görüntüleri tercih edilmeli.
- Kırıcı geçiş algısını azaltmak için "upgrade notlarını okuyun" mesajı net ama sakin verilmeli.
- "Her şey otomatik" gibi mutlak vaatlerden kaçınılmalı.

## Ana CTA Seçenekleri

- Changelog'u incele.
- Upgrade rehberini oku.
- Yeni tema sistemini dene.
- Vendor-first modül sahipliği tablosuna bak.
- `sk:eject` ile hangi modülü sahiplenmen gerektiğini belirle.
- Yeni kurulum için: `composer require lvntr/laravel-starter-kit:^13.0`
