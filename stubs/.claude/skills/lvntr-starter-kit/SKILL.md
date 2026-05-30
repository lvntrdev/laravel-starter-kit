---
name: lvntr-starter-kit
description: "Use this skill whenever working in a Laravel project that has the Lvntr Starter Kit (lvntr/laravel-starter-kit) installed. ALWAYS activate when: writing or modifying controllers under app/Http/Controllers/Admin or app/Http/Controllers/Api; adding business logic under app/Domain/; creating FormRequests, API Resources, Actions, DTOs, Queries, Events, Listeners; touching routes/web/*-route.php or routes/api/*-route.php; building Vue pages under resources/js/pages/Admin/**; using @lvntr/components/* (SkForm, SkDatatable, SkTabs, AppDialog, AvatarUpload, PageLoading); calling FB/DB/TB builders or composables (useDialog, useConfirm, useApi, useDefinition, useRefreshBus, useCan, useFlash, useSidebar, useDarkMode); editing lang/{locale}/sk-*.php translations; running sk:install / sk:update / sk:publish / sk:upgrade / make:sk-domain / remove:sk-domain / sk:seed-permissions / site:install / env:sync; updating config/permission-resources.php, config/starter-kit.php, config/settings.php; working with the file manager, activity log, definitions, or settings panel; or when the user mentions: starter kit, sk-, ApiResponse, to_api, ApiException, BaseAction, BaseDTO, ActionPipeline, DatatableQueryBuilder, RoleEnum, PermissionEnum, definitionOptions, refreshKey, dtApi. Also triggers on Turkish: yeni domain, domain ekle, tablo ekle, form ekle, dialog aç. Enforces the kit's hard rules and upgrade-safety, and routes builder/domain detail to the lvntr-kit-frontend / lvntr-kit-domain skills."
---

# Lvntr Starter Kit — Core Gate

This project is built on top of **Lvntr Starter Kit** (`lvntr/laravel-starter-kit`).
The kit ships an admin-first scaffolding for Laravel 13 / Inertia v3 / Vue 3 /
PrimeVue 4 / Tailwind 4: authentication (Fortify + Passport), users / roles /
permissions, activity logs, settings panel, file manager, definitions, a fluent
Vue component library (FormBuilder, DatatableBuilder, TabBuilder), and a
DDD-flavored domain layer.

After `sk:install` runs, two things matter:

1. **Most code lives in your app** (`app/`, `routes/`, `resources/`, `lang/`,
   `config/`) — the kit publishes editable scaffolding there.
2. **The package itself stays untouched** (`vendor/lvntr/laravel-starter-kit/`)
   — it is upgradable via `composer update` + `php artisan sk:update`.

