# Roles And Permissions

The permission system is built on `spatie/laravel-permission` and driven by `config/permission-resources.php`.

## Core Idea

Permissions are generated from resource names and abilities defined in config. Three sources contribute:

- `resources` — standard CRUD-style resources
- `sub_resources` — scoped variants under a parent resource
- `custom_permissions` — one-off entries that don't fit the resource pattern

Examples from the current project:

- `users.read`
- `users.update`
- `roles.update`
- `settings.update`
- `files.read`
- `files.create`
- `files.update`
- `files.delete`
- `activity-logs.read`
- `pulse.read`
- `api-docs.read`

Sub-resources are also supported:

- `users:student.read`
- `users:guardian.update`

## Main Config Sections

- `resources` — define resource names and which CRUD abilities to generate
- `sub_resources` — nested variants with their own permission strings
- `custom_permissions` — arbitrary permission entries outside the resource model
- `permission_groups` — group permissions together for the Roles admin UI
- `role_groups` — group roles together in the UI
- `role_permissions` — seed which roles get which permissions by default
- `display_names` — human-readable labels for the Roles admin UI

## Default Roles

- `system_admin`
- `admin`
- `user`

`system_admin` is intended to bypass the normal permission restrictions.

Default role permissions are also defined in `config/permission-resources.php`.

### FileManager abilities

FileManager's built-in `global` context checks four independent abilities; holding one does not imply any other:

| Permission | Allows |
| --- | --- |
| `files.read` | Browse trees, list favorites/trash, and download files |
| `files.create` | Upload files, create folders, and copy files |
| `files.update` | Rename/move items, change favorites, restore trash, and pass the context check for share/revoke |
| `files.delete` | Delete items, empty trash, and permanently delete trashed items |

After changing role assignments, run `php artisan sk:seed-permissions` so the seeded permission data matches the four-ability contract.

## Role Hierarchy In User Management

User management is hierarchy-aware on both the admin panel and the API:

- `RoleSelectOptionsQuery` only returns roles the current actor is allowed to assign
- `system_admin` can assign any role
- non-`system_admin` users only see roles at their own rank or lower
- users who hold direct permissions but no role are treated as the lowest rank and cannot assign roles through the admin user flow
- `UpdateUserRequest::authorize()` blocks lower-ranked users from editing higher-ranked targets even if they hold `users.update`
- `UserDatatableQuery` (used by both the admin user listing and `GET /api/v1/users`) hides users whose minimum role `sort_order` is lower than the actor's — so a non-`system_admin` API consumer with `users.read` cannot enumerate higher-rank users
- `Admin/RoleController::data` (the JSON sibling of `edit`) and `Admin/RoleController::edit` and `destroy` all run the same `CanManageRoleQuery` check, so a lower-rank admin cannot read higher-rank role JSON via the prefetch endpoint either

## Regenerating Permissions

`database/seeders/_01_RolePermissionSeeder.php` is responsible for:

- creating configured permissions
- creating sub-resource permissions
- creating custom permissions
- removing orphaned permissions that no longer exist in config
- seeding and updating default roles

After editing permission config, rebuild the seeded data:

```bash
php artisan sk:seed-permissions --fresh
```

The admin panel also exposes a permission sync action for `system_admin` users.

### Keeping the matrix in step with package updates

`sk:update` never writes to `config/permission-resources.php` — the file is yours, and an updater that merged into it would eventually overwrite a project's own authorization model. The consequence is that a resource or ability the kit adds in a later release does not reach an existing installation by itself, and the first sign of that is usually a 403 on a screen that used to work.

After an update, ask:

```bash
php artisan sk:doctor --only=permission-matrix
```

The check lists every resource and ability the package ships that your config does not declare (resources you added yourself are never reported). Add the listed entries by hand, then run `php artisan sk:seed-permissions`.

## Automatic Route-to-Permission Mapping

`Lvntr\StarterKit\Http\Middleware\CheckResourcePermission` (vendor: `vendor/lvntr/laravel-starter-kit/src/Http/Middleware/CheckResourcePermission.php`) converts route names into permission strings automatically.

Examples:

- `users.index -> users.read`
- `users.store -> users.create`
- `users.edit -> users.update`
- `users.destroy -> users.delete`

