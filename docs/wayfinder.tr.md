# Wayfinder

[Laravel Wayfinder](https://github.com/laravel/wayfinder) Laravel route'larından ve controller'larından tip-güvenli TypeScript fonksiyonları üretir. Starter kit vite plugin'i hazır bağlı gelir — frontend kodu hardcoded URL string'leri yerine bu üretilmiş fonksiyonları çağırmalıdır.

## Üretim nasıl çalışır

```
routes/web/*.php            ──┐
routes/api/*.php              │  wayfinder() vite plugin
app/Http/Controllers/**.php   │  (dev + build sırasında çalışır)
                              ▼
resources/js/actions/**/*.ts  ← controller'lar (Action tarzı)
resources/js/routes/**/*.ts   ← isimli route'lar
resources/js/wayfinder/*.ts   ← üretilen metadata
```

Yeniden üretim `npm run dev` ve `npm run build` ile otomatik. Manuel üretim:

```bash
php artisan wayfinder:generate
```

## Üretilen route'ları kullanmak

Her isimli route tipli bir fonksiyona dönüşür. `Route::get('/admin/users/dt', …)->name('admin.users.dtApi')` için:

```ts
import users from '@/routes/users';

// Sadece URL — <a :href> veya üçüncü parti kütüphaneler için
const dtUrl: string = users.dtApi.url();

// İstek nesneleri üret
users.dtApi.get();                   // { url: '/admin/users/dt', method: 'get' }
users.store.post({ name: 'Ali' });   // { url: '/admin/users', method: 'post', data: {…} }
```

Tipli fonksiyonları `route` prop'u alan bileşenlerle kullan:

```vue
<script setup lang="ts">
    import { DB } from '@lvntr/components/DatatableBuilder/core';
    import users from '@/routes/users';

    const tableConfig = DB.table<UserRow>()
        .route(users.dtApi.url())   // <-- hardcoded path yok
        .build();
</script>
```

## Üretilen controller action'larını kullanmak

`Route::…->uses([Controller::class, 'method'])` veya tek-action invokable'lar da `@/actions` altında fonksiyon oluşturur:

```ts
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';

await useApi().post(UserController.store.url(), { name: 'Ali' });
```

İsimli route varsa `@/routes/…` tercih et — action'lar isimsiz route'lar için yedektir.

## Query parametreleri ve route model binding

Query parametreleri tiplidir:

```ts
users.index.url({ query: { status: 'active', type: 'admin' } });
// → '/admin/users?status=active&type=admin'
```

Route model binding de aynı:

```ts
// Route::get('/admin/users/{user}', …)->name('admin.users.show')
users.show.url({ user: 42 });  // '/admin/users/42'
users.show.url({ user: user.id });
```

## Inertia form submission

Wayfinder fonksiyonları Inertia form helper'ına doğrudan takılır:

```ts
import { useForm } from '@inertiajs/vue3';
import users from '@/routes/users';

const form = useForm({ name: '', email: '' });

function submit() {
    form.submit(users.store);
}
```

API akışları için `SkForm`'un built-in submission'ı veya `useApi()` tercih edilmeli — ikisi de route descriptor'ı doğrudan kabul eder.

## Tree-shaking ve bundle boyutu

Wayfinder çıktısı tamamen tree-shakable — sadece bileşenlerinin import ettiği route/action'lar tarayıcıya gider. Barrel dosyadan toplu re-export yapma; Vite'ın kullanılmayan girişleri atabilmesi için spesifik route modülünden import et (`@/routes/users`).

## Wayfinder'ı ne zaman kullanma

- Statik asset URL'leri (`/favicon.ico`, `/storage/…`) — string literal kullan.
- Harici API'ler — Wayfinder yalnızca bu Laravel uygulamasındaki route'ları bilir.
- SSR zamanında string'e ihtiyacın olan PrimeVue DataTable `exportUrl` gibi durumlarda — `.url()`'yi bir kez çağır, string'i aşağıya taşı.

## Sorun giderme

- **`Module not found: @/routes/foo`** — Route adı yok veya üretim henüz çalışmadı. `npm run dev` başlat veya `php artisan wayfinder:generate` çalıştır.
- **TypeScript eksik param hatası** — Wayfinder route pattern'inde tanımlı param'ları ister. İlk argümanda geç: `users.show.url({ user: 42 })`.
- **Route yeniden adlandırıldıktan sonra tipler eski kalıyor** — `resources/js/wayfinder/` ve `resources/js/routes/` klasörlerini sil, sonra yeniden üret. Tamamen türetilmiş dosyalardır.