> **Online docs:** [starter-kit.lvntr.dev](https://starter-kit.lvntr.dev/) — full
> reference, API tables, screenshots. Use this skill for the day-to-day rules.

---

## Iron Law

```
vendor/ ve auto-generated'a dokunma; envelope/dialog/URL/Action kurallarını bypass etme;
pint çalıştırmadan ve committed migration'ı düzenleyerek bitirme.
```

Tam liste: aşağıdaki **Hard rules** bölümü.

---

## Red Flags — STOP

Bu düşüncelerin herhangi biri geçerse dur:

- "vendor'a küçük bir patch yeter" — STOP. `vendor/` dokunulmaz; `sk:publish` veya `app/` override kullan.
- "`response()->json()` daha hızlı" — STOP. `to_api()` / `ApiResponse::*` zorunlu; envelope bypass edilemez.
- "Şimdilik `confirm()` kullanayım" — STOP. `useConfirm()` zorunlu; native `confirm()/alert()` yasak.
- "URL'i hardcode'layıp sonra düzeltirim" — STOP. Vue'da hiçbir URL hardcode edilmez; `@/routes/**` + `.url()`.
- "Wayfinder regen'i sonra yaparım" — STOP. Route değişikliğinden hemen sonra `wayfinder:generate` çalışır.
- "Controller'da iki satır logic zarar vermez" — STOP. Business logic `app/Domain/{Entity}/Actions/` altında olur; controller 5 satır kalır.
- "Auto-generated dosyayı küçük düzeltme için editleyeyim" — STOP. `wayfinder/routes/actions/`, `*.d.ts`, `_ide_helper*` asla elle düzenlenmez.
- "Committed migration'ı düzeltmek daha hızlı" — STOP. Yeni migration eklenir; committed olan asla düzenlenmez.

---

## Rationalization Prevention

| Excuse | Reality |
|---|---|
| "vendor/ değişikliği küçük, composer'da kaybolmaz" | `composer update` sırasında tüm patch'ler silinir; bir sonraki upgrade'de sessizce bozulur. |
| "`response()->json()` envelope ile aynı sonucu verir" | Exception handler envelope'u yalnızca `ApiResponse` üzerinden yönetir; `response()->json()` trace_id, error mapping ve status normalizasyonunu atlar. |
| "PrimeVue `Dialog`'ı doğrudan import edersem daha az boilerplate" | `AdminLayout.vue`'deki tek `<AppDialog />` mount noktasını devre dışı bırakır; z-index, focus trap ve destroy lifecycle'ı bozulur. |
| "URL'i hardcode edeyim, Wayfinder tipi olan bir fark yaratmaz" | Route adı veya parametre değiştiğinde TypeScript hatası yerine runtime 404 alırsın; refactor körlüğü yaratır. |
| "Pint'i atlasam pre-commit hook zaten yakalar" | Hook commit'i reddeder; `--amend` ile düzeltmek önceki commit'i kirletir. Pint'i önceden çalıştır. |

---

## 0. When to apply

- Any work under `app/Domain/`, `app/Http/Controllers/{Admin,Api}/`,
  `app/Http/Requests/`, `app/Http/Resources/`, `routes/web/*-route.php`,
  `routes/api/*-route.php`, `resources/js/pages/Admin/**`,
  `resources/js/components/Lvntr-Starter-Kit/**`, or `lang/{locale}/sk-*.php`
- Adding a new entity (use `php artisan make:sk-domain Foo`)
- Building a form / table / tab / dialog (use FB / DB / TB builders, never
  PrimeVue Dialog directly)
- Returning JSON from an API controller (use `to_api()` or `ApiResponse::*`)
- Adding a permission (edit `config/permission-resources.php`, run
  `sk:seed-permissions --fresh`)
- Customizing a published Vue component (run `sk:publish` first)
- Upgrading the kit (`composer update`, then `sk:update`)

## When NOT to apply

- Pure Laravel work that the kit doesn't touch (queues, mail, scheduling) —
  use Laravel Boost / `laravel-best-practices` instead
- Pest test writing — use `pest-testing`
- Inertia/Vue patterns unrelated to the kit's components — use
  `inertia-vue-development`

If in doubt, apply this skill anyway — it overlaps with the others, but its
rules about *what not to touch* are kit-specific.

---

## 1. Hard rules (these break the kit if ignored)

1. **Never edit `vendor/lvntr/laravel-starter-kit/`.** The kit is upgradable —
   patches there vanish on `composer update`. If you need to change kit code,
   either (a) override via your `app/` layer, (b) publish the asset with
   `sk:publish`, or (c) extend the relevant class.
2. **Never edit auto-generated files.** Each is regenerated by build/composer:
   - `resources/js/{wayfinder,routes,actions}/`
   - `auto-imports.d.ts`, `components.d.ts`
   - `_ide_helper.php`, `_ide_helper_models.php`, `.phpstorm.meta.php`
3. **Never use `response()->json()` in API controllers.** Use `to_api(...)` or
   `ApiResponse::*`, throw `ApiException::*` for errors. The standard envelope
   is enforced by the exception handler installed by the kit.
4. **Never bypass `useDialog()` / `useConfirm()`** by importing PrimeVue
   `Dialog` or using `confirm()/alert()`. The kit's `<AppDialog />` and
   `<ConfirmDialog group="app" />` are mounted in `AdminLayout.vue`.
5. **Never hardcode URLs in Vue.** Import from `@/routes/**` or
   `@/actions/**` and call `.url()`. Run `php artisan wayfinder:generate`
   after route changes.
6. **Never put business logic in controllers.** Push it into an Action under
   `app/Domain/{Entity}/Actions/`. Controllers stay 5-line thin.
7. **Run `vendor/bin/pint --dirty --format agent`** before finishing any PHP
   change. The pre-commit hook will reject otherwise.
8. **Never edit a committed migration** — add a new one. Two-step destructive
   changes (write, then drop in a follow-up migration) keep production safe.

---

## Domain katmanı ve API envelope

Action/DTO/Query desenleri, `to_api` / `ApiException` envelope kuralları ve entity ekleme recipe'i: **`lvntr-kit-domain`** skill.

Form/tablo/Vue mı kuruyorsun? → **`lvntr-kit-frontend`**. Domain/controller/API mı? → **`lvntr-kit-domain`**.

---

## Frontend builders ve composables

FormBuilder/DatatableBuilder/TabBuilder tam API'si, composables referansı ve Vue sayfası recipe'i: **`lvntr-kit-frontend`** skill.

---

## 7. Permissions

Permissions are **declarative**, not hand-rolled. You declare resources and
abilities, and dynamic middleware resolves a route name like `admin.products.index`
to the permission `products.read`.

### Adding a new resource

1. Edit `config/permission-resources.php`:
   ```php
   'products' => [
       'label' => 'sk-product.product',
       'abilities' => ['read', 'create', 'update', 'delete'],
   ],
   ```

2. Re-seed:
   ```bash
   php artisan sk:seed-permissions --fresh
   ```

### Roles (already seeded)

- `system_admin` — bypasses all gates (via `Gate::before` in StarterKitServiceProvider)
- `admin`
- `user`

### Frontend gating

```ts
const { can, canAny, hasRole } = useCan();

if (can('products.update')) { … }
if (canAny(['products.update', 'products.delete'])) { … }
if (hasRole('system_admin')) { … }
```

```vue
<Button v-can="'products.create'" label="New" />
<section v-role="'admin'">…</section>
```

The `v-can` and `v-role` directives are registered by
`resources/js/plugins/permission.ts`.

### Backend gating

The `check.permission` middleware runs automatically on routes loaded via
`routes/web/*-route.php` and `routes/api/*-route.php`. You don't add it
manually — adding the resource to `permission-resources.php` is enough.

---

## 8. Translations & validation messages

The kit splits translations into `sk-*` PHP namespaces:

| File | Use for |
|---|---|
| `sk-attribute.php` (or `validation.php`'s `attributes` block) | field labels |
| `sk-button.php` | button labels |
| `sk-message.php` | flash messages (created/updated/deleted/etc.) |
| `sk-common.php` | shared UI strings |
| `sk-{entity}.php` | per-entity strings (`sk-user`, `sk-role`, `sk-file-manager`, …) |

Frontend access: `$t('sk-message.created', { entity: $t('sk-user.user') })`
Backend access: `__('sk-message.created', ['entity' => __('sk-user.user')])`

### Field label auto-resolution

If you omit `.label()` on a FormBuilder field, the label resolves to
`sk-attribute.attributes.{key}`. Add the attribute to `lang/{locale}/sk-attribute.php`
(or `validation.php` `attributes`) instead of hardcoding labels. Same for
DataTable column labels.

### Build step

`npm run build` compiles PHP translations into `lang/php_{locale}.json`. Only
the active locale is lazy-loaded at runtime.

---

## References (detay — gerekince oku)

Bu skill yalın bir gate'tir; ağır referanslar talep üzerine okunur:
- Proje dosya şekli (`sk:install` sonrası dizin ağacı) → `references/project-shape.md`
- Komut referansı (sk:install/update/publish/upgrade, make:sk-domain, seed-permissions, site:install, env:sync, wayfinder:generate) → `references/commands.md`
- Built-in modüller (file manager, activity log, settings panel, definitions, OAuth/Passport) → `references/modules.md`
- Güvenli güncelleme akışı (`sk:update`, SAFE_UPDATE, hash registry) → `references/update-flow.md`
- "Nereye bak" lookup tablosu (hangi pattern hangi dosyada) → `references/lookup.md`

---

## Skill köprüleri

- Domain/controller/API katmanı → **`lvntr-kit-domain`**
- Frontend builder/composable/Vue sayfası → **`lvntr-kit-frontend`**

---

## Bottom Line

Kit'in yükseltme güvenliği sekiz hard rule üzerine kuruludur. `vendor/` ve auto-generated dosyalar hiçbir koşulda düzenlenmez. API envelope, dialog sistemi, URL yönetimi ve Action katmanı bypass edilemez. PHP değişiklikleri pint ile biter; committed migration'lar düzenlenmez. Bu kurallar tercih değil, non-negotiable.
