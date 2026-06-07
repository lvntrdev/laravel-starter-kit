# Global Dosyalar Modülü

Global Files modülü, sistem genelindeki dosyaları admin panel içinde tam sayfa bir deneyimle yönetmek için hazırlanmış ekrandır. Teknik olarak `FileManager` bileşenini `global` context ile mount eder ve uygulama geneline ait medya varlıkları için merkezi bir çalışma alanı sağlar.

## Ne İşe Yarar

- sistem geneline ait dosyaları tek ekranda yönetir
- `global` context altında klasörleme ve dosya operasyonları sunar
- admin panel içinde tam yükseklik çalışan bir medya çalışma alanı sağlar
- `FileManager` bileşeninin tüm yeteneklerini sayfa seviyesinde kullanıma açar

## Route

| Method | Yol | Route adı | Amaç |
| --- | --- | --- | --- |
| `GET` | `/files` | `files.index` | Global dosya yöneticisi ekranını açar |

Tanım için [routes/web/files-route.php](../stubs/routes/web/files-route.php) dosyasına bakın.

## Ekran Yapısı

Sayfa `resources/js/pages/Admin/Files/Index.vue` içinde tanımlıdır ve şu montajı kullanır:

```vue
<AdminLayout :title="$t('sk-file.title')" :subtitle="$t('sk-file.subtitle')">
    <div class="flex min-h-0 flex-1">
        <FileManager context="global" height="100%" class="flex-1" />
    </div>
</AdminLayout>
```

Bu yapı sayesinde:

- başlık alanı admin layout içinde kalır
- dosya yöneticisi kalan yüksekliği doldurur
- iç scroll yalnızca FileManager alanında yaşar

## Yetenekler

Bu ekran, `FileManager` bileşeninin `global` context ile sunduğu tüm davranışları taşır:

- klasör oluşturma, yeniden adlandırma ve silme
- dosya yükleme
- sürükle-bırak ile taşıma
- toplu silme
- breadcrumb navigasyonu
- inline önizleme
- sıralama ve seçim

Bileşen düzeyindeki ayrıntılar için [file-manager.tr.md](./file-manager.tr.md) dosyasına bakın.

## İzinler

`global` context için erişim kuralları FileManager yetkilendirme katmanında çözülür:

- okuma için genelde `files.read`
- yazma için `files.create`, `files.update`, `files.delete`

Bu nedenle ekran görünür olsa bile asıl yetki kontrolü backend tarafında yapılır.

## Ne Zaman Kullanılmalı

- uygulama geneline ait ortak dosyaları yönetmek istediğinizde
- kullanıcıya bağlı olmayan medya varlıkları için merkezi bir alan gerektiğinde
- operasyon, içerik veya yönetim ekiplerinin panel içinden dosya çalışması yapması gerektiğinde

## İlgili Dokümanlar

- [file-manager.tr.md](./file-manager.tr.md)
- [roles-permissions.tr.md](./roles-permissions.tr.md)
- [settings.tr.md](./settings.tr.md)
