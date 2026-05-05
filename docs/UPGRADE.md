# UPGRADE — Lvntr Starter Kit

This file is the cross-major-version migration guide. Every release gets its own section, newest at the top. Small bug fixes live only in `CHANGELOG.md` — this document covers only changes that touch **published** files (i.e. files copied into your app by `sk:install`), because those changes cannot be delivered by `composer update` alone.

---

## v13.4.x → v13.5.0

### Summary

In this release the package runtime was moved to vendor. Your existing files in `app/` **are not affected** and continue to work as-is. `composer update` is the only required step.

### Upgrade steps

```bash
composer update lvntr/laravel-starter-kit
php artisan migrate
```

`php artisan migrate` returns "Nothing to migrate" because your existing migration history exactly matches this release's vendor migration files.

#### Optional steps

```bash
# Regenerate Wayfinder typed route files (no diff expected)
php artisan wayfinder:generate

# Check for stub updates (reports if a hash changed; never forces)
php artisan sk:update --dry-run
```

### What does not change

| Area | Status |
|------|--------|
| `app/Domain/FileManager/` files | Preserved, not deleted |
| `app/Domain/Shared/` files | Preserved, not deleted |
| `app/Traits/HasActivityLogging.php` | Preserved |
| `app/Traits/HasMediaCollections.php` | Preserved |
| `app/Helpers/sk-helpers.php` | Preserved; your functions take precedence |
| `app/Http/Responses/ApiResponse.php` | Preserved |
| `app/Http/Middleware/CheckResourcePermission.php` | Preserved |
| Route names (`file-manager.*`) | All 19 route names unchanged |
| Migration history | "Nothing to migrate" |
| Config keys (`starter-kit.*`, `file-manager.*`) | Existing keys preserved |
| Frontend `@lvntr` alias | Untouched |
| Permission keys (`files.read`, `files.update`, etc.) | Unchanged |
| API response envelope (`success`, `status`, `message`, `data`) | Unchanged |

### Optional cleanup

#### Backend files (vendor migration)

Files such as `app/Domain/FileManager/` and `app/Domain/Shared/` now also run from vendor. If you want to remove them from your app and rely on the vendor version, see the step-by-step guide at:

`docs_project/migrate-existing-project-to-vendor.tr.md` (in the application worktree)

This step is entirely optional and does not need to happen right away.

#### Frontend (switch to vendor symlink)

If `resources/js/components/Lvntr-Starter-Kit/` is still in your app and you have no custom modifications, you can switch to the vendor frontend:

1. **Vite alias** — point the `@lvntr/components` alias at the vendor path in `vite.config.ts`:

   ```ts
   '@lvntr/components': path.resolve(__dirname, 'vendor/lvntr/laravel-starter-kit/resources/js/components/Lvntr-Starter-Kit'),
   ```

   Add the vendor path to the `Components({ dirs })` plugin array:

   ```ts
   dirs: [
     'resources/js/components',
     'vendor/lvntr/laravel-starter-kit/resources/js/components/Lvntr-Starter-Kit',
   ],
   ```

   Make sure `preserveSymlinks: true` is set.

2. **Delete the app copy**:

   ```bash
   rm -rf resources/js/components/Lvntr-Starter-Kit
   ```

3. **Build smoke test**:

   ```bash
   npm run build
   ```

   Should exit 0.

If you have customised components, do not delete them — keep your app-specific components under `resources/js/components/<X>` while importing from the vendor lib.

#### sk:sync deprecation

`php artisan sk:sync` is deprecated. It was never needed for the composer path-repository (symlink) workflow. The `--force` flag preserves the old behaviour but is not recommended.

### sk:update output

From this release onwards, `sk:update` no longer copies runtime files that have moved to vendor. The output will include an informational message similar to:

```
[INFO] v13.5.0+: The following files now run from vendor.
       Deleting them is optional:
         app/Domain/FileManager/
         app/Domain/Shared/{Actions,Contracts,DTOs,Pipelines}
         app/Traits/HasActivityLogging.php
         app/Traits/HasMediaCollections.php
         app/Helpers/sk-helpers.php
         app/Http/Responses/ApiResponse.php
         app/Http/Middleware/CheckResourcePermission.php
         app/Http/Middleware/SecurityHeaders.php
         app/Exceptions/ApiException.php
         app/Exceptions/ApiExceptionHandler.php
         app/Http/Controllers/FileManagerController.php
         app/Http/Requests/FileManager/
         app/Console/Commands/PurgeFileManagerTrash.php
```

Hash-tracked stubs (auth / layout / user / role / settings / config) retain the existing diff / notification behaviour.

### New install (v13.5.0+)

On a fresh project, `php artisan sk:install` no longer copies `app/Domain/FileManager/`, `app/Domain/Shared/`, `app/Traits/`, `app/Helpers/sk-helpers.php`, `app/Http/Responses/ApiResponse.php`, or `app/Http/Middleware/CheckResourcePermission.php` into `app/`. These modules run directly from `vendor/lvntr/laravel-starter-kit/src/`.

Files published to the application: auth / layout Vue components, User / Role / Setting domain scaffold, config files, single-line route stubs.

---

## v13.4.8 → v13.4.9

