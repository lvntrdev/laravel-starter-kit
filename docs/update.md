# Update

This guide explains the safest way to update the starter kit in an existing project.

> **Hardening / security releases:** When the release notes mention edits to **published files** (files that `sk:install` copied into your app — controllers, requests, policies, composables, configs), `sk:update` will **not** overwrite them if you have modified them locally (which is the common case). For those releases, follow [UPGRADE.md](./UPGRADE.md) — it contains the diff-style patch list you need to apply by hand, plus a smoke-test checklist.
>
> The split is deliberate: the `composer update` tier moves package-internal code (`vendor/lvntr/laravel-starter-kit/src/`), and the UPGRADE guide moves the copy-in-your-app tier.

> **v13.4.1:** This release also ships three install-time fixes (OAuth UUID migrations, Postman settings migration, Passport personal access client provisioning) in addition to the published-file patches — see [UPGRADE.md §7](./UPGRADE.md) for the commands existing installs must run once.

## Recommended Workflow

1. Commit your current work.
2. Preview the package update.
3. Apply the package update.
4. Run migrations, env sync, and rebuild assets. (v13.4.1: also re-run the `oauth_*` migrations — see [UPGRADE.md §7.1](./UPGRADE.md).)
5. Re-check permissions, routes, auth/settings screens, and critical pages.

## 1. Update Composer Package

```bash
composer update lvntr/laravel-starter-kit
```

## 2. Preview Changes First

```bash
php artisan sk:update --dry-run
```

Use `--dry-run` before real updates when the project has custom controllers, routes, pages, or config decisions.

## 3. Apply The Update

```bash
php artisan sk:update
```

### What `sk:update` Does

- runtime code (`Domain/Shared/`, Traits, Middleware, helpers, `ApiResponse`, FileManager layer) lives in `vendor/` since v13.5.0 — `composer update` is sufficient, `sk:update` does not copy these
- removes deprecated app-side files that have been moved to vendor
- notifies of hash-tracked stub changes (auth/layout Vue components, user/role/settings skeleton); applies them only when the local hash still matches
- updates user-modifiable files only if they were not changed locally
- asks how to handle untracked files
- adds new files introduced by the package
- injects missing filesystem and media library config pieces
- can optionally run newly added migrations

## 4. Force Mode

```bash
php artisan sk:update --force
```

Use this only when you intentionally want package files to overwrite local changes.

## 5. Post-Update Checklist

Run these after a successful update:

```bash
npm install
npm run build
php artisan migrate
php artisan env:sync
```

If you changed permission resources or roles, also run:

```bash
php artisan sk:seed-permissions --fresh
```

If the update introduced new settings groups or auth behavior, also review these screens once:

- Settings -> Auth
- Settings -> Turnstile
- Settings -> File Manager
- Profile security tabs

## File Update Strategy Summary

- Safe core paths are overwritten automatically.
- Customizable files are protected unless unchanged.
- `config/permission-resources.php` is treated as a user-owned file.
- New package files are added automatically.

## Rolling Back a Customized File

There is no dedicated `sk:rollback` command — rollback is done through `sk:publish --force` on the tag that owns the file. This is deliberate: it keeps the code path identical to a fresh install, so recovery never relies on shadow state.

```bash
# List available tags
php artisan sk:publish --help

# Reset a single customizable area (e.g. only the FormBuilder) to the package's shipped version
php artisan sk:publish --tag=form --force

# Reset to an isolated directory first to inspect the diff without touching your code
php artisan sk:publish --tag=form --destination=/tmp/sk-compare
diff -ru resources/js/components/Lvntr-Starter-Kit/FormBuilder /tmp/sk-compare/resources/js/components/Lvntr-Starter-Kit/FormBuilder
```

Commit before `--force` so Git keeps the old version reachable. For whole-project recovery (infrastructure config inject mistakes etc.), re-run `php artisan sk:install` — it is idempotent and re-applies AST injections only when keys are missing.

## When To Use `sk:upgrade` Instead

Use `sk:upgrade` when you are crossing a starter-kit or Laravel major line, such as Laravel 12 -> 13. Use normal `sk:update` for same-line package updates.

```bash
php artisan sk:upgrade
php artisan sk:upgrade --force
php artisan sk:upgrade --skip-build
```

## When To Read This Together With Other Docs

- read [install.md](./install.md) for first-time setup
- read [artisan-commands.md](./artisan-commands.md) for command details
- read [project-documentation.md](./project-documentation.md) before updating deeper architecture pieces
