# Module Route Registry

The starter kit uses a **module route registry** to mount vendor-resident route groups into consumer applications. It handles the two scenarios that arise when a package ships its own routes: fresh installs where the consumer has no route file yet, and customized installs where the consumer has overridden the stub.

## How It Works

`StarterKitServiceProvider::moduleRouteRegistry()` returns a list of module descriptors. `registerRoutes()` iterates over them and applies this logic for each module:

1. **Override check** — if any file listed in `overrideStubs` exists in the consumer app (`base_path(...)`), the package skips that module entirely. The consumer's route orchestrator (`routes/web.php` or `routes/api.php`) is responsible for loading the stub.
2. **Auto-mount** — if no override stub is found, the package mounts the module under its declared `middleware` tier via `Route::middleware(...)->group($loader)`.

This ensures a module is never registered twice regardless of which path is taken.

## Module Descriptor Shape

Each entry in the registry has four fields:

```php
[
    'name'          => 'file-manager',          // module identifier (used for logging/debugging)
    'overrideStubs' => [                        // absolute paths; any match → skip
        base_path('routes/web/file-manager-route.php'),
        base_path('routes/api/file-manager-route.php'),
    ],
    'middleware'    => ['web', 'auth', 'verified'], // outer group for auto-mount path
    'loader'        => static function (): void {   // closure that calls the vendor route file
        FileManager::routes();
    },
]
```

The registry lives inside `StarterKitServiceProvider` (not `config/`) because `loader` is a closure — closures cannot be serialized by `config:cache`. The service provider method is the single source of truth for each module's middleware tier.

## Override Mechanism

When a consumer installs the kit, `sk:install` copies stub route files into the app:

- `routes/web/file-manager-route.php`
- `routes/api/file-manager-route.php`

The presence of either file signals to the registry that the consumer's orchestrator owns the mount. The package steps aside and does not mount the module.

### Customizing a Module's Routes

Replace the stub with your own route file. The package detects the file and skips its auto-mount. Your file becomes the sole place where routes for that module are registered.

```php
// routes/web/file-manager-route.php — consumer-owned override
// Replace the one-liner below with your own group and controller.

use Lvntr\StarterKit\Facades\FileManager;

FileManager::routes(); // keep vendor routes, or remove this and write your own
```

Keep the file thin if you only want the vendor routes — the one-liner stub delegates to the package's route file and gives you a future-proof upgrade path via `composer update`.

### Removing the Override (reverting to auto-mount)

Delete the stub file. The registry falls back to the auto-mount path on the next request boot. This is safe — the middleware tier is identical to what the orchestrator applied when the stub was present.

## File Manager — Working Example

File manager is the only vendor-resident module currently in the registry. Its descriptor:

| Field | Value |
| --- | --- |
| `name` | `file-manager` |
| `overrideStubs` | `routes/web/file-manager-route.php`, `routes/api/file-manager-route.php` |
| `middleware` | `web`, `auth`, `verified` |
| `loader` | `FileManager::routes()` |

The loader calls `src/routes/file-manager.php` through the `FileManager` facade. That file declares the full route group under the `file-manager.` name prefix.

### Share Endpoint Security (K1)

The public share endpoint (`GET /file-manager/share/{media}`) uses `withoutMiddleware(['auth', 'verified', 'auth:sanctum', 'auth:api'])` inside the route file itself. This means it is accessible to unauthenticated users with a valid signed URL regardless of which outer middleware group mounts it — whether that is the auto-mount path or a consumer stub. The endpoint is protected only by the `signed` and `throttle:60,1` middleware declared on the route directly.

## Adding a New Vendor-Resident Module (Recipe)

This recipe applies to Faz 3 / Faz 6 modules that move their routes from consumer stubs into the vendor package.

**Step 1.** Create the vendor route file at `src/routes/<module-name>.php`. Declare all routes inside it. Do not attach outer auth middleware here — the registry controls the outer tier.

**Step 2.** Add a loader method or facade method that requires the file, for example:

```php
// src/Facades/MyModule.php
public static function routes(): void
{
    require __DIR__.'/../routes/my-module.php';
}
```

**Step 3.** Append a descriptor to `moduleRouteRegistry()` in `StarterKitServiceProvider`:

```php
[
    'name'          => 'my-module',
    'overrideStubs' => [
        base_path('routes/web/my-module-route.php'),
    ],
    'middleware'    => ['web', 'auth', 'verified'],  // adjust per module requirements
    'loader'        => static function (): void {
        MyModule::routes();
    },
],
```

**Step 4.** Create the stub at `stubs/routes/web/my-module-route.php` — a one-liner that calls `MyModule::routes()`. `sk:install` copies it to the consumer app on install. `sk:update` refreshes it on update.

**Step 5.** Write tests covering:
- stub absent → vendor auto-mounts under the declared middleware tier
- stub present → package skips mount (no double-registration, no route name clash)
- module-specific route names resolve correctly

No further wiring is needed in `registerRoutes()` — the loop picks up the new descriptor automatically.

## Middleware Tier Reference

| Module | Middleware | Notes |
| --- | --- | --- |
| `file-manager` | `web`, `auth`, `verified` | No permission middleware; share endpoint strips auth via `withoutMiddleware` in the route file |

New modules should declare their tier explicitly in the descriptor. A module that requires the `check.permission` middleware must include it in `middleware` — the registry does not infer it.
