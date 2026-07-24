# Artisan Commands

This document is the command reference for the starter kit. Architectural notes for DDD live separately in [ddd.md](./ddd.md).

## End-User Commands

| Command                                   | Purpose                                                      |
| ----------------------------------------- | ------------------------------------------------------------ |
| `php artisan sk:doctor`                   | Run environment health checks and report any issues          |
| `php artisan sk:install`                  | Install the starter kit into the project                     |
| `php artisan sk:update`                   | Update installed kit files safely                            |
| `php artisan sk:upgrade`                  | Upgrade an older starter-kit/Laravel line to the current one |
| `php artisan sk:publish`                  | Publish optional components, language files, or config       |
| `php artisan sk:eject`                    | Eject a vendor-resident domain into the app for full customization |
| `php artisan make:sk-domain`              | Generate a new domain scaffold                               |
| `php artisan remove:sk-domain`            | Remove a generated domain                                    |
| `php artisan env:sync`                    | Sync `.env` keys into `.env.example`                         |
| `php artisan env:sync --reverse`          | Check whether `.env` is missing keys from `.env.example`     |
| `php artisan site:install`                | Reset and reinstall site data for local/dev usage            |
| `php artisan sk:seed-permissions --fresh` | Rebuild role and permission data from config                 |
| `php artisan postman:sync`                | Push the Scramble OpenAPI spec to Postman                    |
| `php artisan apidog:sync`                 | Push the Scramble OpenAPI spec to Apidog                     |
| `php artisan file-manager:purge-trash`    | Permanently delete old File Manager trash                    |

## `sk:doctor`

Runs a series of environment health checks and reports the result of each.

```bash
php artisan sk:doctor
php artisan sk:doctor --json
php artisan sk:doctor --only=database,redis
```

Checks covered: PHP extensions, database connection, Redis, Passport keys, storage symlink, writable directories, queue driver, scheduler, mail driver, npm build artifacts, config cache, and FileManager disk connection.

- `--json` outputs machine-readable JSON instead of a table
- `--only=<checks>` runs a comma-separated subset of checks (e.g. `--only=database,redis`)

Exit codes:

| Code | Meaning                          |
| ---- | -------------------------------- |
| `0`  | All checks passed                |
| `1`  | At least one check returned WARN |
| `2`  | At least one check returned FAIL |

## `sk:install`

Use this on first setup.

```bash
php artisan sk:install
php artisan sk:install --force
php artisan sk:install --no-interaction
php artisan sk:install --without-ai-skill
php artisan sk:install --without-eject
```

- `--force` overwrites existing publishable files
- `--no-interaction` accepts all defaults automatically; useful for CI or scripted installs
- `--without-ai-skill` skips publishing the Lvntr Starter Kit AI skills entirely — both the Claude Code copies (`.claude/skills/`) and their Codex mirror (`.codex/skills/`). Useful when the consumer uses neither Claude Code nor Codex with the kit's skill bundle
- `--without-eject` skips the default `User` and `Role` domain eject on a first install; the runtime stays in vendor and resolves via `class_alias`. Omit this flag to have `app/Domain/User/` and `app/Domain/Role/` created automatically. See [install.md](./install.md) for the ownership trade-off.

## `sk:update`

Use this after `composer update`.

```bash
php artisan sk:update
php artisan sk:update --dry-run
php artisan sk:update --force
php artisan sk:update --without-ai-skill
```

- `--without-ai-skill` skips regenerating the `.codex/skills/` AI-skill mirror for this run. (An install-time `--without-ai-skill` opt-out is honored automatically — skipped skills are never re-added.)

## `sk:upgrade`

Use this when moving between major starter-kit/Laravel lines, such as Laravel 12 -> 13.

```bash
php artisan sk:upgrade
php artisan sk:upgrade --force
php artisan sk:upgrade --skip-build
```

## `sk:publish`

Use this only when you want project-owned copies of package assets.

