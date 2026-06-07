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

**Scaffolded into your app (`app/Domain/`)** — controllers, FormRequests, models, and routes for these are also generated into `app/`:

- `app/Domain/Auth`
- `app/Domain/User`
- `app/Domain/Role`
- `app/Domain/Setting`
- `app/Domain/ApiRoute`

**Vendor-resident (`src/Domain/`, `Lvntr\StarterKit\Domain\`)** — their runtime layer (Actions, DTOs, Queries, Events, Listeners, Services) runs from the package and is not copied into your app on a fresh install. `App\Domain\<Module>\...` imports stay working through `class_alias`; a local `app/Domain/<Module>/` copy, if present, takes precedence:

- `Session`
- `Media`
- `ActivityLog`
- `FileManager`
- `Logs`
- `Shared`

See [ddd.md](./ddd.md) for the full vendor-resident model and reconcile steps.

### Typical Request Flow

1. Route resolves to a thin controller.
2. Validation is handled by a Form Request when needed.
3. Payload is mapped into a DTO where the feature uses DTOs.
4. Business logic lives in Action classes — under `app/Domain/.../Actions` for scaffolded domains, or `src/Domain/.../Actions` (vendor namespace) for vendor-resident ones.
5. Query classes prepare listing and filter data when needed.
6. Responses are returned through Inertia or `to_api()`.

### Domain Events

`app/Providers/DomainServiceProvider.php` registers event-listener pairs such as:

- `UserCreated -> LogUserCreated`
- `UserUpdated -> LogUserUpdated`
- `UserDeleted -> LogUserDeleted`
- `RoleCreated -> LogRoleCreated`
- `RoleUpdated -> LogRoleUpdated`
- `RoleDeleted -> LogRoleDeleted`
- `LogFilesDeleted -> LogActivityForLogFilesDeleted`

This keeps side effects outside the main action classes.

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
- `SkDatatable` renders definition tags through `SkTag`, so DB-driven metadata can be combined with column-level `colors()`, `icons()`, and tag style helpers

### Flash Messages

Controllers redirect with flash messages, and `AdminLayout.vue` turns them into PrimeVue toasts.

### Local Composables

Project-specific composables live under `resources/js/composables/`. The admin sidebar keeps menu definitions in `useAdminMenu()` and shares filtering / active-state logic through `useMenuBuilder()`.

## Suggested Reading

- [project-info.md](./project-info.md)
- [install.md](./install.md)
- [ddd.md](./ddd.md)
- [roles-permissions.md](./roles-permissions.md)
- [api.md](./api.md)
- [datatable.md](./datatable.md)
- [formbuilder.md](./formbuilder.md)
- [api-routes.md](./api-routes.md)
- [files.md](./files.md)
- [logs.md](./logs.md)
- [i18n.md](./i18n.md)
- [composables.md](./composables.md)
