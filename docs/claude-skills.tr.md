# Claude Code Skill'leri

Starter kit, herhangi bir uyumlu yapay zeka kodlama asistanına kit'in convention'larını, hard rule'larını ve builder API'lerini öğreten üç Claude Code skill'i ile birlikte gelir. Skill'ler bağımsız çalışır — herhangi bir harici araç ya da orkestrasyon katmanı gerektirmez.

## Kurulum

`php artisan sk:install` komutu skill'leri otomatik olarak host uygulamanızın `.claude/skills/` dizinine kopyalar.

Skill'leri atlamak için:

```bash
php artisan sk:install --without-ai-skill
```

`--without-ai-skill` flag'i kurulum sırasında (`sk:install`) verilir. `sk:update`'te böyle bir flag yoktur — kurulumdaki tercihi otomatik korur ve atlanan skill'leri yeniden eklemez.

## Nasıl Tetiklenir

Her skill, kendisini tetikleyen dosyaları, komutları ve sembolleri listeleyen bir `description` alanı tanımlar. Claude Code (ve uyumlu araçlar) eşleşen bir bağlam algıladığında ilgili skill'i otomatik olarak etkinleştirir — elle bir şey yapmak gerekmez.

## Üç Skill

### `lvntr-starter-kit` (core)

Giriş noktası. Asistan starter-kit projesinin herhangi bir katmanına dokunduğunda etkinleşir: controller'lar, domain action'ları, Vue sayfaları, route'lar, config, lang dosyaları veya `sk:install`, `make:sk-domain`, `sk:seed-permissions` gibi artisan komutları.

Neleri zorunlu kılar:

- **Iron Law ve 8 hard rule** — `vendor/`, envelope, dialog, URL ve Action desenleri etrafındaki tartışmasız kısıtlamalar
- **Red Flag'ler ve Rasyonalizasyon** — yaygın hatalı kullanım desenleri ve bunlardan nasıl kaçınılacağı
- **Proje şekli** — `sk:install` sonrasında kodun nerede yaşadığı (sizin `app/`, `routes/`, `resources/` vs. `vendor/`'daki dokunulmayan paket)
- **Komut referansı** — flag'leriyle birlikte artisan komutları
- **Permission'lar ve i18n** — rol/yetki convention'ları ve çeviri anahtar ön ekleri
- **Skill yönlendirme** — backend detayını `lvntr-kit-domain`'e, frontend detayını `lvntr-kit-frontend`'e devreder

### `lvntr-kit-domain` (backend / DDD)

Bir görev `app/Domain/`, `app/Http/Controllers/Admin/` veya `app/Http/Controllers/Api/` altındaki controller'lara, FormRequest, API Resource, Action, DTO, Query, Event veya Listener'lara dokunduğunda ya da `to_api`, `ApiResponse`, `BaseAction`, `BaseDTO`, `ActionPipeline` gibi semboller göründüğünde etkinleşir.

Kapsadıkları:

- **Controller/Action/DTO/Query tarifi** — ince controller'lar, domain Action'ları, tipli DTO'lar, query builder'lar
- **API zarfı** — `to_api()`, `ApiResponse::*`, `ApiException::*`; `response()->json()` kullanılmaz
- **Route convention'ları** — tipli Wayfinder route'ları, closure tabanlı route kullanılmaz
- **Entity scaffold tarifi** — yeni bir domain entity eklemek için adım adım kılavuz

### `lvntr-kit-frontend` (Vue / builder'lar / composable'lar)

Bir görev `resources/js/pages/Admin/`, `@lvntr/components/*` (SkForm, SkDatatable, SkTabs, AppDialog) veya `useDialog`, `useConfirm`, `useApi`, `useDefinition`, `useRefreshBus`, `useCan`, `useFlash`, `useSidebar`, `useDarkMode` gibi composable'lara dokunduğunda etkinleşir.

Kapsadıkları:

- **FormBuilder (FB)** — `SkForm` alan tanımları, doğrulama, colSpan, section'lar, card'lar ve icon'lar
- **DatatableBuilder (DB)** — `SkDatatable` sütun tanımları, sunucu taraflı sayfalama, filtreler ve satır eylemleri
- **TabBuilder (TB)** — `SkTabs` sekme tanımları ve lazy loading
- **Composable referansı** — her kit composable için kullanım sözleşmeleri
- **Vue tarifi** — form ve tablo içeren yeni bir admin Vue sayfası eklemek için adım adım kılavuz

## Notlar

- Skill'ler dil bağımsızdır: Türkçe anahtar kelimelerle de tetiklenir (örneğin `yeni domain`, `tablo ekle`, `form ekle`).
- Üç skill birbirine çapraz referans verir; kapsam gerektirdiğinde biri etkinleştiğinde asistanı diğerlerine yönlendirir.
- Claude Code kullanmıyorsanız kopyalama adımını tamamen atlamak için `--without-ai-skill` flag'ini geçin — başka hiçbir davranış değişmez.
