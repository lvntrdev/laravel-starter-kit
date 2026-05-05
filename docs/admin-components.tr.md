# Admin Bileşenleri Stil Rehberi

`resources/js/pages/Admin/*` altındaki sayfalar için kurallar. Yeni modüllerin mevcut User / Role / ActivityLog / Settings / Files sayfalarıyla aynı görünüm ve davranışı sergilemesi için bu rehberi izle.

## Sayfa yerleşimi

```
resources/js/pages/Admin/<Modül>/
├── Index.vue              # Liste (SkDatatable)
├── Create.vue             # Opsiyonel — yalnızca ayrı rota gerekiyorsa
├── Edit.vue               # Opsiyonel — yalnızca ayrı rota gerekiyorsa
└── components/
    └── <Modül>Form.vue    # Create/Edit tarafından kullanılan SkForm sarmalayıcı
```

Çoğu modül dialog tabanlı create/edit ile `Index.vue` içinde çözülür (bkz. `Users/Index.vue`). Form dialog'a sığmıyorsa (çok adımlı, medya, uzun form) `Create.vue` / `Edit.vue`'ye geç.

## Form akışları — birini seç, karıştırma

| Durum | Kullan | Endpoint tipi | Neden |
| --- | --- | --- | --- |
| Dialog veya Inertia sayfasında admin CRUD | **`SkForm` + FormBuilder** | `back()` / `redirect()` dönen Inertia controller | `SkForm` içeride Inertia'nın `useForm`'unu sarmalar; validation hataları Laravel FormRequest yanıtından otomatik gelir; success/cancel event'leri dialog yaşam döngüsünü yönetir |
| Programatik tek-seferlik API çağrısı (satır aksiyonu, toplu işlem, form dışı mutation) | **`useApi()`** | `to_api()` / `ApiResponse` dönen controller | Unwrap'lı `data` döner, başarısızlıkta toast + `ApiError` fırlatır |
| Küçük JSON payload okuma (select seçenekleri, stat widget) | **`useApi().get()`** | `ApiResponse::success(...)` | Aynısı; veri zaten Inertia prop'uyla geliyorsa atla |

`SkForm` submit etmek için `useApi()` kullanma. Elle `fetch()` yazma — toast/hata hattını atlar.

## Datatable

- Frontend: `SkDatatable` + `DB.table<T>()` builder (bkz. [datatable.tr.md](./datatable.tr.md))
- Backend: `app/Domain/<Modül>/Queries/` altında bir `*DatatableQuery` sınıfında `DatatableQueryBuilder::for(Model::class)`
- Benzersiz bir `refresh-key` ver ki dialoglar mutation sonrası `useRefreshBus().refresh(key)` çağırabilsin

## Zengin metin içeriği

Kullanıcı tarafından yazılan her HTML içerik için (hoş geldin mesajı, duyuru banner'ları, pazarlama metni, zengin açıklamalar) `FB.editor()` alanını backend'deki `App\Support\HtmlSanitizer` ile birlikte kullan. İçeriği hem yazma yolunda (FormRequest `prepareForValidation` hook'u) hem de okuma yolunda (controller'da Inertia'ya paylaşmadan önce) sanitize et; `sk-prose` konteyneri içinde `v-html` ile render et. DB'deki ham editor HTML'ini asla doğrudan tarayıcıya aktarma — bkz. [FormBuilder Editor alan API'si](./formbuilder.tr.md#editor-alan-apisi).

## Dialog ve onaylar

- Modal formlar için `@lvntr/components/ui/AppDialog.vue`'den `AppDialog`
- Yıkıcı aksiyonlar (silme, pasifleştirme) için `useConfirm()`. `accept`'i mutation'a bağla.
- Template-level `v-model` istemiyorsan imperatif dialog state için `useDialog()`

## Geri bildirim

- Mutation başarı toast'ı: ya component'ten emit et, ya da Laravel flash session kullan (Inertia yanıtında `useFlash()` otomatik yakalıyor)
- Mutation başarısızlık toast'ı: zaten `useApi()` / `SkForm` yönetiyor — ikinci toast ekleme
- Validation hataları: `SkForm` satır içinde gösteriyor; toast'ta tekrarlama

## İzin kontrolleri

- Template'te: `v-can="'users.update'"` / `v-role="'admin'"` direktifleri
- Script'te: `const { can, hasRole } = useCan()`
- Otoriter kontrol sunucu tarafında — client check sadece UX içindir

## Definitions (enum / dropdown)

- `useDefinition()` çağrısını sayfa `onMounted`'inde yap, loop içinde değil
- Bir mutation definition setini geçersiz kılıyorsa (örn. rol listesi değişti), `useDefinition().invalidate('roles')` çağır veya refresh bus'a bağla:

  ```ts
  const { invalidate } = useDefinition();
  const bus = useRefreshBus();
  bus.on('roles-updated', () => invalidate('roles'));
  ```

## Frontend'de rotalar

Her zaman Wayfinder fonksiyonlarını kullan — asla `/admin/...` string'i yazma.

```ts
import users from '@/routes/users';
DB.table<UserRow>().route(users.dtApi.url());
```

## Yeni admin modül için hızlı kontrol listesi

1. Domain scaffold: `php artisan make:sk-domain <İsim>`
2. Backend: `*DatatableQuery` + FormRequest + Action'lar + Event'ler
3. Frontend `Index.vue`: `refresh-key`'li `SkDatatable`, aksiyonlar dialog'lara bağlı
4. `<Modül>Form.vue`: FormBuilder config'li `SkForm`; `success` / `cancel` emit'leri
5. İzinler: `config/permission-resources.php`'ye resource girişi, `sk:seed-permissions --fresh`
6. Çeviriler: `lang/{en,tr}/<modül>.php`
7. Testler: liste + create + update + delete için Feature test