See [CHANGELOG.md](../CHANGELOG.md#13490---2026-05-02).

Quick upgrade:

```bash
composer update lvntr/laravel-starter-kit
php artisan sk:update
php artisan migrate
npm install
npm run build
```

---

## v13.4.x → v13.4.10

See [CHANGELOG.md](../CHANGELOG.md#134100---2026-05-04).

```bash
composer update lvntr/laravel-starter-kit
php artisan sk:update
php artisan migrate
npm install
npm run build
```

---

## 13.4.0 → 13.4.1 — API response hardening + Postman/Apidog sync + OAuth UUID fix

> **Summary:** This patch bundles the end-to-end API response envelope rework (trace-id pipeline, centralised exception handler, leak-closing controller patches) with two new API client integrations (Postman + Apidog sync) and a pair of install-time fixes (OAuth migrations made UUID-compatible, `site:install` now provisions the Passport personal access client automatically). Most changes are **additive** (new body fields / headers, new admin buttons), but **three behavioural API-response breaks** matter — they can affect UI toast copy and strict client schemas: `abort()` raw-message whitelist + `ModelNotFoundException` message format + `Api/AuthController` raw User → `UserResource`.

### 0. Who is affected?

| Audience | Action |
| --- | --- |
| Fresh installs (`composer create-project` + `sk:install`) | Nothing — stubs already carry 13.4.1. |
| Teams running `sk:update` regularly | `composer update` + `php artisan sk:update`. `ApiResponse`, `ApiExceptionHandler`, `AssignTraceId`, `sk-helpers.php` are carried automatically; **controllers are manual** (Step 4). |
| Projects with customised controllers | Apply the Step 4 patches by hand — especially the `catch (LogicException $e) → throw ApiException::...` pattern flip. |
| Package `src/`-only consumers (never published) | `composer update lvntr/laravel-starter-kit` is enough; `Bootstrap` registers the middleware for you. |
| Anyone with their own `app/Http/Middleware/AssignTraceId.php` | Class name collision — either accept the package stub or rename your class. |

### 1. Pre-upgrade checklist

1. **Branch + backup:** `git checkout -b upgrade/v13.4.1 && git push`
2. **Notify frontend/mobile:** additive fields (body `trace_id`, header `X-Request-ID`, echoed `X-Correlation-ID`, `Retry-After` on 429) are being introduced; strict-schema clients should register them.
3. **QA:** if your UI surfaces error messages as toasts, run a short QA pass for the **behavioural breaks in Step 2** (abort() messages, model-not-found format, auth me/login payload).
4. **Sanity check:** `composer test` + `npm run build` green on the current version?

### 2. Behavioural breaking changes

Status codes unchanged; envelope field list unchanged; only `message` text and the `data.user` shape under auth endpoints may change.

#### 2.1 `abort($code, 'custom message')` no longer leaks the message

```diff
- // Before: body.message = "SQL error: table users missing col xyz"
- abort(400, 'SQL error: table users missing col xyz');
+ // Now: body.message = "Bad request."  (the internal detail is dropped)
+ abort(400, 'SQL error: ...');   // That message never reaches the client.
```

**Why:** the `HttpExceptionInterface` branch now uses the fixed `defaultMessageForStatus()` table instead of `$e->getMessage()` (K3). Internal messages land in `debug.message` when `APP_DEBUG=true`.

**Migration:** for controlled user-facing messages use the curated API exception instead:

```php
// Old
abort(400, 'Invalid coupon code.');

// New (routed through the handler — trace_id + correlation headers attached)
throw \App\Exceptions\ApiException::badRequest('Invalid coupon code.');
```

#### 2.2 `ModelNotFoundException` message now embeds the model name

```diff
- body.message: "The requested resource was not found."
+ body.message: "User not found."          // or Role, Product, …
```

**Why:** `ApiExceptionHandler::modelNotFoundMessage` now resolves via `class_basename($e->getModel())` (K4 — matches the prior AGENTS.md contract). No security impact: the model class name is already inferable from the URL.

**Migration:** if frontend code regex-matches the message, loosen the pattern (`/(not found|bulunamadı)/i`) or branch on status code (404).

#### 2.3 `Api/AuthController` raw User → `UserResource`

```diff
  POST /api/v1/auth/login (default kind)
  POST /api/v1/auth/register (no-verification path)
  POST /api/v1/auth/two-factor-challenge
  GET  /api/v1/auth/me

- data.user: {
-     id: 1, first_name: "...", email: "...",
-     status: "active", email_verified_at: "...",
-     two_factor_confirmed_at: null,
-     avatar_url: "...", created_at: "...", updated_at: "..."
- }
+ data.user: <UserResource::toArray() output, app/Http/Resources/Admin/User/UserResource.php>
```

**Why:** raw Eloquent serialisation relied on `$hidden`; if a future sensitive column was added and forgotten, it would silently leak. `UserResource` makes the wire contract explicit.

**Migration:** inspect the fields returned by `UserResource` (`app/Http/Resources/Admin/User/UserResource.php`). If you depend on a raw-model field not declared in the resource, either extend `UserResource` or introduce a dedicated `AuthUserResource` used by `AuthController`.

### 3. Package update

```bash
composer update lvntr/laravel-starter-kit --with-all-dependencies
php artisan sk:update              # auto: ApiResponse + ApiExceptionHandler + sk-helpers + AssignTraceId
npm install                         # no JS changes, but keep it in the routine
```

`sk:update` auto-syncs:
- `app/Http/Responses/ApiResponse.php`
- `app/Exceptions/ApiExceptionHandler.php`
- `app/Helpers/sk-helpers.php`
- `app/Http/Middleware/AssignTraceId.php` (**new** — created if missing)
- `app/Http/Middleware/SecurityHeaders.php` (unchanged this release but tracked)

> **Important:** if `AssignTraceId.php` is missing after `sk:update`, the package-level `Bootstrap::middleware()` references `App\Http\Middleware\AssignTraceId` and **the first API request throws ClassNotFoundException**. A successful `sk:update` fixes this; to verify: `ls app/Http/Middleware/AssignTraceId.php`.

### 4. Manual controller patches (for published customisations)

`sk:update` never overwrites controllers (most projects add custom methods). Clean up the 11 leak sites by hand. The pattern is uniform:

```diff
- catch (LogicException $e) {
-     return to_api(null, $e->getMessage(), 422);
- }
+ catch (LogicException $e) {
+     throw \App\Exceptions\ApiException::unprocessable($e->getMessage());
+ }
```

**Affected files:**

| File | Method / count |
|---|---|
| `app/Http/Controllers/FileManagerController.php` | `bulkDelete`, `createFolder`, `renameFolder`, `moveItem`, `deleteFolder`, `upload`, `deleteFile` — 7 sites |
| `app/Http/Controllers/Api/UserController.php` | `destroy` — `to_api(null, 'Unauthenticated.', 401)` → `throw ApiException::unauthorized()`; `to_api(null, $e->getMessage(), 400)` → `throw ApiException::badRequest(...)` |
| `app/Http/Controllers/Api/Auth/AuthController.php` | `login` — `to_api(null, 'Invalid email or password.', 401)` → `throw ApiException::unauthorized(...)`; `twoFactorChallenge` — same for "Invalid or expired two-factor code." |

Remember to add `use App\Exceptions\ApiException;` at the top of each touched controller. Finally, in destroy-style methods move `return to_api(status: 204);` **outside** the `try` block (Step 2 exit-flow change):

```diff
- try {
-     $action->execute($user, (string) $performedById);
-     return to_api(status: 204);
- } catch (\LogicException $e) {
-     return to_api(null, $e->getMessage(), 400);
- }
+ try {
+     $action->execute($user, (string) $performedById);
+ } catch (\LogicException $e) {
+     throw ApiException::badRequest($e->getMessage());
+ }
+
+ return to_api(status: 204);
```

### 5. Api/AuthController UserResource migration (if published)

To adopt the Step 2.3 behaviour, patch `Api/Auth/AuthController.php`:

```diff
 use App\Domain\Auth\Actions\TwoFactorChallengeAction;
 use App\Domain\Auth\DTOs\LoginDTO;
 use App\Domain\Auth\DTOs\RegisterDTO;
+use App\Exceptions\ApiException;
 use App\Http\Controllers\Controller;
 use App\Http\Requests\Api\Auth\LoginRequest;
 use App\Http\Requests\Api\Auth\RegisterRequest;
 use App\Http\Requests\Api\Auth\TwoFactorChallengeRequest;
+use App\Http\Resources\Admin\User\UserResource;
 use App\Http\Responses\ApiResponse;

 public function register(...): ApiResponse
 {
     $result = $action->execute(...);
+    $userPayload = new UserResource($result['user']->loadMissing('roles'));

     if ($result['requires_verification']) {
         return to_api(
-            ['user' => $result['user'], 'requires_verification' => true],
+            ['user' => $userPayload, 'requires_verification' => true],
             'Registration successful. ...',
             201,
         );
     }

-    return to_api($result, 'Registration successful.', 201);
+    return to_api(
+        ['user' => $userPayload, 'token' => $result['token'], 'requires_verification' => false],
+        'Registration successful.',
+        201,
+    );
 }

 // login default branch
-    default => to_api(
-        ['user' => $result['user'], 'token' => $result['token']],
-        'Login successful.',
-    ),
+    default => to_api(
+        [
+            'user' => new UserResource($result['user']->loadMissing('roles')),
+            'token' => $result['token'],
+        ],
+        'Login successful.',
+    ),

 // me
-    return to_api($request->user());
+    return to_api(new UserResource($request->user()->loadMissing('roles')));

 // twoFactorChallenge
-    return to_api($result, 'Login successful.');
+    return to_api(
+        [
+            'user' => new UserResource($result['user']->loadMissing('roles')),
+            'token' => $result['token'],
+        ],
+        'Login successful.',
+    );
```

### 6. MakeDomainCommand scaffold (if published)

If `app/Console/Commands/MakeDomainCommand.php` was published, two spots need the new scaffold template:

```diff
 use {$dtoNamespace}\\{$this->dn}DTO;
+use App\Exceptions\ApiException;
 use App\Http\Controllers\Controller;
 ...

 public function destroy({$this->dn} \${$v}, Delete{$this->dn}Action \$action): ApiResponse|JsonResponse
 {
     try {
         \$action->execute(\${$v});
-
-        return to_api(status: 204);
     } catch (\LogicException \$e) {
-        return to_api(null, \$e->getMessage(), 400);
+        throw ApiException::badRequest(\$e->getMessage());
     }
+
+    return to_api(status: 204);
 }
```

If your `tests/Feature/Console/MakeDomainCommandTest.php` asserts the scaffold output, update it:

```diff
 expect(file_get_contents(app_path("Http/Controllers/Api/{$domain}Controller.php")))
-    ->toContain('return to_api(null, $e->getMessage(), 400);');
+    ->toContain('throw ApiException::badRequest($e->getMessage());');
```

### 7. Install-time fixes (OAuth + Postman settings + Passport personal client)

These three chores apply to **any existing install** that was seeded before 13.4.1. They are orthogonal to the API response work — run them after `sk:update` whether or not you published controllers.

#### 7.1 OAuth migrations made UUID-compatible

Three Passport migrations now use `foreignUuid` / `nullableUuidMorphs` instead of the default `foreignId` / `nullableMorphs`. This matches the `char(36)` primary key on `users.id` that the starter kit ships. Without this patch the API login path fails with `SQLSTATE 1265: Data truncated for column 'user_id'` the first time Passport tries to insert an access token.

Fresh installs pick this up automatically via `site:install`. For **existing installs**, re-run the three migrations against live data:

```bash
# 1. Roll back the three migrations (data-loss safe — oauth_* tables
#    are rebuilt on every token issue):
php artisan migrate:rollback --path=database/migrations/2026_03_04_205119_create_oauth_auth_codes_table.php
php artisan migrate:rollback --path=database/migrations/2026_03_04_205120_create_oauth_access_tokens_table.php
php artisan migrate:rollback --path=database/migrations/2026_03_04_205122_create_oauth_clients_table.php

# 2. Re-run with the new schema:
php artisan migrate
```

If you cannot roll back (rows with `char(36)` user ids already exist in a fork of your schema), apply the column change manually:

```sql
ALTER TABLE oauth_access_tokens MODIFY user_id CHAR(36) NULL;
ALTER TABLE oauth_auth_codes    MODIFY user_id CHAR(36) NOT NULL;
ALTER TABLE oauth_clients       MODIFY owner_id CHAR(36) NULL;
```

Verify with a login test — see Step 9 (Regression test).

#### 7.2 Postman / Apidog credentials moved from `.env` to the settings table

The earlier preview that wired Postman via three `.env` keys is gone. Configuration now lives in the `postman` / `apidog` settings groups and the `api_key` / `access_token` fields are encrypted at rest through `config/settings.php → sensitive_keys`.

If you had `POSTMAN_API_KEY`, `POSTMAN_WORKSPACE_ID`, or `POSTMAN_COLLECTION_ID` in `.env`, copy them into the settings table once, then delete the `.env` entries:

```bash
php artisan tinker --execute '
use App\Models\Setting;
Setting::setValue("postman.api_key", env("POSTMAN_API_KEY"));
Setting::setValue("postman.workspace_id", env("POSTMAN_WORKSPACE_ID"));
Setting::setValue("postman.collection_id", env("POSTMAN_COLLECTION_ID"));
echo "migrated";
'
```

Then remove the three keys from both `.env` and `.env.example`. The admin UI at **Settings → API Clients → Postman** shows the stored values (secrets are masked); use it to rotate the key later. Apidog is configured the same way at **Settings → API Clients → Apidog** (Access Token + Project ID).

#### 7.3 Passport personal access client (new `site:install` step)

`site:install` now runs `passport:client --personal --provider=users` automatically between `passport:keys` and the admin-user seed. If your existing install never had a personal access client (symptom: `RuntimeException: Personal access client not found for 'users'` on API login), create one once:

```bash
php artisan passport:client --personal --provider=users --name="$(php artisan config:show app.name)" --no-interaction
```

One row lands in `oauth_clients` with `revoked=0`. API token issuance starts working immediately — no app restart needed.

### 8. New additive features — no code changes required

These land automatically and surface new body fields / headers to clients. Loop in the frontend team:

| Feature | Where it appears |
|---|---|
| `trace_id` (UUID) | Every JSON body (success and error), plus `X-Request-ID` response header |
| `X-Correlation-ID` | Echoes a sanitised client-supplied `X-Request-ID` |
| `Retry-After` | Attached to 429 Too Many Requests responses |
| `simplePaginate()` support | `to_api(Model::simplePaginate(...))` works without a type error; meta carries `has_more` |
| "Sync to Postman" button | API Routes page → pushes the current OpenAPI spec to Postman once configured |
| "Sync to Apidog" button | API Routes page → pushes the current OpenAPI spec to Apidog once configured |
| Settings → API Clients tab | Postman + Apidog credentials UI; `postman.api_key` / `apidog.access_token` encrypted at rest |

### 9. Regression test — optional but recommended

The package ships `tests/Feature/Api/ApiResponseTest.php` — a 16-test contract file covering the envelope, exception mapping, trace id, 204, Retry-After, and the debug guard. If you don't already have one, copy the example:

```bash
cp vendor/lvntr/laravel-starter-kit/tests/examples/ApiResponseTest.php \
   tests/Feature/Api/ApiResponseTest.php
php artisan test --compact --filter=ApiResponseTest
```

Expected: 16 tests / 57 assertions pass. If something fails, confirm `AssignTraceId` is active in the `api` middleware group.

### 10. Rollback

If the release is reverted:

```bash
git revert <upgrade-commit>
composer install
php artisan sk:update --force   # restores published files to the previous version
```

`AssignTraceId.php` did not exist in 13.4.x — after rollback either delete it or leave it (no-op) provided the previous `Bootstrap.php` does not reference the class.

---

## 13.3.x → 13.4.0 — Security hardening sprint

> **Summary:** Following a three-pass parallel code review, ~37 findings were closed (HIGH: 13 → 1 manual, MEDIUM: 14, LOW: 4). The bulk of the changes are security (auth bypass, brute-force, XSS, log injection) and data integrity (missing DB transactions). **New installs** pick these fixes up automatically; **existing installs** must apply the patch list in this document.

### 0. Who is affected?

| Audience | What to do |
| --- | --- |
| Fresh installs (`composer create-project` + `sk:install`) | Nothing — stubs already carry the new version. |
| Existing consumer apps | Follow **Steps 1–8** in this document. |
| Consumers using only the package `src/` (never published) | `composer update lvntr/laravel-starter-kit` is enough. |

### 1. Pre-upgrade checklist

1. **Branch + backup:** `git checkout -b upgrade/v13.4.0 && git push`
2. **DB backup:** Snapshot / dump before rolling changes to production.
3. **Baseline:** Make sure `composer test` + `npm run build` pass on your current version.
4. **Expect a PR review:** Most of these changes are patch-style edits and deserve a real code review.

### 2. Package update

```bash
composer update lvntr/laravel-starter-kit --with-all-dependencies
npm install
```

This step picks up the Tier-1 changes (those that live inside the package `src/`) automatically:
- `SecurityHeaders` HSTS `preload` directive (`src/Http/Middleware/SecurityHeaders.php`)
- `MakeDomainCommand` / stub improvements

Everything else lives in published files, so **you must update your own copy** in the app.

---

### 3. HIGH — Security & data integrity patches

Apply these **in order**. Each is independent, but sequential commits keep history clean.

#### 3.1 (BE-H1) `UserPolicy::delete` + `Api\UserController::destroy` null guard

**File:** `app/Policies/UserPolicy.php`

Flip the self-match branch in `delete()`:

```diff
     public function delete(User $actor, User $user): bool
     {
         if ($actor->is($user)) {
-            return true;
+            return false;
         }

         if (! $this->canManage($actor, $user)) {
             return false;
         }

         return $actor->can('users.delete');
     }
```

**File:** `app/Http/Controllers/Api/UserController.php`

Add a null guard to `destroy`:

```diff
     public function destroy(Request $request, User $user, DeleteUserAction $action): ApiResponse|JsonResponse
     {
         Gate::authorize('delete', $user);

+        $performedById = $request->user()?->id;
+        if ($performedById === null) {
+            return to_api(null, 'Unauthenticated.', 401);
+        }
+
         try {
-            $action->execute($user, (string) $request->user()?->id);
+            $action->execute($user, (string) $performedById);
             return to_api(status: 204);
```

**Verify:** `DELETE /api/v1/users/{your_own_id}` must return 403 (policy denies it); an expired token must return 401.

---

#### 3.2 (BE-H2) `CreateRoleAction` + `UpdateRoleAction` DB transaction

**File:** `app/Domain/Role/Actions/CreateRoleAction.php`

```diff
 use App\Models\Role;
 use Illuminate\Support\Facades\Auth;
+use Illuminate\Support\Facades\DB;
 ...
     public function execute(RoleDTO $dto): Role
     {
-        $role = Role::create($dto->toArray());
-        $role->syncPermissions($dto->permissions);
+        $role = DB::transaction(function () use ($dto): Role {
+            $role = Role::create($dto->toArray());
+            $role->syncPermissions($dto->permissions);
+
+            return $role;
+        });

         RoleCreated::dispatch($role, Auth::id());
         return $role;
     }
```

**File:** `app/Domain/Role/Actions/UpdateRoleAction.php`

```diff
 use Illuminate\Support\Facades\Auth;
+use Illuminate\Support\Facades\DB;
 ...
         $oldPermissions = $role->permissions->pluck('name')->sort()->values()->all();

-        $role->update($data);
-        $role->refresh();
-        $role->syncPermissions($dto->permissions);
+        $role = DB::transaction(function () use ($role, $data, $dto): Role {
+            $role->update($data);
+            $role->refresh();
+            $role->syncPermissions($dto->permissions);
+
+            return $role;
+        });
```

---

#### 3.3 (BE-H3) `UpdateAuthSettingsAction` 2FA revoke transaction

**File:** `app/Domain/Setting/Actions/UpdateAuthSettingsAction.php`

```diff
 use App\Models\User;
+use Illuminate\Support\Facades\DB;

 ...
     public function execute(AuthSettingsDTO $dto): void
     {
-        $wasTwoFactorEnabled = Setting::getValue('auth.two_factor', '1') === '1';
-        $isTwoFactorDisabled = $dto->twoFactor === '0';
-
-        Setting::setGroup('auth', $dto->toArray());
-
-        if ($wasTwoFactorEnabled && $isTwoFactorDisabled) {
-            $this->revokeAllTwoFactorAuth();
-        }
+        DB::transaction(function () use ($dto): void {
+            $wasTwoFactorEnabled = Setting::getValue('auth.two_factor', '1') === '1';
+            $isTwoFactorDisabled = $dto->twoFactor === '0';
+
+            Setting::setGroup('auth', $dto->toArray());
+
+            if ($wasTwoFactorEnabled && $isTwoFactorDisabled) {
+                $this->revokeAllTwoFactorAuth();
+            }
+        });
     }
```

---

#### 3.4 (BE-H4) `LogoutUserAction` null-safe

**File:** `app/Domain/Auth/Actions/LogoutUserAction.php`

```diff
     public function execute(User $user): void
     {
-        $user->token()->revoke();
+        $user->token()?->revoke();
     }
```

A single character — but in production a logout request from a user without an active access token currently 500s.

---

#### 3.5 (BE-H5) FileManager N+1 fix

**Files:** `app/Domain/FileManager/Actions/BulkDeleteAction.php` and `DeleteFolderAction.php`.

Replace the `collectDescendantIds` method in both files — the new version loads the owner-scoped `parent_id` map in a single query and walks the tree in PHP. Because the change is large, copy the full new files from `vendor/lvntr/laravel-starter-kit/stubs/app/Domain/FileManager/Actions/BulkDeleteAction.php` and `DeleteFolderAction.php`.

**Highlights:**
- `BulkDeleteAction` gains `buildChildrenMap(FileManagerContextDTO $context): array`. `collectDescendantIds($folder, $childrenByParent)` takes the map as a parameter.
- `DeleteFolderAction::collectDescendantIds` now takes a context parameter and loads every folder row belonging to the owner in a single query.

A 50-level folder tree drops from 50 queries to 1.

---

#### 3.6 (BE-H6) SMTP encryption `'none'` fix

**File:** `app/Providers/SettingsServiceProvider.php`

```diff
             if (array_key_exists('encryption', $mail)) {
-                config(['mail.mailers.smtp.encryption' => $mail['encryption']]);
+                // Laravel's SMTP mailer expects null (not the string "none") to send without TLS.
+                $encryption = $mail['encryption'] === 'none' ? null : $mail['encryption'];
+                config(['mail.mailers.smtp.encryption' => $encryption]);
             }
```

---

#### 3.7 (GV-H2 + GV-H3) `ApiExceptionHandler` — message leak + X-Request-ID injection

**File:** `app/Exceptions/ApiExceptionHandler.php`

Two changes:

**A) Change trace ID generation in `handle()`:**

```diff
     private static function handle(Throwable $e, Request $request): JsonResponse
     {
-        // 1. Trace ID — use client-provided value or generate a new one
-        $traceId = $request->header('X-Request-ID', (string) Str::uuid());
+        // 1. Trace ID — always server-generated to prevent log / header injection.
+        //    Any client-supplied X-Request-ID is accepted as correlation metadata
+        //    only after being sanitised and length-capped.
+        $traceId = (string) Str::uuid();
+        $clientRequestId = self::sanitizeClientRequestId($request->header('X-Request-ID'));

         // 2. Status + Message mapping
         [$status, $message] = self::resolve($e);

         // 3. Logging — 500+ non-validation errors
         if ($status >= 500 && ! ($e instanceof ValidationException)) {
             Log::error("[API {$status}] {$message}", [
                 'trace_id' => $traceId,
+                'client_request_id' => $clientRequestId,
                 'exception' => get_class($e),
                 ...
             ]);
         }
```

**B) Harden the `default` arm in `resolve()` and add the new helper to the class:**

