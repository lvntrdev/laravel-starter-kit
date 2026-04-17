# Package Architecture

This document describes how `lvntr/laravel-starter-kit` is laid out and how a consuming Laravel application is expected to integrate with it.

## Package Layout

```
lvntr/laravel-starter-kit
├── src/                 Package code — owned by the package
├── stubs/               Templates published into your app
├── config/              Package config (publishable)
├── resources/
│   ├── lang/            Translations (namespace: starter-kit)
│   └── js/components/   Vue components (published or aliased)
└── database/migrations  (ships via stubs/)
```

### `src/` — owned by the package

Everything under `src/` lives in the `Lvntr\StarterKit\` namespace and is considered **package internals**. You should never copy, edit, or re-declare these classes in your application.

Notable contracts inside `src/`:

| Path | Purpose |
| --- | --- |
| `src/Domain/Shared/Actions/BaseAction.php` | Base class for domain actions |
| `src/Domain/Shared/DTOs/BaseDTO.php` | Base class for data transfer objects |
| `src/Domain/Shared/Contracts/PipeableAction.php` | Pipeline contract |
| `src/Domain/Shared/Pipelines/ActionPipeline.php` | Action pipeline runner |
| `src/Enums/PermissionEnum.php` | Package-level permission enum |
| `src/Http/Responses/ApiResponse.php` | Standard API response helper |
| `src/Traits/HasMediaCollections.php` | Media library integration |
| `src/Traits/HasActivityLogging.php` | Activity log integration |

These ship via Composer. Upgrading the package upgrades these in place — no publish step is needed.

> **Note (v13.3+):** In a future major release, the `stubs/app/Domain/Shared/*` templates will be removed and domain actions/DTOs will extend the classes in `Lvntr\StarterKit\Domain\Shared\*` directly. This is not yet active — your current `App\Domain\Shared\*` files keep working.

### `stubs/` — templates copied into your app

`stubs/` is scaffolding. When you run `php artisan sk:install`, the installer copies these templates into your application, renaming namespaces where appropriate:

- `stubs/app/**` → `app/**` (namespace: `App\…`)
- `stubs/database/migrations/**` → `database/migrations/**`
- `stubs/config/settings.php` → `config/settings.php`
- `stubs/resources/js/app.ts`, `ssr.ts` → `resources/js/*`

Once published, **you own these files.** Edit them freely. The update flow (`php artisan sk:update`) is hash-aware: files you have modified are preserved, and only a curated set of paths (listed in `UpdateCommand::SAFE_UPDATE_PATHS`) are overwritten on update.

### `config/` — publishable config

`config/starter-kit.php` is merged at boot (`mergeConfigFrom`). Publish it to override:

```bash
php artisan vendor:publish --tag=starter-kit-config
```

### `resources/lang/` — translations

Translations ship under the `starter-kit::` namespace. Use them like:

```php
__('starter-kit::button.save');
trans('starter-kit::admin.users.title');
```

Publish to override:

```bash
php artisan vendor:publish --tag=starter-kit-lang
# → lang/vendor/starter-kit/{locale}/*.php
```

---

## Consuming Vue Components

The Vue components (FormBuilder, DatatableBuilder, TabBuilder, Skeleton, UI) live in `resources/js/components/` inside the package. You have two ways to consume them. Both are supported — pick the one that fits your workflow.

### Option A — Vendor-direct (recommended)

Alias `@lvntr` to the package's Composer-installed path. Components stay read-only in `vendor/`, so every `composer update` of the package ships the latest components automatically.

**`vite.config.ts`:**

```ts
import path from 'node:path';

export default defineConfig({
  resolve: {
    alias: {
      '@lvntr': path.resolve(
        __dirname,
        'vendor/lvntr/laravel-starter-kit/resources/js',
      ),
    },
  },
});
```

**Usage in your pages:**

```ts
import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
import SkDatatable from '@lvntr/components/DatatableBuilder/SkDatatable.vue';
```

Pros:

- Single source of truth, no copy.
- Package updates arrive for free.
- No accidental drift between your app and the package.

Cons:

- You cannot edit the components. If you need to customize one, switch to Option B for that component (or wrap it).

### Option B — Publish into your app

Copy the components into your application so you can fork/customize them. Useful when you need to tweak a specific component for your project.

```bash
php artisan vendor:publish --tag=starter-kit-components
# → resources/js/components/Lvntr-Starter-Kit/
```

Then alias `@lvntr` to your local copy:

```ts
// vite.config.ts
alias: {
  '@lvntr': path.resolve(__dirname, 'resources/js/components/Lvntr-Starter-Kit'),
}
```

Pros:

- Full control — edit any component.

Cons:

- Package updates do **not** reach the published copy. You must re-publish (or manually merge) when upgrading.
- Drift risk over time.

### Option C — Mixed

You can publish only a subset with a narrower tag, and let the rest come from `vendor/`. Tags: `starter-kit-form`, `starter-kit-datatable`, `starter-kit-tabs`, `starter-kit-skeleton`, `starter-kit-ui`.

This requires two separate aliases in `vite.config.ts` (one per subtree). Use only if you truly need to fork one builder.

---

## Summary

| Concern | Ships via | You own it? | Update behavior |
| --- | --- | --- | --- |
| `src/` classes | Composer (`vendor/`) | No | Replaced on `composer update` |
| `stubs/app/**` → `app/**` | `sk:install` copy | Yes | Hash-protected on `sk:update` |
| `config/starter-kit.php` | `mergeConfigFrom` + publish | If published | Merged on boot |
| Translations | `starter-kit::` namespace | If published | Overlayed on top of package |
| Vue components (Option A) | Composer (`vendor/`) | No | Replaced on `composer update` |
| Vue components (Option B) | `vendor:publish` | Yes | Stays until you re-publish |
