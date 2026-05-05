# Wayfinder

[Laravel Wayfinder](https://github.com/laravel/wayfinder) generates type-safe TypeScript functions from your Laravel routes and controllers. The starter kit ships with the vite plugin already wired up — frontend code should call these generated functions instead of hardcoding URL strings.

## How the generation works

```
routes/web/*.php            ──┐
routes/api/*.php              │  wayfinder() vite plugin
app/Http/Controllers/**.php   │  (runs on dev + build)
                              ▼
resources/js/actions/**/*.ts  ← controllers (Action-style)
resources/js/routes/**/*.ts   ← named routes
resources/js/wayfinder/*.ts   ← generated metadata
```

Regeneration happens automatically under `npm run dev` and `npm run build`. Manual regeneration:

```bash
php artisan wayfinder:generate
```

## Using generated routes

Every named route becomes a typed function. For `Route::get('/admin/users/dt', …)->name('admin.users.dtApi')`:

```ts
import users from '@/routes/users';

// URL only — good for <a :href> or third-party libs
const dtUrl: string = users.dtApi.url();

// Build request objects
users.dtApi.get();                   // { url: '/admin/users/dt', method: 'get' }
users.store.post({ name: 'Ali' });   // { url: '/admin/users', method: 'post', data: {…} }
```

Use the typed functions with components that accept a `route` prop:

```vue
<script setup lang="ts">
    import { DB } from '@lvntr/components/DatatableBuilder/core';
    import users from '@/routes/users';

    const tableConfig = DB.table<UserRow>()
        .route(users.dtApi.url())   // <-- no hardcoded path
        .build();
</script>
```

## Using generated controller actions

Every `Route::…->uses([Controller::class, 'method'])` entry (or single-action invokables) also gets a function under `@/actions`:

```ts
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';

await useApi().post(UserController.store.url(), { name: 'Ali' });
```

Prefer `@/routes/…` when you have a named route — actions are a fallback when no name is defined.

## Query params and route model binding

Query params are typed:

```ts
users.index.url({ query: { status: 'active', type: 'admin' } });
// → '/admin/users?status=active&type=admin'
```

Route model binding works the same way:

```ts
// Route::get('/admin/users/{user}', …)->name('admin.users.show')
users.show.url({ user: 42 });  // '/admin/users/42'
users.show.url({ user: user.id });
```

## Inertia form submission

Wayfinder functions plug straight into Inertia's form helper:

```ts
import { useForm } from '@inertiajs/vue3';
import users from '@/routes/users';

const form = useForm({ name: '', email: '' });

function submit() {
    form.submit(users.store);
}
```

For API flows, prefer `SkForm`'s built-in submission or `useApi()` — both accept the route descriptor directly.

## Tree-shaking and bundle size

Wayfinder output is fully tree-shakable — only the routes/actions imported by your components ship to the browser. Do not mass-re-export from a barrel file; import from the specific route module (`@/routes/users`) so Vite can drop unused entries.

## When not to use Wayfinder

- Static asset URLs (`/favicon.ico`, `/storage/…`) — use string literals.
- External APIs — Wayfinder only knows about routes defined in this Laravel app.
- Route templates embedded inside PrimeVue DataTable `exportUrl` props where you need a string at SSR time — call `.url()` once and pass the string down.

## Troubleshooting

- **`Module not found: @/routes/foo`** — The route name does not exist or generation has not run yet. Start `npm run dev` or run `php artisan wayfinder:generate`.
- **TypeScript complains about missing params** — Wayfinder requires params that are declared in the route pattern. Pass them in the first argument (`users.show.url({ user: 42 })`).
- **Stale types after renaming a route** — Delete `resources/js/wayfinder/` and `resources/js/routes/` then regenerate. They are fully derived artifacts.