```diff
-            // Unexpected errors
             default => [
                 500,
-                config('app.debug') ? $e->getMessage() : 'A server error occurred.',
+                'A server error occurred.',
             ],
         };
     }

+    /**
+     * Accept a client-provided X-Request-ID only if it matches a safe charset
+     * (letters, digits, dash, underscore, dot) and is ≤ 128 chars long.
+     */
+    private static function sanitizeClientRequestId(mixed $value): ?string
+    {
+        if (! is_string($value) || $value === '') {
+            return null;
+        }
+
+        $trimmed = substr($value, 0, 128);
+
+        return preg_match('/^[A-Za-z0-9._-]+$/', $trimmed) === 1 ? $trimmed : null;
+    }
```

---

#### 3.8 (FE-H1) Axios CSRF defaults

**File:** `resources/js/app.ts`

At the top of the file, right after the imports:

```diff
 import '../css/app.css';
 import 'primeicons/primeicons.css';
 import { createInertiaApp, usePage } from '@inertiajs/vue3';
+import axios from 'axios';
 import { i18nVue } from 'laravel-vue-i18n';
 ...
 import { PermissionPlugin } from '@/plugins/permission';

+// Axios defaults — send session + XSRF cookies on every request so Fortify
+// endpoints that rely on the web session (2FA, sessions, password-confirm)
+// stay CSRF-protected. XSRF cookie/header names match Laravel's defaults.
+axios.defaults.withCredentials = true;
+axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
+axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';
+axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
+axios.defaults.headers.common['Accept'] = 'application/json';
```

