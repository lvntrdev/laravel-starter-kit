# Updating the kit safely (`sk:update` flow)

> Reference detail for the `lvntr-starter-kit` skill — read on demand.

The kit tracks every published file in `storage/starter-kit/hashes.json`
(format v2). On `sk:update`:

1. **Hash-tracked files** (the published scaffold): if your file's hash still
   matches what the kit shipped, `sk:update` overwrites it with the new
   version. If not (you customized it), it is **skipped and reported**.

2. **SAFE_UPDATE paths** go through the same hash guard: refreshed only while
   your copy is unmodified (or with `--force`); an edited copy is preserved
   and reported separately, an untracked one is prompted for. The list is
   intentionally tiny:
   - `app/Enums/PermissionEnum.php` — keeps it in sync with the package's
     permission constants. Prefer not to hand-edit it so it keeps updating.

3. **NEVER_UPDATE paths** are installed once and never overwritten:
   - `config/permission-resources.php` (your resource matrix)
   - `config/settings.php` (your setting groups + `sensitive_keys` whitelist)
   - `package.json` — never copied over; it is **merged** instead.
     `dependencies`, `devDependencies` and `scripts` are unioned: your own
     entries (and their order) stay, the kit's own entries take the kit's
     current value. `sk:install` merges an existing `package.json` the same
     way, and `sk:update --dry-run` reports the merge without writing.

4. **Vendor-resident paths** (domain runtimes, kit middleware, helpers,
   `ApiException(Handler)`, FileManager HTTP layer, …) are **not copied at
   all** — they run from the vendor package. If an old app copy exists it is
   only *reported* (never auto-deleted), and that app copy keeps winning via
   the alias-skip invariant. Exception: the six kit migration app copies are
   force-deleted (safe — their basenames are already in the `migrations`
   table).

5. **Skipped-at-install paths** (`--without-ai-skill`) are recorded with a
   `__skipped__` sentinel and never re-added by update. `sk:update
   --without-ai-skill` skips the AI-skill refresh for a single run.

6. **Run `--dry-run` first.** Use `--force` only if your customizations are
   safe to lose — it ignores the registry and overwrites everything tracked.

7. **Migrations:** `sk:update` offers `migrate` whenever a migration is
   pending — a stub migration it just copied, or a kit migration auto-loaded
   from `vendor/` that is not in the `migrations` table yet. Declined (or a
   non-interactive run that skipped it)? Run `php artisan migrate`: a kit
   page whose table is missing answers with a 500.

8. **After update:** re-run `npm install && npm run build`; read
   `vendor/lvntr/laravel-starter-kit/CHANGELOG.md` (every entry newer than
   the version you came from — `composer show lvntr/laravel-starter-kit`
   prints the installed one) and `vendor/lvntr/laravel-starter-kit/docs/UPGRADE.md`
   for breaking notes and hand-applied steps (e.g. the v13.5.11 → v13.6.0
   theme-tree migration). Both ship inside the package. If a kit dependency's version
   moved and `npm install` fails with `ERESOLVE`, `sk:update` prints the
   recovery: `rm -rf node_modules package-lock.json && npm install && npm run build`.

## Before `sk:update` — the Composer step

```bash
composer sk-update        # = composer update lvntr/laravel-starter-kit -W, then php artisan sk:update
# or, when the app's composer.json has no sk-update script yet:
composer update lvntr/laravel-starter-kit -W
```

If `sk:update` or `sk:doctor` says Composer kept the kit at an older
release, run `composer why-not lvntr/laravel-starter-kit <version>` — it names
the package holding the kit back.

**Keep the `-W`.** Without it Composer leaves the kit's own dependencies at
their locked versions; when a new kit release needs a newer one (13.8.2+
needs `lvntr/api-dock` `~0.0.8`) it quietly installs the newest kit that
still fits the old lock instead of failing — the app looks updated but is
stuck on an older kit.

**Never `composer require` a kit dependency yourself** (e.g.
`lvntr/api-dock`) — Composer writes a `^0.0.x` constraint that pins one exact
patch and blocks every later kit update. If the app's `composer.json` already
lists one:

```bash
composer remove lvntr/api-dock --no-update
composer update lvntr/laravel-starter-kit -W
```

`php artisan sk:doctor` flags both cases (missing or root-pinned kit
dependency) under **Kit Dependencies**; `sk:update` also warns and, in an
interactive terminal, offers to run the `-W` update.

To customize something that runs from vendor: **publish it** (`sk:publish` —
components, composables, plugins, lang, config, helpers…) or **eject the
domain** (`sk:eject {Domain}`) and edit the app copy. Never edit vendor files
in place.