```bash
php artisan sk:publish
php artisan sk:publish --tag=components
php artisan sk:publish --tag=datatable
php artisan sk:publish --tag=form
php artisan sk:publish --tag=tabs
php artisan sk:publish --tag=skeleton
php artisan sk:publish --tag=ui
php artisan sk:publish --tag=filemanager
php artisan sk:publish --tag=composables
php artisan sk:publish --tag=plugins
php artisan sk:publish --tag=lang
php artisan sk:publish --tag=config
php artisan sk:publish --tag=helpers
```

## `sk:eject`

Use this when you need to fully customize a domain whose runtime currently runs from the vendor package. Ejecting copies the domain's backend classes into `app/Domain/{Name}/`, rewrites their namespaces to `App\Domain\{Name}\`, refreshes the domain's Vue pages, and wires any event/listener bindings into `app/Providers/DomainServiceProvider.php` so the audit log keeps firing. Run `--dry-run` first to preview what will change.

Unless `--force`, `--dry-run`, or `--no-interaction` is passed, the command asks for confirmation before doing any work — ejecting is a one-way trade-off (the domain stops receiving kit runtime updates via `composer update`). `sk:install`'s own internal default-domain eject always passes `--force`, so the fresh-install flow is not interrupted by this prompt.

```bash
php artisan sk:eject User
php artisan sk:eject User --dry-run
php artisan sk:eject User --force
php artisan sk:eject User --no-vue
php artisan sk:eject Role --destination=/tmp/eject-preview
php artisan sk:eject ApiClient          # controllers + requests + resources (ApiClient + ApiToken)
php artisan sk:eject ContentLanguage    # domain + controller + request + resource
```

- `--dry-run` prints the copy/rewrite/injection plan without writing any files. Always run this first.
- `--force` overwrites files that already exist — both the backend `app/Domain/{Name}/` tree and the domain's Vue pages. **Without `--force`, eject never overwrites an existing file:** an already-present `app/Domain/{Name}/` makes the command exit early, and any Vue page that already exists is left untouched and reported as preserved — only missing pages are written. This protects edits you made to pages shipped by `sk:install`.
- `--no-vue` skips refreshing the domain's Vue pages; only the backend classes are ejected.
- `--destination=<path>` redirects output to an arbitrary directory instead of the app root. Intended for isolated testing.
- `--skip-autoload` skips the `composer dump-autoload` call at the end of eject. Use this only when the calling process (such as `sk:install`) will run `composer dump-autoload` itself afterwards. Without this flag, eject always regenerates autoload and exits non-zero if regeneration fails.

> **Exit code:** if Composer's autoload regeneration fails (e.g. `composer` is missing or errors out), the command prints the error and **exits non-zero** even though the files were copied — so CI and scripts do not mistake a broken autoload for a successful eject. Run `composer dump-autoload` manually, then re-verify.

### Ejectable domains

Fourteen domains can be ejected. Domains not in this list are already app-owned and do not need ejecting.

| Domain            | Backend classes | Vue pages | HTTP layer ejected          | Event bindings injected |
| ----------------- | --------------- | --------- | --------------------------- | ----------------------- |
| `User`            | yes             | yes       | —                           | 3 (Created/Updated/Deleted) |
| `Role`            | yes             | yes       | —                           | 3 (Created/Updated/Deleted) |
| `Setting`         | yes             | yes       | controller + requests       | —                       |
| `Logs`            | yes             | yes       | controller + requests       | 1 (FilesDeleted)        |
| `ActivityLog`     | yes             | yes       | controller                  | —                       |
| `ApiClient`       | yes             | —         | ApiClient + ApiToken controllers + requests + resources | — |
| `ApiRoute`        | yes             | yes       | controller                  | —                       |
| `ContentLanguage` | yes             | —         | controller + requests + resource | —                  |
| `SystemHealth`    | no (controller-only) | —    | controller                  | —                       |
| `Definitions`     | no (controller-only) | —    | API + Service controllers   | —                       |
| `MediaUpload`     | no (controller-only) | —    | controller                  | —                       |
| `Files`           | no (Vue only)   | yes       | —                           | —                       |
| `Session`         | yes             | —         | —                           | —                       |
| `Media`           | yes             | —         | —                           | —                       |