---

#### 3.9 (FE-H2) `TwoFactorTab` QR SVG XSS fix

**File:** `resources/js/pages/Profile/components/TwoFactorTab.vue` (or legacy path `pages/Profile/TwoFactorTab.vue`)

**A) In `<script setup>` — right below the `qrCodeSvg` ref:**

```diff
     const qrCodeSvg = ref('');
     const setupKey = ref('');
     const recoveryCodes = ref<string[]>([]);
     const showRecoveryCodes = ref(false);

+    /**
+     * Render the Fortify QR SVG through an <img src="data:..."> element
+     * rather than v-html. An <img> sandbox neutralises any inline <script>
+     * or event handlers that a compromised intermediary could smuggle in.
+     */
+    const qrCodeDataUrl = computed<string>(() => {
+        if (!qrCodeSvg.value) return '';
+        try {
+            const encoded = window.btoa(unescape(encodeURIComponent(qrCodeSvg.value)));
+            return `data:image/svg+xml;base64,${encoded}`;
+        } catch {
+            return '';
+        }
+    });
```

**B) In the template — replace the `v-html` block:**

```diff
-                            <!-- eslint-disable vue/no-v-html -- QR SVG from trusted server -->
-                            <div class="inline-block rounded-lg bg-white p-4" v-html="qrCodeSvg" />
-                            <!-- eslint-enable vue/no-v-html -->
+                            <div class="inline-block rounded-lg bg-white p-4">
+                                <img
+                                    v-if="qrCodeDataUrl"
+                                    :src="qrCodeDataUrl"
+                                    :alt="$t('sk-profile.two_factor_scan')"
+                                    class="h-48 w-48"
+                                />
+                            </div>
```

