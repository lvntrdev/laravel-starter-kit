# Lvntr Starter Kit

### Admin-first Laravel starter kit.

![CI](https://img.shields.io/github/actions/workflow/status/lvntrdev/laravel-starter-kit/ci.yml?branch=master&style=flat-square&label=CI)
![License](https://img.shields.io/badge/license-PolyForm--Noncommercial%201.0.0-f59e0b?style=flat-square)
![Packagist Version](https://img.shields.io/packagist/v/lvntr/laravel-starter-kit?style=flat-square&label=packagist)
![Downloads](https://img.shields.io/packagist/dt/lvntr/laravel-starter-kit?style=flat-square&label=downloads)

> ## ⚠️ WARNING
>
> This repository is under active development and is subject to frequent changes. The stability of the project is not yet guaranteed. Please consider the following points before use:
>
> 1. **Code Changes:** The directory structure or core classes may undergo radical changes without prior notice.
> 2. **Update Process:** Updates may not always provide an automated migration path. In addition to running update commands, you may need to perform manual interventions by checking the `README` or `CHANGELOG` files.
> 3. **Risk:** Significant changes may lead to data loss or breaking issues in your existing project.

## Introduction

Lvntr Starter Kit is a full-featured admin panel for Laravel, built with **Laravel 13**, **Inertia.js v3**, **Vue 3**, **PrimeVue 4** and **Tailwind CSS 4**.

Unlike the official Laravel starter kits, which ship a minimal authentication scaffold, this kit gives you a production-ready admin panel on day one: users, roles, permissions, activity logs, settings, file manager, 2FA, and a DDD-style domain layer you can extend.

It is designed for teams who want to skip re-building the same admin screens on every project and go straight to business features.

> **Website & Documentation:** [starter-kit.lvntr.dev](https://starter-kit.lvntr.dev/)
> Installation guide, component references, architecture notes and examples.

## Screenshots

![Dark & Light themes](https://starter-kit.lvntr.dev/shots/dark-light.png)

![Login screen](https://starter-kit.lvntr.dev/shots/auth-login.png)

![User management](https://starter-kit.lvntr.dev/shots/admin-users.png)

![Roles & permissions](https://starter-kit.lvntr.dev/shots/admin-permissions.png)

![File manager](https://starter-kit.lvntr.dev/shots/admin-file-manager.png)

## What is Inside?

- **Authentication**
    - Login / Register / Password Reset
    - Email Verification
    - Two-Factor Authentication (Fortify)
    - OAuth2 API with Laravel Passport
- **User & Access Management**
    - User CRUD with avatar upload and soft deletes
    - Roles & dynamic resource-scoped permissions (Spatie)
    - Session management
- **Admin Modules**
    - Dashboard
    - Activity Logs (browsable, filterable)
    - Settings panel (General / Auth / Mail / Storage / File Manager)
    - File Manager with pluggable contexts and signed share links
    - API Clients & Personal Access Token management
    - System Health dashboard
    - API Routes explorer
    - Definitions (DB-backed enums used across forms and tables)
- **Developer Tooling**
    - DDD-style domain layer (Actions / DTOs / Queries / Events / Listeners)
    - FormBuilder, DatatableBuilder, TabBuilder fluent APIs (including [Translatable Fields](./docs/translatable-fields.md))
    - Domain scaffolding via `make:sk-domain` with opt-in flag support
    - Datatable bulk actions with cross-page selection
    - Safe upgrade flow via `sk:update` (hash-tracked, preserves your edits)
    - System health check via `sk:doctor`
    - Light & Dark themes

## How to use it?

Start from a clean Laravel install:

```bash
composer create-project laravel/laravel my-app
cd my-app
composer require lvntr/laravel-starter-kit:^13.0
php artisan sk:install
```

That's it. The installer sets up migrations, seeders, Passport keys, a default admin user, and builds the frontend.

Full step-by-step guide: [starter-kit.lvntr.dev/docs/install](https://starter-kit.lvntr.dev/docs/install)

## Requirements

- PHP 8.4+
- Laravel 13
- Node.js 18+
- MySQL or MariaDB

## Compatibility & Versioning

The package version major aligns with the supported Laravel major. Each
Laravel major gets its own maintenance branch and `vN.x.y` tag stream;
existing consumer constraints stay locked to their major and never
receive breaking changes from a newer Laravel target.

| Laravel | Constraint                                            | Branch  | Status      |
|---------|-------------------------------------------------------|---------|-------------|
| 13.x    | `composer require lvntr/laravel-starter-kit:^13.0`    | `13.x`  | active      |

`main` tracks the currently active major (today: `13.x`). When a future
Laravel release is targeted, `main` will move to that next-major dev
stream and the previous major's `N.x` branch will continue to receive
backports.

## Documentation

Everything — installation, update flow, domain scaffolding, FormBuilder / DatatableBuilder / TabBuilder APIs, composables, file manager, roles & permissions, OAuth2 API, activity logs, settings — lives on the official site:

**[starter-kit.lvntr.dev](https://starter-kit.lvntr.dev/)**

- [Upgrading between versions](docs/UPGRADE_.md)
- [Changelog](./CHANGELOG.md)

## Commands

| Command | Description |
|---|---|
| `php artisan sk:install` | Full installation: migrations, seeders, Passport keys, admin user, frontend build |
| `php artisan sk:update` | Pull updated stubs (hash-tracked, preserves your edits) |
| `php artisan sk:publish [--tag=...]` | Publish specific asset groups (components, config, stubs, migrations) |
| `php artisan make:sk-domain Foo` | Scaffold a new DDD domain |
| `php artisan sk:doctor` | Run system health checks |

### `sk:doctor` — System Health

Runs 12 checks and reports: PHP extensions, database, Redis, Passport keys, storage symlink, writable directories, queue driver, schedule, mail driver, npm build artifacts, config cache, FileManager disk.

```bash
# Human-readable output
php artisan sk:doctor

# JSON output (CI-friendly)
php artisan sk:doctor --json

# Run only specific checks
php artisan sk:doctor --only=database,redis,passport-keys
```

Exit codes: `0` OK, `1` WARN (non-critical), `2` FAIL (action required).

The `/admin/system-health` admin page runs these checks on demand. Permission required: `system.health.view`.

### `make:sk-domain` — Domain Generator

```bash
# Minimal scaffold (unchanged from previous versions)
php artisan make:sk-domain Post

# Opt-in extras individually
php artisan make:sk-domain Post --with-policy --with-test --with-factory

# Opt-in extras as a comma list
php artisan make:sk-domain Post --with=policy,factory,seeder,test

# With relationship scaffolding
php artisan make:sk-domain Comment --with-relations \
  --relations="belongsTo:Post,morphTo:commentable"
```

Flag reference:

| Flag | Generates |
|---|---|
| `--with-policy` | `PostPolicy` registered in `AuthServiceProvider` |
| `--with-factory` | `PostFactory` |
| `--with-seeder` | `PostSeeder` |
| `--with-test` | Pest feature test stub |
| `--with-relations` | Relation methods in model + migration foreign keys |
| `--with=a,b,c` | Shorthand for multiple `--with-*` flags |
| `--relations="..."` | Relation definitions (implies `--with-relations`) |

## File Manager

### Signed Share Links

Generate time-limited, publicly accessible URLs for private files.

```bash
# Config keys in config/file-manager.php
file-manager.share.enabled          # true/false
file-manager.share.default_ttl_hours # default: 24
file-manager.share.max_ttl_hours    # default: 720
file-manager.share.allow_revoke     # true/false
```

**Create a share link:**
```
POST /file-manager/share
{ "media_id": 42, "ttl_hours": 48 }

→ { "url": "https://...?expires=...&signature=...", "expires_at": "..." }
```

**Revoke a share link:**
```
POST /file-manager/share/revoke
{ "media_id": 42, "signed_token_hash": "..." }
```

**Access a shared file:**
```
GET /file-manager/share/{media}?expires=...&signature=...
```

Permissions: `share-media`, `revoke-share-media`.

## Datatable Bulk Actions

`SkDatatable` supports cross-page selection and bulk operations via the `BulkAction` interface and `BulkActionDispatcher`.

**Request payload:**
```json
{
  "action": "bulk-delete-users",
  "ids": [1, 2, 3],
  "select_all_filtered": false,
  "filter_snapshot": { "search": "...", "role": "editor" }
}
```

**Response:**
```json
{ "processed": 3, "skipped": 0, "failed": 0, "message": "3 users deleted." }
```

When `select_all_filtered: true`, the action receives the filter snapshot and resolves affected records server-side — no client-side ID enumeration required.

Built-in stub examples available via `php artisan sk:publish --tag=starter-kit-stubs`:
- `BulkDeleteUserAction` — rank-aware; prevents self-deletion
- `BulkDeleteRoleAction` — skips system-protected roles

## API Clients & Tokens

Admin routes: `/admin/api-clients`, `/admin/api-tokens`.

Supports Passport `authorization_code` and `client_credentials` grants, and Personal Access Tokens.

Permissions:

| Permission | Scope |
|---|---|
| `api-clients.create` | Create OAuth clients |
| `api-clients.read` | List / view clients |
| `api-clients.update` | Edit client name / redirect URIs |
| `api-clients.delete` | Delete clients |
| `api-tokens.create` | Mint Personal Access Tokens |
| `api-tokens.read` | List tokens |
| `api-tokens.delete` | Revoke tokens |

**Security notes:**

- Client secret and token plaintext are shown exactly once at creation via `OneTimeSecretModal` (cannot be dismissed without copying). Subsequent views show only masked values.
- `redirect_uris` must use HTTPS. `localhost` and `127.0.0.1` are allowed with HTTP (RFC 8252 §8.3). Enforced by `HttpsOrLocalhostUrl` validation rule.
- All clients are created as `confidential = true`. Public (non-confidential) clients cannot be created through the UI.
- Personal Access Tokens are always minted for the currently authenticated admin; `user_id` in the request body is rejected.

## License

[PolyForm Noncommercial 1.0.0](./LICENSE)
