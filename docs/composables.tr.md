# Composable'lar

Kit composable'ları artık pakete dahil edilmiştir ve varsayılan olarak doğrudan vendor kütüphanesinden çalışır — tüketici uygulamasına kopyalanmaları gerekmez. Uygulama genelindeki importlar daha önce olduğu gibi `@/composables/<name>` (veya yalnızca `@/composables` barrel'ı) üzerinden yapılır; Vite `customResolver` ve buna eşlik eden tsconfig path girişi bu yolları **önce local, sonra vendor** olarak çözer: tüketicinin `resources/js/composables/` dizininde bir dosya varsa o kullanılır, yoksa vendor kopyası otomatik devreye girer.

**`useAdminMenu`**, stub olarak gönderilmeye devam eden tek composable'dır (`resources/js/composables/useAdminMenu.ts`). Tüketicinin ürettiği `@/routes/*` dosyalarına bağımlıdır ve projeye özgü menü tanımını barındırır; bu nedenle düzenlenebilir olarak kalmalıdır. `@/composables/index.ts` barrel'ı da stub olarak kalmaya devam eder.

### Composer üzerinden composable güncellemeleri

15 kit composable'ı pakette yer aldığından, `composer update lvntr/laravel-starter-kit` çalıştırıldığında otomatik olarak güncellenir. Elle dosya kopyalamaya gerek yoktur.

### Özelleştirmek için composable yayımlama

Bir composable'ı düzenlemek için önce tüketici uygulamasına yayımlayın:

```bash
php artisan sk:publish --tag=composables
```

Bu komut, vendor'daki güncel sürümleri `resources/js/composables/` dizinine kopyalar. Local kopya oluştuğunda local-first resolver onu otomatik olarak seçer — alias değişikliği veya build config düzenlemesi gerekmez.

### Mevcut kurulumlar için geçiş notu

Bu değişiklikten önce oluşturulan projelerde tüm composable'lar `resources/js/composables/` altında zaten mevcuttur. Local-first resolver bu local kopyaları kullanmaya devam eder; **hiçbir şey bozulmaz**. Ancak bu projelerde `composer update` ile gelen upstream düzeltmeleri otomatik olarak alınmaz. Vendor tarafından yönetilen güncellemelere geçmek için özelleştirmediğiniz composable dosyalarını `resources/js/composables/` dizininden silin — `useAdminMenu.ts`, `index.ts` ve kasıtlı olarak düzenlediğiniz dosyaları koruyun. Kit, local dosyaları hiçbir zaman otomatik silmez.

## Sık Kullanılan Composable'lar

- API response zarfını kullanan JSON istekleri için `useApi`
- Inertia shared props içinden role ve permission kontrolü için `useCan`
- cache'li definition yükleme için `useDefinition`
- dialog durumu ve uzaktan veri yükleme akışları için `useDialog`
- FileManager ve file-upload alanlarından tam ekran görsel önizleme için `useImageLightbox`
- onay işlemleri için `useConfirm`
- flash mesaj yönetimi için `useFlash`
- dark mode kalıcılığı için `useDarkMode`
- uygulama-geneli çalışma-zamanı tema preset'i seçimi için `useTheme`
- Inertia yüklenme durumu için `usePageLoading`
- tablo veya widget yenilemek için `useRefreshBus`
- responsive sidebar durumu için `useSidebar`
- URL ile senkron sekme durumu için `useUrlTab`
- admin navigasyonu üretmek için `useAdminMenu` ve `useMenuBuilder`

## Temel İstek ve Dialog Yardımcıları

### useApi()

Projenin `to_api()` / `ApiResponse` JSON yapısına göre hazırlanmış küçük bir `fetch()` sarmalayıcısıdır.

- `Accept: application/json` ve `X-Requested-With: XMLHttpRequest` header'larını ekler
- Uygunsa `X-XSRF-TOKEN` header'ını ekler
- `data` payload'ını doğrudan çözer
- Başarısız cevaplarda `ApiError` fırlatır
- `toast: false` verilmezse PrimeVue toast hata mesajı gösterebilir

```ts
const api = useApi();

const user = await api.get<User>('/api/v1/users/1');
await api.post('/api/v1/users', { name: 'John Doe' });
await api.put('/api/v1/users/1', { name: 'Jane Doe' });
await api.patch('/api/v1/users/1', { status: 'active' });
await api.delete('/api/v1/users/1');
```

### useConfirm()

PrimeVue `ConfirmationService` üzerine kurulmuş iki yardımcı döndürür:

- `confirmDelete(onAccept, message?, icon?)`
- `confirmAction({ message, onAccept, header?, icon?, acceptLabel?, rejectLabel?, acceptClass? })`

```ts
const { confirmDelete, confirmAction } = useConfirm();

confirmDelete(() => {
    console.log('Silme onaylandı');
});

confirmAction({
    message: 'Bu kaydı şimdi yayınlamak istiyor musun?',
    acceptLabel: 'Yayınla',
    onAccept: () => console.log('Onaylandı'),
});
```

### useDialog()

`@lvntr/components/ui/AppDialog.vue` ile birlikte çalışan global dialog yöneticisidir.

- `open(component, props?, header?, options?)`
- `openAsync(component, url, header?, options?, baseProps?)`
- `close()`
- `setLoading(val)`