---

#### 3.10 (FE-H3) `useDefinition.load()` error handling

**File:** `resources/js/composables/useDefinition.ts`

Replace both `load()` and `loadAll()` with the new versions from `vendor/lvntr/laravel-starter-kit/stubs/resources/js/composables/useDefinition.ts`. The core change: every `fetch` call is wrapped in `try/catch`, `res.ok` is checked, `loaded.value` stays false on failure, and errors are logged to the console.

---

### 4. MEDIUM — Authorization, performance, UX

#### 4.1 (BE-M1) FormRequest `authorize(): true` cleanup

In the following files, replace `return true;` with the corresponding permission check:

| File | Permission |
| --- | --- |
| `app/Http/Requests/Admin/User/StoreUserRequest.php` | `users.create` |
| `app/Http/Requests/Api/User/StoreUserRequest.php` | `users.create` |
| `app/Http/Requests/Admin/Role/StoreRoleRequest.php` | `roles.create` |
| `app/Http/Requests/Admin/Settings/UpdateAuthSettingsRequest.php` | `settings.update` |
| `app/Http/Requests/Admin/Settings/UpdateGeneralSettingsRequest.php` | `settings.update` |
| `app/Http/Requests/Admin/Settings/UpdateMailSettingsRequest.php` | `settings.update` |
| `app/Http/Requests/Admin/Settings/UpdateStorageSettingsRequest.php` | `settings.update` |
| `app/Http/Requests/Admin/Settings/UpdateFileManagerSettingsRequest.php` | `settings.update` |
| `app/Http/Requests/Admin/Settings/UpdateTurnstileSettingsRequest.php` | `settings.update` |
| `app/Http/Requests/Admin/Settings/SendTestMailRequest.php` | `settings.update` |

