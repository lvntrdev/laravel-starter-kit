# Installation

> **Active Development Notice**
>
> This repository is under active development and is subject to frequent changes. The stability of the project is not yet guaranteed. Please consider the following points before use:
>
> 1. **Code Changes:** The directory structure or core classes may undergo radical changes without prior notice.
> 2. **Update Process:** Updates may not always provide an automated migration path. In addition to running update commands, you may need to perform manual interventions by checking the README or CHANGELOG files.
> 3. **Risk:** Significant changes may lead to data loss or breaking issues in your existing project.

This guide explains the recommended installation flow for a fresh project.

> **Start from a bare Laravel install.** Do **not** run `php artisan install:inertia`, `install:api`, Breeze, Jetstream, or any other starter preset before installing this package. Presets scaffold controllers, routes, pages, and layouts that this starter kit also ships — the installer cannot detect them, so they remain as orphaned dead code next to the kit's own files.
>
> Recommended flow:
>
> ```bash
> composer create-project laravel/laravel my-app
> cd my-app
> composer require lvntr/laravel-starter-kit:^13.0
> php artisan sk:install
> ```

## Requirements

| Requirement | Version         |
| ----------- | --------------- |
| PHP         | 8.4+            |
| Laravel     | 13              |
| Node.js     | 18+             |
| Database    | MySQL / MariaDB |

## 1. Prepare The Project

Make sure the project has a working database connection and a valid `.env`. Set the basics before starting:

```env
APP_NAME="My Application"
APP_URL=https://my-app.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_app
DB_USERNAME=root
DB_PASSWORD=
```

### Environment Variables Of Note

The installer writes a starter `.env.example` that carries a few keys new installs should review:

```env
# Log level — 'debug' is fine for local dev; production should ship 'error' or 'warning'.
LOG_LEVEL=error

# Cloudflare Turnstile (bot / captcha). When TURNSTILE_ENABLED=false the
# `turnstile` middleware is a no-op, so leaving the keys empty locally is safe.
TURNSTILE_ENABLED=false
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=

# Session hardening — both default to 'true'. Keep these on in production.
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

# Passport OAuth2 keys — the recommended production pattern is to load these
# via env instead of committing the key files at storage/oauth-*.key.
# Run `php artisan passport:keys` once, move the generated strings into these
# env vars, then delete the files.
# PASSPORT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"
# PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
```

## 2. Require The Package

```bash
composer require lvntr/laravel-starter-kit:^13.0
```

## 3. Run The Installer

```bash
php artisan sk:install
```

The installer walks through each step interactively:

| Step | What it does                                                                                     |
| ---- | ------------------------------------------------------------------------------------------------ |
| 1    | Configure database connection (driver, host, port, database, credentials)                        |
| 2    | Publish application scaffolding (Controllers, Models, Routes, Vue pages, Enums, Providers, etc.) |
| 3    | Merge `package.json` dependencies                                                                |
| 4    | Remove conflicting default Laravel files (`vite.config.js`, `welcome.blade.php`, etc.)           |
| 5    | Publish and inject config files (`app.php`, `filesystems.php`, `media-library.php`)              |
| 6    | Configure application settings, filesystem disks, media library, and `bootstrap/app.php`         |
| 7    | Register service providers                                                                       |
| 8    | Regenerate Composer autoload                                                                     |
| 9    | Run database migrations                                                                          |
| 10   | Run seeders (Roles, Permissions, Definitions, Settings)                                          |
| 11   | Generate Passport encryption keys                                                                |
| 12   | Create default admin user (`admin@demo.com` / `password`)                                        |
| 13   | Install npm dependencies and build frontend assets                                               |

### Useful Flags

```bash
php artisan sk:install --force
php artisan sk:install --no-interaction
```

- `--force` overwrites existing publishable files
- `--no-interaction` is useful for CI or scripted installs; accepts all defaults automatically

## 4. Build Frontend Assets

If you skipped the asset step during installation, run:

```bash
npm install
npm run build
```

For local development:

```bash
composer dev
```

## 5. Verify The Installation

After installation, confirm these areas work:

- web login page (log in with `admin@demo.com` / `password`)
- register and forgot-password pages, including Turnstile when enabled
- dashboard access
- user and role management pages
- profile security page (password, 2FA, browser sessions, avatar)
- settings page tabs: General, Auth, Mail, Storage, File Manager, Turnstile
- file manager
- `/api/v1/auth/login` and `/api/v1/auth/me`