Options içinde `refreshKey` verilirse `onSuccess` ve `onCancel` callback'leri otomatik eklenir.

### useImageLightbox()

`AdminLayout.vue` içindeki global `ImageLightbox` overlay'i üzerinden çalışan, ortak tam ekran görsel önizleme state'idir.

- `open(url, name?)`
- `close()`
- `state.visible`, `state.url`, `state.name`

Resimler için bunu kullanın. Resim olmayan dosyalarda `FilePreviewModal` ile `useDialog()` akışı kullanılmaya devam eder.

## Yetki ve Gezinme Yardımcıları

### useCan()

Inertia shared props içindeki permission ve role verilerini okur.

- `can(permission)`
- `canAny(permissions)`
- `hasRole(role)`

### useAdminMenu()

Projeye özel admin sidebar menü öğelerini tanımlar ve görünürlük ile aktiflik davranışını `useMenuBuilder()` composable'ına devreder.

### useMenuBuilder()

Sidebar benzeri gezinme yapıları için ortak menü yardımcısıdır.

- Üst seviye ve alt menü öğelerini permission ve role'a göre filtreler
- Filtreleme sonrası boşta kalan section başlıklarını kaldırır
- Düz URL'lerde ve query parametreli URL'lerde aktif linki doğru belirler
- Çocuk öğelerden biri aktifse parent group'u açık tutar

```ts
const allItems: MenuItem[] = [{ title: 'sk-menu.dashboard', href: '/dashboard' }];

return useMenuBuilder(allItems);
```

### useUrlTab()

Sekme seçimini `?tab=security` gibi bir query string anahtarı ile senkron tutar.

### useRefreshBus()

Özellikle DataTable gibi bileşenler için kullanılan basit bir global yenileme bus'ıdır. Kayıtlı callback'ler bileşen unmount olduğunda otomatik temizlenir.

- `on(key, callback)` — refresh callback'i kaydet
- `refresh(...keys)` — bir veya birden fazla refresh anahtarını tetikle
- `refreshAll()` — tüm kayıtlı callback'leri tetikle

```ts
const bus = useRefreshBus();

bus.on('users-table', () => fetchData());
bus.refresh('users-table');
```

## UI State Yardımcıları

### useSidebar()

Admin sidebar için masaüstü daraltma ve mobil açık/kapalı durumlarını yönetir.

### useDarkMode()

Dark mode tercihini local storage'da saklar ve `<html>` üzerinde `.dark` sınıfını değiştirir.

### useTheme()

Uygulama-geneli çalışma-zamanı renk preset'ini yönetir. Aktif preset adı, Inertia shared-prop `theme`'den başlatılır (backend `appearance.theme`'den çözümler). Hydrate sonrası runtime swap'ları `usePreset()` üzerinden gerçekleşir; deployment veya sayfa yenilemesi gerekmez.

- `currentTheme` — reaktif salt-okunur preset adı
- `themeNames` — frontend kaydındaki seçilebilir preset adları
- `setTheme(name)` — preset'i runtime'da uygular; bilinmeyen adlar `default`'a düşer

`useDarkMode()` ile ortogonaldir: `setTheme()` `.dark` class'ına hiçbir zaman dokunmaz. Tam tema rehberi için `docs/theming.tr.md` dosyasına bakın.

### usePageLoading()

`inertia:start` ve `inertia:finish` tarayıcı event'leri ile sayfa geçiş durumunu izler.

### useFlash()

Inertia shared props içindeki flash verisini reactive olarak sunar.

Bu projede flash mesajlar composable içinde değil, `AdminLayout.vue` içinde toast olarak gösterilir.

## Definition Yardımcıları

### useDefinition()

Definition kayıtlarını giriş gerektiren `/definitions` endpoint'inden yükler ve ortak bir reactive cache içinde tutar.

- `load(keys)` — yalnızca istenen key'leri yükler; ortak cache sayesinde tekrar istek atmaz
- `loadAll()` — mevcut tüm definition key'lerini yükler
- `list(key, filter?)` — ham definition öğelerini döner, isteğe bağlı filtreyle
- `options(key, filter?)` — select'ler için `{ label, value }` formatında öğeler döner
- `find(key, value)` — değere göre tek bir öğe bulur
- `clearCache()` — reactive cache'i sıfırlar
- `loaded` — herhangi bir yükleme tamamlandığında `true` olan reactive boolean

Bu projede tipik key'ler `userStatus`, `gender`, `identityType` ve `yesNo` değerleridir.

```ts
const { load, options, find } = useDefinition();

await load(['userStatus', 'gender']);

const statusOptions = options('userStatus');
const activeStatus = find('userStatus', 'active');
```

`list()` ve `options()`, definition listesinin alt kümesini almak istediğinde `only` veya `except` dizileri içeren isteğe bağlı bir `filter` nesnesi alır:

```ts
// Yalnızca active ve pending durumlarını göster
const filteredOptions = options('userStatus', { only: ['active', 'pending'] });

// Belirli bir durumu dışla
const filteredOptions = options('userStatus', { except: ['archived'] });
```

## Öneri

Bir arayüz davranışı birden fazla sayfada görünmeye başladığında, aynı kodu tekrar etmek yerine bunu bir composable içine taşıyın.
