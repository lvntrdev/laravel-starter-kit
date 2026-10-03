# Lvntr Starter Kit

### Admin-first Laravel starter kit.

![CI](https://img.shields.io/github/actions/workflow/status/lvntrdev/laravel-starter-kit/ci.yml?branch=main&style=flat-square&label=CI)
![License](https://img.shields.io/badge/license-MIT-3b82f6?style=flat-square)
![Packagist Version](https://img.shields.io/packagist/v/lvntr/laravel-starter-kit?style=flat-square&label=packagist)
![Downloads](https://img.shields.io/packagist/dt/lvntr/laravel-starter-kit?style=flat-square&label=downloads)

![Lvntr Starter Kit dashboard](.github/screenshots/dashboard-aura-light.jpg)

## Introduction

Lvntr Starter Kit is a full-featured admin panel for Laravel, built with **Laravel 13**, **Inertia.js v3**, **Vue 3**, **PrimeVue 4** and **Tailwind CSS 4**.

Unlike the official Laravel starter kits, which ship a minimal authentication scaffold, this kit gives you a production-ready admin panel on day one: users, roles, permissions, activity logs, settings, file manager, 2FA, and a DDD-style domain layer you can extend.

It is designed for teams who want to skip re-building the same admin screens on every project and go straight to business features.

> **Website & Documentation:** [starter-kit.lvntr.dev](https://starter-kit.lvntr.dev/)
> Installation guide, component references, architecture notes and examples.

## A Quick Tour

### Two themes, light & dark — switch instantly, no rebuild

Pick the built-in **Aura** (inset panel inside a brand-coloured frame) or **Main** theme, choose from 26 accent colours, and let every user toggle dark mode.

<table>
  <tr>
    <td width="50%"><img src=".github/screenshots/dashboard-aura-dark.jpg" alt="Aura theme, dark mode"><br><sub><b>Aura</b> · dark</sub></td>
    <td width="50%"><img src=".github/screenshots/dashboard-main-light.jpg" alt="Main theme, light mode"><br><sub><b>Main</b> · light</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src=".github/screenshots/dashboard-main-dark.jpg" alt="Main theme, dark mode"><br><sub><b>Main</b> · dark</sub></td>
    <td width="50%"><img src=".github/screenshots/settings-appearance.jpg" alt="Appearance settings"><br><sub>Theme, accent colour, logos and favicon from the Appearance settings</sub></td>
  </tr>
</table>

### Users, roles & permissions

Searchable, filterable datatables with server-side pagination, column toggles and bulk actions; dialog forms built with FormBuilder; and a resource × ability permission matrix per role.

<table>
  <tr>
    <td width="50%"><img src=".github/screenshots/users.jpg" alt="User management"><br><sub>User management</sub></td>
    <td width="50%"><img src=".github/screenshots/users-edit.jpg" alt="Edit user dialog"><br><sub>Edit user dialog with avatar upload</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src=".github/screenshots/roles.jpg" alt="Roles"><br><sub>Roles</sub></td>
    <td width="50%"><img src=".github/screenshots/role-permissions.jpg" alt="Role permission matrix"><br><sub>Per-role permission matrix</sub></td>
  </tr>
</table>

### File manager

Folders, favourites, trash with a retention period, image/video/PDF previews, storage quota — on local disk, Amazon S3, DigitalOcean Spaces or Hetzner Object Storage.

<table>
  <tr>
    <td width="50%"><img src=".github/screenshots/file-manager.jpg" alt="File manager"><br><sub>File manager</sub></td>
    <td width="50%"><img src=".github/screenshots/settings-file-manager.jpg" alt="File manager settings"><br><sub>Upload size, quota, accepted types and trash</sub></td>
  </tr>
</table>

### Activity logs & log viewer

Every model change is recorded with its causer and a field-level old → new diff. Laravel log files are browsable in the panel, filterable by level, time and message.

<table>
  <tr>
    <td width="50%"><img src=".github/screenshots/activity-logs.jpg" alt="Activity logs"><br><sub>Activity logs</sub></td>
    <td width="50%"><img src=".github/screenshots/activity-log-detail.jpg" alt="Activity log detail"><br><sub>Field-level change detail</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src=".github/screenshots/log-files.jpg" alt="Log files"><br><sub>Log files</sub></td>
    <td width="50%"><img src=".github/screenshots/log-viewer.jpg" alt="Log viewer"><br><sub>Log viewer with level filters and context</sub></td>
  </tr>
</table>

### Settings panel

Everything a project usually hard-codes in `.env` is editable from the panel — identity, locale and currency, security policy, SMTP, storage driver, content languages, API clients/tokens — plus a System Health page that runs the `sk:doctor` checks.

<table>
  <tr>
    <td width="50%"><img src=".github/screenshots/settings-general.jpg" alt="General settings"><br><sub>General</sub></td>
    <td width="50%"><img src=".github/screenshots/settings-security.jpg" alt="Security settings"><br><sub>Security — registration, 2FA, password policy, bot protection</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src=".github/screenshots/settings-mail.jpg" alt="Mail settings"><br><sub>Mail — SMTP and test email</sub></td>
    <td width="50%"><img src=".github/screenshots/settings-storage.jpg" alt="Storage settings"><br><sub>Storage driver</sub></td>
  </tr>
  <tr>
    <td width="50%"><img src=".github/screenshots/settings-content-languages.jpg" alt="Content languages"><br><sub>Content languages for translatable fields</sub></td>
    <td width="50%"><img src=".github/screenshots/settings-system-health.jpg" alt="System health"><br><sub>System health</sub></td>
  </tr>
</table>

### Component showcase

A built-in page documents the kit's PrimeVue + SK components in every variant — tags, buttons, messages, toasts and FormBuilder forms.

![Component showcase](.github/screenshots/components.jpg)

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
    - Settings panel (General / Auth / Mail / Storage / File Manager / Content Languages)
    - Multi-language content: active languages managed in Settings drive every [Translatable Field](./docs/translatable-fields.md) form-wide, no rebuild required
    - File Manager with pluggable contexts and signed share links
    - API Clients & Personal Access Token management
    - System Health dashboard
    - API Routes explorer
    - Definitions (DB-backed enums used across forms and tables)
- **Developer Tooling**
    - DDD-style domain layer (Actions / DTOs / Queries / Events / Listeners)
    - FormBuilder, DatatableBuilder, TabBuilder fluent APIs (including [Translatable Fields](./docs/translatable-fields.md))
    - `@lvntr/components` Vue component library (FormBuilder/DatatableBuilder/TabBuilder, UI primitives, File Manager UI) — not published on npm; resolved via a Vite alias into the package's own `vendor/` copy, so no separate install step is needed
    - Domain scaffolding via `make:sk-domain` with opt-in flag support
    - Datatable bulk actions with cross-page selection
    - Safe upgrade flow via `sk:update` (hash-tracked, preserves your edits)
    - System health check via `sk:doctor`
    - Dedicated data-encryption key for sensitive settings & 2FA secrets, independent of `APP_KEY` — generate/rotate/verify with `encryption:key`, `encryption:rekey`, `encryption:health`
    - Light & Dark themes with instant-switch built-in `main` and `aura` kit themes (no rebuild)

## How to use it?

Start from a clean Laravel install:

```bash
composer create-project laravel/laravel my-app
cd my-app
composer require lvntr/laravel-starter-kit:^13.7
php artisan sk:install
```

> **Check `php -v` first — this kit requires PHP 8.4+.** The `laravel/laravel`
> skeleton itself only requires PHP 8.3, so `create-project` succeeds on 8.3 and
> the failure surfaces later. Always require the kit with `:^13.7` (not a looser
> `:^13.0`): with a loose constraint Composer silently resolves down to an
> ancient release that still fits PHP 8.3 instead of reporting the real blocker.

That's it. The installer sets up migrations, seeders, Passport keys, a default admin user, and builds the frontend. It also ejects the `User` and `Role` domain runtime classes into `app/Domain/` so they are immediately project-owned and ready to customise. Pass `--without-eject` to keep them vendor-resident instead.

Full step-by-step guide: [starter-kit.lvntr.dev/docs/install](https://starter-kit.lvntr.dev/docs/install)

## Requirements

- PHP 8.4+ (hard floor — `spatie/laravel-activitylog:^5.0` requires it too)
- Laravel 13
- Node.js 20.19+ (or 22.12+) — Vite 7 engine floor
- MySQL or MariaDB

## Compatibility & Versioning

The package version major aligns with the supported Laravel major. Each
Laravel major gets its own maintenance branch and `vN.x.y` tag stream;
existing consumer constraints stay locked to their major and never
receive breaking changes from a newer Laravel target.

| Laravel | Constraint                                            | Branch  | Status      |
|---------|-------------------------------------------------------|---------|-------------|
| 13.x    | `composer require lvntr/laravel-starter-kit:^13.7`    | `13.x`  | active      |

`main` tracks the currently active major (today: `13.x`). When a future
Laravel release is targeted, `main` will move to that next-major dev
stream and the previous major's `N.x` branch will continue to receive
backports.

The **git tag is the single source of version truth** — neither
`composer.json` nor the root `package.json` carries a `version` field, so
there is nothing to keep in sync. Releases are cut with `release.sh` (from
`main`), which tags the release and pushes only that tag.

## Documentation

Everything — installation, update flow, domain scaffolding, FormBuilder / DatatableBuilder / TabBuilder APIs, composables, file manager, roles & permissions, OAuth2 API, [AI-oriented API metadata](./docs/api-ai-metadata.md), activity logs, settings — lives on the official site:

**[starter-kit.lvntr.dev](https://starter-kit.lvntr.dev/)**

## License

[MIT](./LICENSE)