## 6. Optional Publishing

The package keeps many assets inside the package by default. Publish them only when you need project-level customization:

```bash
php artisan sk:publish
php artisan sk:publish --tag=components
php artisan sk:publish --tag=lang
php artisan sk:publish --tag=config
```

## Resetting the Database (site:install)

For development, the `site:install` command drops all tables and reinstalls from scratch:

```bash
php artisan site:install
```

This command:

1. Shows target database and environment details for confirmation
2. Runs `migrate:fresh` (drops all tables and re-runs migrations)
3. Runs all seeders (files prefixed with `_` in `database/seeders/`)
4. Generates Passport keys
5. Creates the default admin user

**Safety guards:**

- Only runs in `local` and `setup` environments
- Permanently blocked in any environment containing `prod` or `production`
- Requires explicit confirmation before proceeding

> **Note:** `site:install` is published as a stub file. If you customize it (e.g., add custom seeders or change admin defaults), the `sk:update` command will detect your changes and skip the file during updates.

## Updating The Package

When a new version is released:

```bash
# 1. Update the Composer package
composer update lvntr/laravel-starter-kit

# 2. Sync application files
php artisan sk:update
```

The update command uses a hash-based tracking system to safely merge package updates with your customizations:

| File category                                                                        | Behavior                                                            |
| ------------------------------------------------------------------------------------ | ------------------------------------------------------------------- |
| **Core files** (`Domain/Shared/`, Traits, Middleware, helpers, `ApiResponse`)        | Always updated to latest version                                    |
| **User-modifiable files** (Controllers, Models, Pages, Routes, `SiteInstallCommand`) | Updated only if you haven't modified them since last install/update |
| **Never-update files** (`config/permission-resources.php`)                           | Installed once, never touched again                                 |
| **Your custom domains**                                                              | Never touched                                                       |
| **New files from package**                                                           | Automatically added                                                 |
| **Deprecated files**                                                                 | Automatically removed                                               |

```bash
# Preview what would change without modifying anything
php artisan sk:update --dry-run

# Force update everything (overwrites your customizations)
php artisan sk:update --force
```

## Upgrading From Laravel 12

If you have an existing Starter Kit project on Laravel 12:

```bash
# 1. Update composer.json to require Laravel 13
composer require laravel/framework:^13.0 lvntr/laravel-starter-kit:^13.0 -W

# 2. Run the upgrade wizard
php artisan sk:upgrade
```

The upgrade command verifies Laravel 13+, Starter Kit v13+, PHP 8.4+; syncs stubs; clears caches; runs new migrations (optional); re-seeds roles and permissions (optional); and rebuilds frontend assets.

```bash
php artisan sk:upgrade --force       # skip confirmation prompts
php artisan sk:upgrade --skip-build  # skip npm install / npm run build
```

## All Available Commands

| Command            | Description                                    |
| ------------------ | ---------------------------------------------- |
| `sk:install`       | Full installation wizard                       |
| `sk:update`        | Update package files preserving user changes   |
| `sk:upgrade`       | Upgrade from previous Laravel version          |
| `sk:publish`       | Publish optional assets for customization      |
| `site:install`     | Reset database and reinstall with default data |
| `make:sk-domain`   | Scaffold a complete DDD domain interactively   |
| `remove:sk-domain` | Remove a domain and all its files              |
| `env:sync`         | Sync `.env` keys to `.env.example`             |

## Troubleshooting

**Vite manifest error after install:**

```bash
npm run build
# or start dev server
npm run dev
```

**Frontend changes not reflected:**

```bash
npm run dev
# or rebuild
npm run build
```

**Missing classes after install:**

```bash
composer dump-autoload
```

**Passport keys missing:**

```bash
php artisan passport:keys --force
```

**`php artisan tinker` not found after deploy:**

`laravel/tinker` ships in `require-dev` — production builds that run `composer install --no-dev` will not have it. This is deliberate. If you need tinker on the server, install it explicitly with `composer require laravel/tinker` (outside `require-dev`).

Related docs:

- [update.md](./update.md)
- [artisan-commands.md](./artisan-commands.md)
- [ui-components.md](./ui-components.md)