**`ApiClient` ejects the API-token flow too:** the ApiClient domain owns both the OAuth client and the personal-access-token actions, so `sk:eject ApiClient` copies the `ApiClientController` **and** the `ApiTokenController` (plus their FormRequests and API Resources, and rewrites both `api-client-route.php` and `api-token-route.php` imports). The one-time Passport client-secret reveal stays byte-identical — eject moves the file, it does not change behavior.

**`SystemHealth`, `Definitions`, and `MediaUpload` are controller-only:** they have no `app/Domain/{Name}` backend tree. `SystemHealth` drives Artisan + `Gate` directly from its controller; `Definitions` ejects both the `Api\DefinitionController` and the `Service\DefinitionServiceController` (which wrap the vendor `DefinitionService` — that service stays vendor); `MediaUpload` ejects the `Api\MediaUploadController` whose `media.destroy` route lives in the shared `routes/web.php`. None ship a FormRequest or `app/Domain` folder, so no autoload-affecting class is added unless a controller is copied.

**Models stay app-owned — eject never relocates a Model.** `App\Models\{ContentLanguage,Media,Definition,...}` remain published in your app and are never aliased to vendor (an `App\Models\X` alias would break Laravel's `XPolicy` discovery and route-model binding). The vendor controllers/domains reference these models by their `App\` FQCN, and an ejected `app/Domain/ContentLanguage` keeps that `App\Models\ContentLanguage` reference unchanged.

**Why Auth and Helpers are not ejectable:** Auth screens are already 100% app-owned — `sk:update` keeps them fresh without any eject. The `sk-helpers.php` global helpers ship as a single overridable file; consumers delete what they do not need.

**`Files` is Vue-only:** the FileManager backend (controller, FormRequests, route-registry infrastructure) stays vendor-managed after ejecting `Files`. Only the admin Vue pages (`resources/js/pages/Admin/Files/`) are copied into your app so the UI can be customised while the backend continues to receive kit updates. To revert, delete the copied `resources/js/pages/Admin/Files/` directory — the vendor pages take over via `app.ts` fallback.

### What the namespace rewrite covers

Only the ejected domain's own namespace is rewritten. Every other vendor reference is left untouched:

- `Lvntr\StarterKit\Domain\User\Actions\CreateUserAction` → `App\Domain\User\Actions\CreateUserAction`
- `use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;` — **unchanged** (`Shared` base classes stay in vendor)
- `Lvntr\StarterKit\Http\Responses\ApiResponse` — **unchanged**
- Any other domain not being ejected — **unchanged**

### Update-loss trade-off

> **Warning:** after ejecting a domain, future `composer update` runs that include security fixes or bug fixes to that domain's vendor runtime will not apply to your copy. You own the files — you must apply upstream changes manually.

`sk:update` never touches backend files in `app/Domain/` (they are not hash-tracked stubs). Vue pages ejected with `--force` follow the normal hash-tracking rules: if you edit them, `sk:update` marks them as customized and skips them.

### Reverting an eject (v1: manual)

A `--revert` flag is planned for a future version. To revert manually:

1. Delete `app/Domain/{Name}/`.
2. Remove the `Event::listen(...)` lines for that domain from `app/Providers/DomainServiceProvider.php`.
3. Run `composer dump-autoload`.

The `class_alias` entries in `StarterKitServiceProvider` will resume resolving `App\Domain\{Name}\*` imports back to the vendor copies automatically.

## `make:sk-domain`

Creates a new domain with the starter kit structure.

```bash
# Bare domain (backward compatible)
php artisan make:sk-domain Article

# Namespaced
php artisan make:sk-domain Store/Product

# Core options
php artisan make:sk-domain Product --admin --api --events --fields="name:string,price:decimal"
php artisan make:sk-domain Product --from-migration=2026_03_21_create_products_table.php

# Opt-in extras — individual flags
php artisan make:sk-domain Article --with-policy --with-factory

# Opt-in extras — bulk syntax
php artisan make:sk-domain Article --with=policy,factory,test

# Relation scaffold
php artisan make:sk-domain Article --with-relations --relations="belongsTo:User,hasMany:Comment"

# Full
php artisan make:sk-domain Article --with=policy,factory,seeder,test,relations --relations="belongsTo:User,morphTo:commentable"
```

Opt-in flags (v2):

| Flag | What it generates |
| ---- | ----------------- |
| `--with-policy` | Policy class |
| `--with-factory` | Factory |
| `--with-seeder` | Seeder |
| `--with-test` | Feature test |
| `--with-relations` | Relation scaffold (use together with `--relations`) |
| `--with=policy,factory,test` | Bulk syntax — multiple opt-ins in a single flag |
| `--relations="belongsTo:User,hasMany:Comment,morphTo:commentable"` | Relation definitions for the scaffold |

Use it when you want the package conventions for actions, DTOs, queries, requests, routes, and Vue screens.

## `remove:sk-domain`

Removes a generated domain and its related files.

```bash
php artisan remove:sk-domain Product
```

## `env:sync`

Keeps `.env.example` aligned with the project `.env` keys.

```bash
php artisan env:sync
php artisan env:sync --reverse
```

`--reverse` is a safe validation mode: it does not write files, it only reports keys that exist in `.env.example` but are missing from `.env`.

## `site:install`

Useful in local development when you want a clean installation flow again.

```bash
php artisan site:install
```

The command shows the target environment and database before confirmation, only allows `local` and `setup`, and hard-blocks environments that look like production.

Since v13.4.1 the pipeline runs `passport:client --personal --provider=users` between `passport:keys` and the default admin seed, so a fresh install leaves you with a working personal-access-token path without any manual follow-up.

## `postman:sync`

Pushes the Scramble-generated OpenAPI spec to Postman so the workspace collection stays in sync with the current API surface.

```bash
php artisan postman:sync
```

Reads the `postman` settings group: `postman.api_key` and `postman.workspace_id` are required, and `postman.collection_id` is rewritten with the upstream id after a successful push. The command fails early with a clear error when the key or workspace id is missing — set them under **Settings → API Clients → Postman** in the admin panel (or insert the rows directly) and re-run. Internally it delegates to `App\Domain\ApiRoute\Actions\SyncPostmanAction`, which uses the shared `OpenApiExporter` helper to run `scramble:export` into a per-request temp file under `storage/app/postman/` and hands the spec to Postman unchanged. The Action imports the fresh collection first, persists the new UID, then best-effort deletes the old one — a failed push never leaves the workspace without a working collection.

## `apidog:sync`

Pushes the same Scramble OpenAPI spec to Apidog for teams that mirror the collection there.

```bash
php artisan apidog:sync
```

Reads the `apidog` settings group: `apidog.access_token` and `apidog.project_id` are required. If either value is missing the command aborts with a "not configured" error — populate them under **Settings → API Clients → Apidog** (or insert the rows directly) and re-run. The heavy lifting is done by `App\Domain\ApiRoute\Actions\SyncApidogAction`, which shares the `OpenApiExporter` helper with `postman:sync` — the spec is uploaded to Apidog unchanged so the pushed project mirrors the real server contract.

## `file-manager:purge-trash`

Permanently deletes soft-deleted File Manager items older than the configured age.

```bash
php artisan file-manager:purge-trash
php artisan file-manager:purge-trash --days=30
```

The default is 7 days. The command only targets File Manager media (`collection_name = files`) and trashed folders; avatar, logo, editor upload and other MediaLibrary collections are not touched. The shipped `routes/console.php` schedules it daily.
