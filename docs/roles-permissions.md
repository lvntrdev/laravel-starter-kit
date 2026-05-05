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

## Automatic Route-to-Permission Mapping

`app/Http/Middleware/CheckResourcePermission.php` converts route names into permission strings automatically.

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

If the resolved permission does not exist in the database, the middleware behaves differently by environment:

- production: deny the request with `403` so unseeded permissions do not silently expose routes
- non-production: allow the request and log a warning so developers can seed the missing permission

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
| 3. FileManager ContextRegistry | `app/Domain/FileManager/Support/ContextRegistry.php` | Per pluggable FileManager context (owner model + custom rules) | Closure passed when registering a context |

### When to use which

- **Middleware only** is enough for flat admin CRUD where any user with the permission may act on any row.
- **Add a Policy** when you need row-level rules (self-ownership, state-based gating, tenant scoping). Policies are auto-discovered: `App\Models\Foo` → `App\Policies\FooPolicy`.
- **Register a FileManager context** when a domain wants to expose a files tab bound to one of its models (users, organisations, projects). The context's authorizer closure controls read/write access without duplicating logic in a controller.

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

`ContextRegistry` exposes a pluggable authorization hook: each file context (e.g. `user`, or a custom `project` context added by the host app) provides a closure that decides whether the current user may `read` or `write` inside that context. The default user-owned context delegates to `UserPolicy@view` / `UserPolicy@update`, so one policy drives both explicit `authorize()` calls and the files tab guard.

Keep context closures thin; delegate real rules to a Policy so logic stays in one place.