```diff
     public function authorize(): bool
     {
-        return true;
+        return $this->user()?->can('users.create') ?? false;
     }
```

(Swap in the right permission name per file.)

Also `app/Http/Requests/DestroySessionsRequest.php`:

```diff
-        return true;
+        return $this->user() !== null;
```

**Leave auth / public endpoints alone:** `Api/Auth/LoginRequest.php`, `RegisterRequest.php`, `TwoFactorChallengeRequest.php` remain public.

**Leave FileManager endpoints alone:** `FileManagerRequest.php` and its subclasses rely on context-based authorization.

---

#### 4.2 (BE-M4) TwoFactorChallenge brute-force hardening

**File:** `app/Domain/Auth/Actions/TwoFactorChallengeAction.php`

Add `Cache::forget($cacheKey)` to all three failure arms — the challenge becomes single-use:

```diff
         if ($code !== null && $code !== '') {
             $valid = $this->provider->verify(...);

             if (! $valid) {
+                Cache::forget($cacheKey);
+
                 return null;
             }
         } elseif ($recoveryCode !== null && $recoveryCode !== '') {
             $match = collect($user->recoveryCodes())->first(...);

             if ($match === null) {
+                Cache::forget($cacheKey);
+
                 return null;
             }

             $user->replaceRecoveryCode($match);
         } else {
+            Cache::forget($cacheKey);
+
             return null;
         }
```

The route-level `throttle:5,1` is already in place.

---

#### 4.3 (BE-M7 + BE-M12) `SettingService` transaction + cache

**File:** `app/Domain/Setting/SettingService.php`

Easiest path: replace the whole file with `vendor/lvntr/laravel-starter-kit/stubs/app/Domain/Setting/SettingService.php`. In summary:

1. `DB` facade import added.
2. `getValue()` and `getGroup()` now read from the `allGrouped()` cache — no per-lookup queries.
3. `setGroup()` is wrapped in `DB::transaction(...)`.

Same behaviour, better performance and atomicity.

---

#### 4.4 (BE-M8) `MoveItemRequest` tighter validation

**File:** `app/Http/Requests/FileManager/MoveItemRequest.php`

```diff
 <?php

 namespace App\Http\Requests\FileManager;

+use Illuminate\Validation\Rule;
+
 class MoveItemRequest extends FileManagerRequest
 {
     public function rules(): array
     {
+        $itemType = $this->input('item_type');
+
+        $itemIdRules = ['required'];
+        if ($itemType === 'file') {
+            $itemIdRules = ['required', 'integer', 'min:1'];
+        } elseif ($itemType === 'folder') {
+            $itemIdRules = ['required', 'uuid'];
+        }
+
         return [
             ...$this->contextRules(),
-            'item_type' => ['required', 'string', 'in:folder,file'],
-            'item_id' => ['required'],
+            'item_type' => ['required', 'string', Rule::in(['folder', 'file'])],
+            'item_id' => $itemIdRules,
             'target_folder_id' => ['nullable', 'uuid'],
         ];
     }
 }
```

