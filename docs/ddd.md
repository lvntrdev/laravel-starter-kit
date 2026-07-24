# DDD

This document explains the domain-first structure used by the starter kit. It is a DDD-style organization centered around domains instead of controllers.

## Goal

The main goal is to keep business rules out of large controllers and move them into predictable domain folders.

## Domain Structure

Typical structure after installation:

```text
app/Domain/
├── Auth/
├── Role/
│   └── BulkActions/
└── User/
    └── BulkActions/
```

Domains that are fully managed by the package and run from vendor are not scaffolded into `app/` on a fresh install. See [Vendor-resident domains](#vendor-resident-domains) below.

### Vendor-resident domains

The following domains have their **runtime layer** (Actions, DTOs, Queries, Events, Listeners, Services) inside the package (`src/Domain/`, `Lvntr\StarterKit\Domain\`). They are not copied into your app on install.

| Domain | Vendor namespace |
|---|---|
| `FileManager` | `Lvntr\StarterKit\Domain\FileManager\` |
| `Shared` | `Lvntr\StarterKit\Domain\Shared\` |
| `ActivityLog` | `Lvntr\StarterKit\Domain\ActivityLog\` |
| `Logs` | `Lvntr\StarterKit\Domain\Logs\` |
| `Session` | `Lvntr\StarterKit\Domain\Session\` |
| `Media` | `Lvntr\StarterKit\Domain\Media\` |
| `ApiClient` | `Lvntr\StarterKit\Domain\ApiClient\` |
| `ApiRoute` | `Lvntr\StarterKit\Domain\ApiRoute\` |
| `Role` | `Lvntr\StarterKit\Domain\Role\` |
| `Setting` | `Lvntr\StarterKit\Domain\Setting\` |
| `User` | `Lvntr\StarterKit\Domain\User\` |

**Import compatibility:** controllers and providers that use `App\Domain\<Module>\...` import paths continue to work — `StarterKitServiceProvider` registers `class_alias` entries that resolve them to the vendor namespace. A local `app/Domain/<Module>/` copy, if present, always takes precedence (the guard skips the alias when the file exists on disk).

**Surface ownership is split per module.** Models always stay app-owned (so policy discovery and route-model binding keep working). For **user-facing** modules (`User`, `Role`, Dashboard, Auth, Profile) the Controllers, FormRequests, Vue pages, and route files are scaffolded into your app. For **vendor-first behaviour** modules (Files, Logs, Activity Logs, API Routes, Settings, …) the entire HTTP + Vue surface runs from the package — only their Models live in `app/`; run `sk:eject <Module>` to take ownership. See the Module Ownership table in the [README](../README.md).

**Existing app copies:** if your project was installed before a domain moved to vendor, your existing `app/Domain/<Module>/` files are preserved and continue to work. Removing them is optional — see [UPGRADE.md](./UPGRADE.md) for reconcile steps.

Inside a domain you will usually see these layers:

- `Actions` for write operations and use-case orchestration
- `DTOs` for carrying validated data
- `Queries` for list and datatable query logic
- `Events` for domain events
- `Listeners` for side effects such as logging
- `Repositories` or `Contracts` when the domain needs abstraction

## Request Flow

Typical flow:

1. Controller receives the request.
2. Form Request validates it.
3. DTO normalizes the payload.
4. Action performs the business operation.
5. Event is fired if side effects are needed.
6. Listener reacts without bloating the controller.
7. Response returns through `to_api()` or an Inertia redirect.

## Rules Of Thumb

- keep controllers thin
- keep validation in Form Requests
- keep complex writes in Actions
- keep reusable list logic in Queries
- keep side effects in Listeners
- keep kit-level shared cross-domain code under `src/Domain/Shared`; use `app/Domain/Shared` only for project-owned or ejected code

## Why It Helps

- better readability in large admin projects
- easier testing of business operations
- safer refactoring over time
- less duplication between web and API controllers

## Related Commands

The domain structure is supported by scaffolding commands, but the command reference lives in [artisan-commands.md](./artisan-commands.md). This file exists specifically to keep DDD guidance separate from command documentation.

### `make:sk-domain` core flags

Beyond the domain name and `--fields=`, the wizard's layer/ID/Vue choices can all be passed non-interactively:

| Flag | What it does |
| --- | --- |
| `--fields="name:string,age:integer"` | Comma-separated `field:type` pairs. Available types: `string`, `integer`, `bigInteger`, `unsignedBigInteger`, `float`, `decimal`, `boolean`, `text`, `longText`, `json`, `date`, `dateTime`, `timestamp`. Omit to be prompted field-by-field. |
| `--id-type=id\|uuid\|ulid` | Primary key strategy. `id` (default) is an auto-increment bigint; `uuid`/`ulid` add the matching `HasUuids`/`HasUlids` concern and switch the migration's `id` column. Prompts interactively when omitted — skipped entirely with `--from-migration` (detected from the file). |
| `--api` / `--no-api` | Force-generate or force-skip the API controller + routes. Prompts (default: yes) when neither is passed. |
| `--admin` / `--no-admin` | Force-generate or force-skip the Admin controller + routes. Prompts (default: yes) when neither is passed. |
| `--events` / `--no-events` | Force-generate or force-skip the Created/Updated/Deleted events and their logging listeners. Prompts (default: yes) when neither is passed. |
| `--soft-deletes` / `--no-soft-deletes` | Force-enable or force-disable `SoftDeletes` on the model and migration. Prompts (default: yes) when neither is passed — skipped entirely with `--from-migration` (detected from the file). |
| `--vue=none\|empty\|full` | Vue page generation mode; only applies when the Admin layer is generated (forced to `none` otherwise). `full` scaffolds Index (DataTable) + Create/Edit (FormBuilder); `empty` scaffolds an empty Index page only; `none` skips Vue generation. Prompts interactively (default: `full`) when omitted. |
| `--vue-fields` / `--no-vue-fields` | Only relevant with `--vue=full`. Include the model's fields in the generated DataTable columns and FormBuilder, or generate an id-only skeleton. Prompts (default: yes) when neither is passed and fields exist. |
| `--from-migration=<filename>` | Parse fields, ID type, and soft-deletes from an existing migration file instead of `--fields`/`--id-type`/prompts, e.g. `--from-migration=2026_03_21_create_products_table.php`. Accepts a full or partial filename (glob-matched under `database/migrations/`). |

### `make:sk-domain` v2 opt-in flags

Calling the command without any flags preserves the v13.5.x behavior (backward compatible).

**Individual flags:**

| Flag | What it generates |
| --- | --- |
| `--with-policy` | Policy class |
| `--with-factory` | Factory |
| `--with-seeder` | Seeder |
| `--with-test` | Feature test |
| `--with-relations` | Relation scaffold (use together with `--relations`) |

**Bulk syntax** — pass any combination of `policy`, `factory`, `seeder`, `test`, `relations` in one flag (individual `--with-*` flags are additive on top of it):

```bash
php artisan make:sk-domain Article --with=policy,factory,test
```

**Relations syntax:**

```bash
php artisan make:sk-domain Article --with-relations --relations="belongsTo:User,hasMany:Comment,morphTo:commentable"
```

Supported relation types: `belongsTo`, `hasMany`, `morphTo`. Passing `--relations=` implies `--with-relations`.

**Examples:**

```bash
# Domain only — v13.5.x behavior, backward compatible
php artisan make:sk-domain Article

# Policy and factory
php artisan make:sk-domain Article --with-policy --with-factory

# Bulk syntax
php artisan make:sk-domain Article --with=policy,factory,test

# With relations
php artisan make:sk-domain Article --with-relations --relations="belongsTo:User,hasMany:Comment"

# Full
php artisan make:sk-domain Article --with=policy,factory,seeder,test,relations --relations="belongsTo:User,morphTo:commentable"
```