If an explicit permission is passed in middleware, that value is used directly.

## Sub-Resource Support

The middleware also supports sub-resource permissions through the `type` query parameter.

Example:

- route permission: `users.read`
- current URL: `/users?type=student`
- resolved permission: `users:student.read`

This only applies if the scoped permission exists in the database.

## Frontend Usage

### Composable

Use `@/composables/useCan` inside pages and components:

```ts
const { can, canAny, hasRole } = useCan();
```

### Vue Directives

The frontend permission plugin registers:

- `v-can`
- `v-role`

Examples:

```vue
<Button v-can="'users.create'" />
<Button v-can:any="['users.create', 'users.update']" />
<div v-role="'system_admin'">Only for system admins</div>
```

### FormBuilder Form-Level Permission

`SkForm` also accepts `.permission('users.update')` to put the whole form into read-only mode. If the user lacks the ability, every field is disabled and the submit button is hidden. See the [FormBuilder guide](./formbuilder.md#form-level-permission-guard) for details.

### DataTable Row Actions

Each row action and menu action on `SkDatatable` supports a `.visible(() => can('users.update'))` callback — the button is not rendered at all when the user is not authorized.

## Middleware Mapping

The project maps route intent to permission checks. A route name like `users.index` typically resolves to a `users.read` permission check. Routes protected with `check.permission` middleware benefit from this automatic resolution.

If the resolved permission does not exist in the database, the middleware's behavior depends on `app()->environment()` (fail-closed by default, since v13.6.9):

- `local`: allow the request and log a warning, so day-to-day development is not blocked by a not-yet-seeded permission
- every other environment — `production`, `staging`, `uat`, `demo`, `testing`, etc.: deny the request with `403` so a forgotten permission row never silently exposes a route on a public host

**Opt-out:** set `config('starter-kit.permissions.allow_unmapped')` to `true` (env `STARTER_KIT_ALLOW_UNMAPPED_PERMISSIONS=true`) to restore the pre-v13.6.9 "allow on any non-production environment" behavior. `production` always denies regardless of this flag. See [UPGRADE.md](./UPGRADE.md) for the full migration note.

### Unmapped vs. Unresolved

The two failure modes above are easy to conflate but are governed by separate config keys:

- **`allow_unmapped`** — a permission WAS derived from the route name (`admin.users.index` → `users.read`), but no row with that name is seeded in the database. Covered above.
- **`allow_unresolved`** (config `starter-kit.permissions.allow_unresolved`, env `STARTER_KIT_ALLOW_UNRESOLVED_ROUTES`, default `true`) — NO permission could be derived at all: the route has no name, its name has fewer than two segments, or its action segment is not in the middleware's ability map. Historically this passed in total silence; with the default `true` it still passes, but the middleware logs a throttled warning naming the route so the gap is visible. Setting it to `false` denies the request instead. `php artisan sk:doctor --only=unresolved-routes` lists every route currently in this state.

**Production asymmetry, deliberate:** unlike `allow_unmapped`, which production always clamps to deny, `allow_unresolved` keeps applying in production once flipped to `false`. An unmapped permission is a per-host *data* gap (fixed by seeding the row); an unresolved route is a *structural* mismatch between the route table and the ability map, fixable only by renaming the route or shipping code — so the escape hatch has to remain available on the host where the flip could otherwise lock out a route.

`starter-kit.permissions.unrestricted_routes` lists route-name patterns (`Str::is` wildcards, e.g. `'api.v1.auth.*'`) that are deliberately permission-free: they pass with no warning and are never denied, regardless of `allow_unresolved`. It is consulted only on the unresolved axis — it can never exempt a route whose permission does resolve — and it is checked once per request rather than for every pattern, so keep entries tight (list endpoints, not whole trees) to avoid silently exempting routes added later.

**Who gets which default:** `sk:install` writes `STARTER_KIT_ALLOW_UNRESOLVED_ROUTES=false` into a **new** project's `.env`, so a fresh app is fail-closed from the first request. An app that does not set the key falls through to the package's own constant, which is `true` — and no release changes that on its own, because a published config predating the key lands on the same constant and flipping it would alter authorization on a plain `composer update`. An existing install opts in by writing the line itself. See [UPGRADE.md](./UPGRADE.md) for the ordered remediation path to run first.

### Octane / Long-Running Worker Deployments

`CheckResourcePermission` caches the seeded permission-name set with `Cache::remember()` under a short TTL (60 seconds) rather than for the whole request or worker lifetime. Both `php artisan sk:seed-permissions` and the Roles screen's permission sync (`RoleController::syncPermissions()` → `SyncPermissionsAction`) call `CheckResourcePermission::flushCache()` immediately after seeding, so a freshly seeded permission is honored at once instead of waiting out the TTL.

This makes the kit Octane-safe out of the box — no `RequestReceived` listener or manual cache-clearing workaround is required, on Octane (Swoole / RoadRunner) or standard PHP-FPM alike.

**Residual caveat:** if your cache store is per-process rather than centrally shared (for example the `array` driver), a worker other than the one that performed the seed/sync can still serve a stale permission-name list for up to the 60-second TTL — the short TTL exists specifically to bound that window.

## Login Status Check

API login (`POST /api/v1/auth/login`) validates not only the credentials but also the user's `status`. `LoginUserAction`:

1. Attempts `Auth::attempt()` with the supplied credentials.
2. On success, inspects `user.status`.
3. Any value other than `active` (e.g. `inactive`, `banned`) triggers `Auth::logout()` and the action returns `null`.

The controller responds with `401 Invalid email or password` in that case — so banned or inactive accounts cannot obtain a token even with a valid password.

## Menu Filtering

`useAdminMenu()` defines the project-specific admin navigation tree, and `useMenuBuilder()` filters visible items based on the current user's permissions and roles.

Query-aware active state handling also lives in `useMenuBuilder()`, so links like `/users?type=student` can highlight the correct menu entry.

## Practical Workflow for Adding a New Protected Area

1. Add the resource and abilities to `config/permission-resources.php`.
2. Run `php artisan sk:seed-permissions --fresh`.
3. Protect the routes with `check.permission`.
4. Use `useCan()` or `v-can` in the frontend where needed.

## Authorization Layers

The starter kit uses **three stacked layers**. They do not replace each other — pick the layer that matches the granularity you need.

| Layer | Where | Granularity | Example |
| --- | --- | --- | --- |
| 1. Route middleware | `check.permission` in route definitions | Per route, broad permission (`users.read`) | `Route::get('/admin/users', …)->middleware('check.permission')` |
| 2. Laravel Policy | `app/Policies/*Policy.php` | Per model instance, optional row-level rules | `$this->authorize('update', $role)` |
| 3. FileManager ContextRegistry | `Lvntr\StarterKit\Domain\FileManager\Support\ContextRegistry` (vendor) | Per pluggable FileManager context (owner model + custom rules) | Closure passed when registering a context |

### When to use which

- **Middleware only** is enough for flat admin CRUD where any user with the permission may act on any row.
- **Add a Policy** when you need row-level rules (self-ownership, state-based gating, tenant scoping). Policies are auto-discovered: `App\Models\Foo` → `App\Policies\FooPolicy`.
- **Register a FileManager context** when a domain wants to expose a files tab bound to one of its models (users, organisations, projects). The context's authorizer closure controls `read`, `create`, `update`, and `delete` access without duplicating logic in a controller.

### Policy pattern

Policy methods accept the authenticated `User` as the first argument and the target model as the second. Keep checks permission-first; add self/state logic only when required.

```php
namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('roles.read');
    }

    public function update(User $actor, Role $role): bool
    {
        // Add row-level rules here if needed, e.g. tenant scoping.
        return $actor->can('roles.update');
    }
}
```

The kit ships policies for `User`, `Role`, `Setting`, and `FileFolder`. They are additive — middleware-guarded routes keep working even if a controller never calls `authorize()`.

### FileManager ContextRegistry

`ContextRegistry` exposes a pluggable authorization hook: each file context (e.g. `user`, or a custom `project` context added by the host app) provides a closure that receives `read`, `create`, `update`, or `delete`. The kit never passes the deprecated `write` name. The default user-owned context delegates `read` to `UserPolicy@view` and every mutation to `UserPolicy@update`, so one policy drives both explicit `authorize()` calls and the files tab guard.

Keep context closures thin; delegate real rules to a Policy so logic stays in one place.