---

#### 4.5 (BE-M9) `DeleteFolderRequest` FormRequest

**New file:** `app/Http/Requests/FileManager/DeleteFolderRequest.php`

```php
<?php

namespace App\Http\Requests\FileManager;

class DeleteFolderRequest extends FileManagerRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->contextRules();
    }
}
```

**File:** `app/Http/Controllers/FileManagerController.php`

Add the use statement + change the method signature:

```diff
 use App\Http\Requests\FileManager\BulkDeleteRequest;
+use App\Http\Requests\FileManager\DeleteFolderRequest;
 use App\Http\Requests\FileManager\MoveItemRequest;
 ...

-    public function deleteFolder(Request $request, FileFolder $folder, DeleteFolderAction $action): ApiResponse
+    public function deleteFolder(DeleteFolderRequest $request, FileFolder $folder, DeleteFolderAction $action): ApiResponse
     {
-        $context = $this->contextFromRequest($request);
+        $context = $request->context();
         $this->authorizer->authorizeWrite($context);
```

---

#### 4.6 (BE-M10) `uploadAvatar` Gate::authorize consistency

**File:** `app/Http/Controllers/Admin/UserController.php`

```diff
     public function uploadAvatar(UploadAvatarRequest $request, User $user, UploadMediaAction $action): ApiResponse
     {
+        Gate::authorize('update', $user);
+
         $action->execute($user, $request, 'avatar');
```

---

#### 4.7 (FE-M1) `useDialog` timer leak

**File:** `resources/js/composables/useDialog.ts`

Full version in `vendor/lvntr/laravel-starter-kit/stubs/resources/js/composables/useDialog.ts`. The edits:

1. Module-level `let closeTimer: ReturnType<typeof setTimeout> | null = null;` right below `state`.
2. `open()` starts with `clearTimeout(closeTimer)` + `closeTimer = null`.
3. `close()` does the same clear, then `closeTimer = setTimeout(..., 300)`, and the timeout body sets `closeTimer = null`.

---

#### 4.8 (FE-M2) `useImageLightbox` timer leak

Same pattern as `useDialog`. Copy from `vendor/lvntr/laravel-starter-kit/stubs/resources/js/composables/useImageLightbox.ts`.

---

#### 4.9 (FE-M4) `SkForm` isDirty guard — prevent data loss

**File:** `resources/js/components/Lvntr-Starter-Kit/FormBuilder/SkForm.vue` (if you import the component directly from the package instead, this change arrives via `composer update` — the package source was fixed).

Add the isDirty arm to the `watch(derivedDefaults, …)` block:

```diff
     watch(derivedDefaults, (newValues, oldValues) => {
         if (!isInternalMode.value) {
             return;
         }
         if (oldValues && shallowRecordEqual(newValues, oldValues)) {
             return;
         }
+        if (internalForm.isDirty) {
+            internalForm.defaults(newValues);
+            return;
+        }
         restoringDefaults.value = true;
```

---

#### 4.10 (FE-M6) `SkDatatable` urlFilters → api.get

**File:** `resources/js/components/Lvntr-Starter-Kit/DatatableBuilder/SkDatatable.vue`

```diff
     if (urlFilters.length) {
         onMounted(async () => {
-            await Promise.all(
+            await Promise.allSettled(
                 urlFilters.map(async (f) => {
-                    const res = await fetch(f.optionsUrl!, {
-                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
-                        credentials: 'same-origin',
-                    });
-                    const json = await res.json();
-                    urlOptions[f.key] = json.data ?? json;
+                    try {
+                        const data = await api.get<FilterOption[]>(f.optionsUrl!);
+                        urlOptions[f.key] = data ?? [];
+                    } catch {
+                        urlOptions[f.key] = [];
+                    }
                 }),
             );
         });
     }
```

In the same file, change `let activeMenuItems = ref<MenuItem[]>([]);` → `const activeMenuItems = ref<MenuItem[]>([]);` (FE-M9).

---

#### 4.11 (FE-M7) `TwoFactorTab` router.reload await

**File:** `resources/js/pages/Profile/components/TwoFactorTab.vue`

```diff
     async function enableTwoFactor() {
         twoFactorProcessing.value = true;

         if (!props.twoFactorEnabled) {
             await axios.post('/user/two-factor-authentication');
-            router.reload({ only: ['twoFactorEnabled', 'twoFactorConfirmed'] });
+            await new Promise<void>((resolve) => {
+                router.reload({
+                    only: ['twoFactorEnabled', 'twoFactorConfirmed'],
+                    onFinish: () => resolve(),
+                });
+            });
         }

         await loadQrAndSetupKey();
```

---

#### 4.12 (FE-M8) Drop `as any` casts

**File:** `resources/js/pages/Profile/components/ProfileInfoTab.vue`

```diff
-        :avatar-url="(user as any)?.avatar_url"
+        :avatar-url="user?.avatar_url"
```

**File:** `resources/js/pages/Admin/Users/components/UserForm.vue`

```diff
-            :avatar-url="(formRef.remoteData as any)?.avatar_url"
+            :avatar-url="(formRef.remoteData as { avatar_url?: string | null } | null)?.avatar_url"
```

---

### 5. Config / env hardening

#### 5.1 (GV-M1) LOG_LEVEL in `.env.example` and `.env`

**File:** `.env.example`

```diff
-LOG_LEVEL=debug
+LOG_LEVEL=error
```

Make sure production `.env` files use `LOG_LEVEL=error` or `warning` as well.

---

#### 5.2 (GV-M2) Move tinker from `require` to `require-dev`

**File:** `composer.json`

```diff
     "require": {
         "php": "^8.3",
         "laravel/framework": "^13.0",
         "laravel/pulse": "^1.7",
-        "laravel/tinker": "^2.10.1 || ^3.0",
         "lvntr/laravel-starter-kit": "@dev"
     },
     "require-dev": {
         ...
         "laravel/sail": "^1.41",
+        "laravel/tinker": "^2.10.1 || ^3.0",
         "mockery/mockery": "^1.6",
```

Then: `composer update`.

---

#### 5.3 (GV-M3, GV-M4) `.env.example` — Turnstile & Passport key placeholders

