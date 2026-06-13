# Project Documentation

This document gives the high-level map of the starter kit after installation. It is a Laravel 13 admin-first starter application with Inertia.js v3, Vue 3.5, Passport API authentication, Fortify web auth flows, and a package-backed UI toolkit.

## Backend Areas

- `app/Domain/` for the business logic that is scaffolded into your app; the runtime layer of vendor-managed domains lives in the package under `src/Domain/` (`Lvntr\StarterKit\Domain\`)
- `app/Http/Controllers/` for web and API entry points
- `app/Http/Responses/` for API response shaping
- `app/Models/` for Eloquent models
- `app/Providers/` for app, domain, settings, and Fortify bootstrapping
- `routes/web*` and `routes/api*` for modular route loading

### Main Domain Modules

**Surface ownership is split per module** — Models always stay app-owned; the rest of the surface depends on the module. The domain runtime and HTTP/Vue surface split by module:

- `Auth` is fully app-side (`app/Domain/Auth`)
- `User` and `Role` are scaffolded into the app (controllers, FormRequests, Vue) but keep only the app-owned `BulkActions` slice under `app/Domain/...`; the core domain runtime is vendor-resident
- `Setting`, `ApiRoute`, `Logs`, `ActivityLog`, and `Files` are **vendor-first**: their controllers, FormRequests, and Vue pages all run from the package (only their Models stay in `app/`). Run `sk:eject <Module>` to pull them into the app — see the Module Ownership table in the [README](../README.md)

**Vendor-resident runtime domains (`src/Domain/`, `Lvntr\StarterKit\Domain\`)** — Actions, DTOs, Queries, Events, Listeners, and Services for these modules run from the package and are not copied into your app on a fresh install. `App\Domain\<Module>\...` imports stay working through `class_alias`; a local `app/Domain/<Module>/` copy from an eject or older install takes precedence:

- `ActivityLog`
- `ApiClient`
- `ApiRoute`
- `FileManager`
- `Logs`
- `Media`
- `Role`
- `Session`
- `Setting`
- `Shared`
- `User`

See [ddd.md](./ddd.md) for the full vendor-resident model and reconcile steps.

### Typical Request Flow

1. Route resolves to a thin controller.
2. Validation is handled by a Form Request when needed.
3. Payload is mapped into a DTO where the feature uses DTOs.
4. Business logic lives in Action classes — under `app/Domain/.../Actions` for scaffolded domains, or `src/Domain/.../Actions` (vendor namespace) for vendor-resident ones.
5. Query classes prepare listing and filter data when needed.
6. Responses are returned through Inertia or `to_api()`.

### Domain Events

Kit-provided audit event/listener pairs for vendor-resident `User`, `Role`, and `Logs` runtime are registered in `StarterKitServiceProvider::registerEventListeners()` with vendor FQCNs:

- `UserCreated -> LogUserCreated`
- `UserUpdated -> LogUserUpdated`
- `UserDeleted -> LogUserDeleted`
- `RoleCreated -> LogRoleCreated`
- `RoleUpdated -> LogRoleUpdated`
- `RoleDeleted -> LogRoleDeleted`
- `LogFilesDeleted -> LogActivityForLogFilesDeleted`

The scaffolded `app/Providers/DomainServiceProvider.php` is left for your own application events. `sk:eject` can add bindings there when you copy a kit domain back into `app/Domain/`.

## Frontend Areas

- `resources/js/pages/` for Inertia pages
- `resources/js/layouts/` for shared layouts
- `resources/js/components/Lvntr-Starter-Kit/` for reusable starter-kit components
- `resources/js/composables/` for client-side behaviors
- `resources/js/routes/` and `resources/js/actions/` for Wayfinder-generated helpers

### Inertia Pages

Pages live under `resources/js/pages/`. Examples:

- `resources/js/pages/Admin/Users`
- `resources/js/pages/Admin/Roles`
- `resources/js/pages/Admin/Settings`
- `resources/js/pages/Admin/ApiRoutes`
- `resources/js/pages/Admin/Files`
- `resources/js/pages/Admin/Logs`
- `resources/js/pages/Profile`

### Reusable UI Toolkit

The admin panel uses shared UI building blocks exposed through the `@lvntr/*` alias. Examples:

- `@lvntr/components/DatatableBuilder/core`
- `@lvntr/components/FormBuilder/core`
- `@lvntr/components/TabBuilder/core`
- `@lvntr/components/ui/AppDialog.vue`

## Request Patterns

- browser pages use Inertia
- JSON endpoints use `to_api()` and `ApiResponse`
- list-heavy admin screens use datatable query classes
- settings and other writes should flow through Form Requests and Actions

### Authentication Runtime

- Fortify renders the browser auth screens through Inertia pages under `resources/js/pages/Auth`
- the login pipeline includes rate limiting, Turnstile validation, inactive-user blocking, and optional two-factor redirection
- Passport handles `/api/v1/auth/*` personal-access-token flows for API consumers

## Routing Strategy

Route files are split by feature. `routes/web.php` loads files from `routes/web/`, and `routes/api.php` loads files from `routes/api/`.

- public routes are loaded first
- authenticated routes are grouped under `auth` and `verified`
- permission-protected route files are wrapped with `check.permission`
- API routes are grouped under `/api/v1` with throttle and `auth:api` rules

### Service Routes for the Frontend

`routes/web/service-route.php` is loaded inside the authenticated web group:

- `GET /definitions` powers `useDefinition()` and builder-driven option loading
- `GET /roles/options` provides select options for admin forms and filters

### Public Helper Routes

`routes/web/public-route.php` holds lightweight public helper routes:

- `POST /locale` updates the active interface language in session

### Feature-Specific Admin Routes

Some admin screens are isolated into dedicated route files:

- `routes/web/developer-route.php` loads the `api-routes.*` screen
- `routes/web/files-route.php` opens the global file manager as `files.index`
- `routes/web/log-route.php` exposes the system-admin log viewer at `logs.*`
- `routes/web/profile-route.php` contains profile, avatar, and browser-session endpoints

## Shared Building Blocks

- helpers from `app/Helpers/sk-helpers.php` and `app/Helpers/custom.php`
- `ApiException` and `ApiExceptionHandler`
- permission middleware (`check.permission`)
- security headers middleware
- definitions system

### Global Overlays in AdminLayout

`AdminLayout.vue` renders the shared overlays once:

- `ConfirmDialogComponent`
- `ToastComponent`
- `AppDialog`
- `ImageLightbox`

### Definitions

The current UI favors database-backed definitions over a separate enum-sharing layer.

- `_02_DefinitionSeeder.php` seeds keys such as `userStatus`, `gender`, `identityType`, and `yesNo`
- `DefinitionService` (vendor-resident `Lvntr\StarterKit\Domain\Shared\Services\`, reachable via the `App\Domain\Shared\Services\DefinitionService` alias) groups and caches definition items per locale
- `useDefinition()` reads them from `GET /definitions`
- definition records carry label, severity, and optional icon metadata
- `SkDatatable` and `SkForm` can bind directly to definition keys such as `.tag('definition').tagKey('userStatus')` and `.definitionOptions('gender')`
- `SkDatatable` renders definition tags through PrimeVue's `<Tag>`, so DB-driven metadata can be combined with column-level `colors()`, `icons()`, and tag style helpers

### Flash Messages

Controllers redirect with flash messages, and `AdminLayout.vue` turns them into PrimeVue toasts.

### Local Composables

Project-specific composables live under `resources/js/composables/`. The admin sidebar keeps menu definitions in `useAdminMenu()` and shares filtering / active-state logic through `useMenuBuilder()`.

## Suggested Reading

**Getting started**

- [welcome.md](./welcome.md) — what the kit is and what ships inside it
- [project-info.md](./project-info.md) — stack and high-level project overview
- [install.md](./install.md) — installation flow
- [update.md](./update.md) — pulling updated stubs (hash-tracked)
- [UPGRADE.md](./UPGRADE.md) — version upgrade notes

**Backend & DDD**

- [ddd.md](./ddd.md) — domain layout and the vendor-resident model
- [auth.md](./auth.md) — Fortify (web) + Passport (API) authentication
- [roles-permissions.md](./roles-permissions.md) — permission resources and seeding
- [api.md](./api.md) — API response envelope and conventions
- [api-clients.md](./api-clients.md) — Passport clients & token management
- [api-routes.md](./api-routes.md) — API route inventory screen
- [module-routes.md](./module-routes.md) — modular route registry
- [definitions.md](./definitions.md) — shared label/value lookups
- [settings.md](./settings.md) — application settings module
- [activity-logs.md](./activity-logs.md) — audit/activity logging
- [logs.md](./logs.md) — application log viewer

**Frontend & UI builders**

- [formbuilder.md](./formbuilder.md) — FormBuilder (FB)
- [datatable.md](./datatable.md) — DatatableBuilder (DB)
- [tabs.md](./tabs.md) — TabBuilder (TB)
- [composables.md](./composables.md) — Vue composables
- [admin-components.md](./admin-components.md) — admin page style guide
- [ui-components.md](./ui-components.md) — reusable UI primitives
- [theme.md](./theme.md) — theme system
- [wayfinder.md](./wayfinder.md) — type-safe route helpers

**Features**

- [file-manager.md](./file-manager.md) — file manager
- [files.md](./files.md) — file uploads
- [i18n.md](./i18n.md) — internationalization
- [translatable-fields.md](./translatable-fields.md) — multi-language model fields

**Tooling**

- [artisan-commands.md](./artisan-commands.md) — `sk:*` command reference
- [claude-skills.md](./claude-skills.md) — shipped Claude Code skills
