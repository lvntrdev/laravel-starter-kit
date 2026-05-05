# Artisan Commands

This document is the command reference for the starter kit. Architectural notes for DDD live separately in [ddd.md](./ddd.md).

## End-User Commands

| Command                                   | Purpose                                                      |
| ----------------------------------------- | ------------------------------------------------------------ |
| `php artisan sk:install`                  | Install the starter kit into the project                     |
| `php artisan sk:update`                   | Update installed kit files safely                            |
| `php artisan sk:upgrade`                  | Upgrade an older starter-kit/Laravel line to the current one |
| `php artisan sk:publish`                  | Publish optional components, language files, or config       |
| `php artisan make:sk-domain`              | Generate a new domain scaffold                               |
| `php artisan remove:sk-domain`            | Remove a generated domain                                    |
| `php artisan env:sync`                    | Sync `.env` keys into `.env.example`                         |
| `php artisan env:sync --reverse`          | Check whether `.env` is missing keys from `.env.example`     |
| `php artisan site:install`                | Reset and reinstall site data for local/dev usage            |
| `php artisan sk:seed-permissions --fresh` | Rebuild role and permission data from config                 |
| `php artisan postman:sync`                | Push the Scramble OpenAPI spec to Postman                    |
| `php artisan apidog:sync`                 | Push the Scramble OpenAPI spec to Apidog                     |
| `php artisan file-manager:purge-trash`    | Permanently delete old File Manager trash                    |

## `sk:install`

Use this on first setup.

```bash
php artisan sk:install
php artisan sk:install --force
php artisan sk:install --no-interaction
php artisan sk:install --without-ai-skill
```

- `--force` overwrites existing publishable files
- `--no-interaction` accepts all defaults automatically; useful for CI or scripted installs
- `--without-ai-skill` skips publishing the Lvntr Starter Kit AI skill (`stubs/.claude/skills/`) — useful when the consumer does not use Claude Code with the kit's skill bundle

## `sk:update`

Use this after `composer update`.

```bash
php artisan sk:update
php artisan sk:update --dry-run
php artisan sk:update --force
```

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
php artisan sk:publish --tag=lang
php artisan sk:publish --tag=config
```

## `make:sk-domain`

Creates a new domain with the starter kit structure.

```bash
php artisan make:sk-domain Product
php artisan make:sk-domain Store/Product
php artisan make:sk-domain Product --admin --api --events --fields="name:string,price:decimal"
php artisan make:sk-domain Product --from-migration=2026_03_21_create_products_table.php
```

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
