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
    - Light & Dark themes with instant-switch built-in `main` and `aura` kit themes (no rebuild)

## How to use it?

Start from a clean Laravel install:

```bash
composer create-project laravel/laravel my-app
cd my-app
composer require lvntr/laravel-starter-kit:^13.0
php artisan sk:install
```

That's it. The installer sets up migrations, seeders, Passport keys, a default admin user, and builds the frontend. It also ejects the `User` and `Role` domain runtime classes into `app/Domain/` so they are immediately project-owned and ready to customise. Pass `--without-eject` to keep them vendor-resident instead.

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

- [Upgrading between versions](docs/UPGRADE.md)
- [Changelog](./CHANGELOG.md)

## Module Ownership — What Runs From Vendor

The kit uses a "vendor-first" model for its built-in behaviour modules. Most modules run entirely from the package; you only own what you choose to own.

| Module | Vue pages | Controller / Requests | How to fully own |
|---|---|---|---|
| **Files** (File Manager) | vendor | vendor | `sk:eject Files` (Vue only) |
| **Logs** | vendor | vendor | `sk:eject Logs` |
| **Activity Logs** | vendor | vendor | `sk:eject ActivityLog` |
| **API Routes** | vendor | vendor | `sk:eject ApiRoute` |
| **Settings** | vendor | vendor | `sk:eject Setting` |
| **API Clients & Tokens** | vendor (Settings tabs) | vendor | `sk:eject ApiClient` (ejects ApiToken too) |
| **System Health** | vendor (Settings tab) | vendor (controller-only) | `sk:eject SystemHealth` |
| **Content Languages** | vendor (Settings tab) | vendor | `sk:eject ContentLanguage` |
| **Definitions** (API + Service) | — | vendor (controller-only) | `sk:eject Definitions` |
| **Media upload/delete** | — | vendor (controller-only) | `sk:eject MediaUpload` |
| **Users** | installed into app | app-owned | edit in place |
| **Roles** | installed into app | app-owned | edit in place |
| **Dashboard** | installed into app | app-owned | edit in place |
| **Auth screens** | installed into app | app-owned | edit in place |
| **Profile** | installed into app | app-owned | edit in place |

> **Models stay app-owned.** Even for fully vendor-resident modules, the Eloquent models (`App\Models\ContentLanguage`, `App\Models\Media`, `App\Models\Definition`, …) remain published in your app and are never relocated to vendor — so Laravel's policy discovery and route-model binding keep working. Vendor controllers reference them by their `App\` FQCN.

## Commands

| Command | Description |
|---|---|
| `php artisan sk:install` | Full installation: migrations, seeders, Passport keys, admin user, frontend build. Ejects `User` + `Role` by default (`--without-eject` to skip) |
| `php artisan sk:update` | Pull updated stubs (hash-tracked, preserves your edits); removes deprecated app copies of vendor-resident modules |
| `php artisan sk:publish [--tag=...]` | Publish specific asset groups (components, composables, plugins, lang, config, helpers) |
| `php artisan sk:eject <module>` | Copy a vendor-resident module (controller + FormRequests + Resources + Vue pages) into your app for full ownership. Supported: `User`, `Role`, `Setting`, `Logs`, `ActivityLog`, `ApiClient` (ejects ApiToken too), `ApiRoute`, `ContentLanguage`, `SystemHealth`, `Definitions`, `MediaUpload`, `Files` (Vue only), `Session`, `Media` |
| `php artisan make:sk-domain Foo` | Scaffold a new DDD domain |
| `php artisan sk:doctor` | Run system health checks |

## License

[PolyForm Noncommercial 1.0.0](./LICENSE)
