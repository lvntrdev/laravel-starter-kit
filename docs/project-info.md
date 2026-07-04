# Project Info

> **Active Development Notice**
>
> This repository is under active development and is subject to frequent changes. The stability of the project is not yet guaranteed. Please consider the following points before use:
>
> 1. **Code Changes:** The directory structure or core classes may undergo radical changes without prior notice.
> 2. **Update Process:** Updates may not always provide an automated migration path. In addition to running update commands, you may need to perform manual interventions by checking the README or CHANGELOG files.
> 3. **Risk:** Significant changes may lead to data loss or breaking issues in your existing project.

This starter kit is an admin-first Laravel 13 package that gives a new project a production-ready foundation instead of a blank panel.

## What It Includes

- Laravel 13 backend with PHP 8.4+
- Inertia.js v3, Vue 3.5, TypeScript 5.9, Vite 7, and SSR-ready app wiring through `@inertiajs/vite`
- Tailwind CSS 4 and PrimeVue 4 UI stack
- Fortify-powered web authentication with profile security, optional 2FA, and browser session controls
- Cloudflare Turnstile support for login, register, and forgot-password flows
- Passport-based API authentication with personal access tokens
- Role and permission management
- Settings panel with general, auth, mail, storage, file manager, API integrations, API clients, API tokens, and System Health sections
- Activity logs, definitions system, ApiRoutes admin module, and a global files workspace
- Reusable builder components such as DataTable, FormBuilder, and Tabs
- Domain-first application structure for scaling business logic cleanly

## Minimum Requirements

- PHP `8.4+`
- Composer
- Node.js `20.19+`
- npm
- MySQL or MariaDB
- A fresh Laravel 13 project or a project aligned with this starter kit structure

## When To Use It

Use this package when you want to start from a ready admin platform instead of assembling authentication, permissions, settings, media handling, and panel infrastructure by hand.

## SSR

SSR support ships with the application and is wired through the same Inertia/Vite entrypoint. Runtime enablement is controlled by the `INERTIA_SSR_ENABLED` env var (the kit defaults it to `false` from vendor since v13.5.12, so `config/inertia.php` no longer needs to be published — publish it only to customize Inertia further); when it is off, the app falls back cleanly to client rendering without changing the page code.

## ApiRoutes Admin Module

The ApiRoutes module lets admins inspect and regenerate the Passport API route documentation from within the panel.

- Frontend pages: `resources/js/pages/Admin/ApiRoutes/`
- Backend domain: `app/Domain/ApiRoute/`
- Web routes: `routes/web/developer-route.php`, named `api-routes.*`

See [api-routes.md](./api-routes.md) for details.

## Global Files Module

The Global Files module gives the admin panel a full-page workspace for system-wide file management. It mounts the FileManager component with the `global` context and provides a central media area for operational use.

See [files.md](./files.md) for details.

## Locale & Translations

The starter kit combines Laravel `lang/` files with `laravel-vue-i18n`. The selected interface language is stored in session, while the set of active languages is controlled from the settings panel.

See [i18n.md](./i18n.md) for details.

## Recommended Reading Order

1. [install.md](./install.md)
2. [update.md](./update.md)
3. [ddd.md](./ddd.md)
4. [project-documentation.md](./project-documentation.md)