**File:** `.env.example`

Add after the Passport section:

```
# Passport OAuth2 keys — prefer loading via env in production instead of
# committing the key files at storage/oauth-*.key. Run `php artisan passport:keys`
# once, move the generated strings into these env vars, then delete the files.
# PASSPORT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"
# PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"

# Cloudflare Turnstile (bot / captcha). When TURNSTILE_ENABLED=false the
# `turnstile` middleware becomes a no-op, so leaving the keys empty during
# development is safe.
TURNSTILE_ENABLED=false
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
```

---

#### 5.4 (GV-M5) `HandleInertiaRequests` — appEnv / appDebug scope

**File:** `app/Http/Middleware/HandleInertiaRequests.php`

```diff
             'appVersion' => InstalledVersions::getPrettyVersion('lvntr/laravel-starter-kit'),
-            'appEnv' => config('app.env'),
-            'appDebug' => config('app.debug'),
+            'appEnv' => fn () => app()->environment('production') ? null : config('app.env'),
+            'appDebug' => fn () => app()->environment('production') ? false : (bool) config('app.debug'),
```

If any front-end code branches on `appEnv === 'production'`, update it to expect `null` in that case.

---

#### 5.5 (GV-M7) CORS preflight cache

**File:** `config/cors.php`

```diff
-    'max_age' => 0,
+    // Cache preflight (OPTIONS) results in the browser for 2 hours so SPA /
+    // mobile clients don't re-run the CORS handshake on every mutating call.
+    'max_age' => 7200,
```

---

#### 5.6 (GV-L1) `Password::defaults` policy

**File:** `app/Providers/AppServiceProvider.php`

```diff
 use Illuminate\Support\Facades\Event;
 use Illuminate\Support\ServiceProvider;
+use Illuminate\Validation\Rules\Password;

 class AppServiceProvider extends ServiceProvider
 {
     ...
     public function boot(): void
     {
         Event::listen(Login::class, UpdateLastLogin::class);
+
+        Password::defaults(function () {
+            return Password::min(10)
+                ->mixedCase()
+                ->letters()
+                ->numbers()
+                ->symbols();
+        });
     }
 }
```

**Heads up:** This change does NOT invalidate existing users' passwords, but new registration / password-change flows now require 10+ characters with mixed case, digits, and symbols.

---

### 6. GV-H1 — Passport private key rotation (CRITICAL, MANUAL)

This step involves destructive operations; run it **off-hours, with team sign-off and a rollback plan**.

```bash
# 1. Install git-filter-repo (filter-branch is deprecated)
brew install git-filter-repo          # or: pipx install git-filter-repo

# 2. Strip the key files from history
cd /path/to/starter-kit-app
git filter-repo --path storage/oauth-private.key --invert-paths
git filter-repo --path storage/oauth-public.key  --invert-paths

# 3. Generate a fresh key pair (file form for now)
php artisan passport:keys --force

# 4. Move the contents into .env, delete the files
# (PASSPORT_PRIVATE_KEY and PASSPORT_PUBLIC_KEY — config/passport.php already reads them)
rm storage/oauth-private.key storage/oauth-public.key

# 5. Purge every active token
php artisan passport:purge

# 6. Force-push (team sign-off required)
git push --force-with-lease origin <branch>
```

**Important:**
- Everyone on the team must `git fetch && git reset --hard origin/<branch>` after the force push.
- Scrub any cached repo copies on CI/CD runners.
- Put `PASSPORT_*` env values in your production vault / secrets manager (never commit to git).

---

### 7. Verification

```bash
# Backend
composer install
php artisan migrate --force
php artisan sk:seed-permissions --fresh
vendor/bin/pint --dirty --format agent

# Frontend
npm install
npm run build

# Tests
php artisan test --compact
npm run test
```

Do not commit until everything turns green. If a test breaks, isolate and hot-fix the offending patch — don't defer to the other patches in this release; they're all independent.

### 8. Smoke-test checklist

- [ ] Login → 2FA challenge → wrong code → consumes the single attempt (BE-M4).
- [ ] API `DELETE /api/v1/users/{your_own_id}` returns 403 (BE-H1).
- [ ] Role create + permission assignment: changes land in the DB (BE-H2).
- [ ] Settings > Auth disable 2FA: every user's 2FA secret is cleared + setting saved (BE-H3).
- [ ] Large folder (50+ levels) bulk delete: no timeouts (BE-H5).
- [ ] SMTP encryption set to "none": mail sends successfully (BE-H6).
- [ ] With `APP_DEBUG=true`, a 500 on an API endpoint: response `message` is generic; details live in the `debug` block (GV-H2).
- [ ] Request with `X-Request-ID: ../etc/passwd`: response header `X-Request-ID` is a UUID; log has `client_request_id: null` (GV-H3).
- [ ] 2FA setup page: QR code renders as `<img>`, no `v-html` (FE-H2).
- [ ] Rapid dialog open/close/open: content survives (FE-M1).
- [ ] FormBuilder form open, parent prop changes: user input is not wiped (FE-M4).

---

## Troubleshooting

### "422 Unprocessable Content" — new FormRequest authorize
The new `authorize()` check is strict. Make sure the permission is actually assigned to the user: run `php artisan sk:seed-permissions --fresh`.

### 2FA verification says "challenge expired"
After BE-M4 the challenge is single-use. If the 6-digit code is wrong the flow restarts — pull the fresh code from your OTP app (it rotates every 30 seconds) and log in again.

### Axios requests aren't 419'ing but there's no session
After FE-H1 `withCredentials = true`. If your front-end is served from a different domain (subdomains included) make sure `config/cors.php` sets `supports_credentials => true` and `allowed_origins` does not contain a wildcard.

### Dashboard looks empty
`appEnv` / `appDebug` are now `null` / `false` in prod — if your Vue templates branch on them, make sure you have a fallback.

---

## Previous releases

- **13.3.3** (2026-04-20) — Windows build fix: sibling `core.ts` barrel for Builder `core/` imports. Details: [CHANGELOG.md](CHANGELOG.md).
- **13.3.2** (2026-04-19) — Security hardening + user audit + API auth parity. Details: [CHANGELOG.md](CHANGELOG.md).

Full change history lives in [CHANGELOG.md](CHANGELOG.md).
