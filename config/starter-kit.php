<?php

use Lvntr\StarterKit\Http\Middleware\CheckResourcePermission;

return [

    /*
    |--------------------------------------------------------------------------
    | Package Migrations
    |--------------------------------------------------------------------------
    |
    | When true (default), the package auto-loads its own migrations via
    | loadMigrationsFrom() so consumer apps do not need to publish them.
    | Filenames inside database/migrations/ MUST stay stable across releases:
    | Laravel records the bare basename in the `migrations` table, so any
    | rename would re-run an already-applied migration and likely fail with
    | "table already exists". Apps that prefer to own a physical copy can
    | still publish via `vendor:publish --tag=starter-kit-migrations` and
    | flip this flag to false to disable auto-load.
    |
    */

    'run_migrations' => true,

    /*
    |--------------------------------------------------------------------------
    | Stub Manifest Version
    |--------------------------------------------------------------------------
    |
    | Used by sk:update to track which files have been published
    | and whether they have been modified by the user.
    |
    */

    'version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Published Stubs Hash Registry
    |--------------------------------------------------------------------------
    |
    | Stores hashes of published stubs so sk:update can detect
    | user modifications and skip those files.
    | This is auto-managed — do not edit manually.
    |
    */

    'published_hashes' => storage_path('starter-kit/hashes.json'),

    /*
    |--------------------------------------------------------------------------
    | Datatable defaults
    |--------------------------------------------------------------------------
    |
    | Used by DatatableQueryBuilder when the caller does not override the
    | value via perPage() or ?per_page=. Existing callers are unaffected —
    | the builder falls back to 10 when this key is absent.
    |
    | `max_per_page` caps the value accepted from the `?per_page=` query
    | parameter to protect against expensive queries / large payloads. The
    | builder falls back to 100 when this key is absent.
    |
    */

    'datatable' => [
        'default_per_page' => (int) env('STARTER_KIT_DATATABLE_PER_PAGE', 10),
        'max_per_page' => (int) env('STARTER_KIT_DATATABLE_MAX_PER_PAGE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application namespace
    |--------------------------------------------------------------------------
    |
    | The namespace used by the consumer application. Publish/install flows
    | rewrite `App\…` references in published stubs to this value when it is
    | not the default `App`. Leave as `App` to keep the historical behavior.
    |
    */

    'app_namespace' => env('STARTER_KIT_APP_NAMESPACE', 'App'),

    /*
    |--------------------------------------------------------------------------
    | Eloquent strict mode
    |--------------------------------------------------------------------------
    |
    | When true (default), StarterKitServiceProvider enables Eloquent strict
    | mode (Model::shouldBeStrict) OUTSIDE production only — lazy-loading,
    | accessing a missing attribute and silently discarding a non-fillable
    | assignment all throw during local/staging/testing so bugs surface early,
    | while production traffic is never risked with a strictness 500.
    |
    | Set to false to opt out of this opinionated global mutation entirely
    | (e.g. when integrating a legacy schema that trips these guards).
    |
    */

    'strict_models' => env('STARTER_KIT_STRICT_MODELS', true),

    /*
    |--------------------------------------------------------------------------
    | Resource permission gating (CheckResourcePermission)
    |--------------------------------------------------------------------------
    |
    | The CheckResourcePermission middleware derives the required permission
    | from the route name (admin.users.index → users.read). When the resolved
    | permission is NOT seeded in the database the middleware is FAIL-CLOSED by
    | default: only `local` allows the request through (+ a logged warning);
    | every other environment — production, staging, uat, demo, testing —
    | denies it. This stops a forgotten permission row from silently exposing
    | an endpoint on a public non-production host.
    |
    | Two DIFFERENT failure axes live here — do not confuse them:
    |
    |   UNMAPPED   — a permission WAS derived from the route name, but no row
    |                with that name is seeded in the database.
    |   UNRESOLVED — NO permission could be derived at all: the route has no
    |                name, its name has fewer than two segments, or its action
    |                segment is not in the middleware's ACTION_ABILITY_MAP.
    |
    | `allow_unmapped` (env: STARTER_KIT_ALLOW_UNMAPPED_PERMISSIONS) covers the
    | first. Set it to true to restore the legacy behavior where ANY
    | non-production environment lets the unmapped permission through with a
    | warning. Production always denies regardless of this flag; local always
    | allows.
    |
    | `allow_unresolved` (env: STARTER_KIT_ALLOW_UNRESOLVED_ROUTES) covers the
    | second. Historically an unresolved route passed through in TOTAL SILENCE,
    | which is exactly how an ungated endpoint hides. With this flag true (the
    | shipped default) the request still passes but the middleware logs a
    | throttled warning naming the route, so the gap is visible. Set it to
    | false to deny instead.
    |
    | WHO GETS WHICH DEFAULT: `sk:install` seeds
    | STARTER_KIT_ALLOW_UNRESOLVED_ROUTES=false into a NEW project's .env, so a
    | fresh app is fail-closed from the first request. An app that sets nothing
    | falls through to CheckResourcePermission::ALLOW_UNRESOLVED_DEFAULT, which
    | is true.
    |
    | NO RELEASE FLIPS THAT CONSTANT ON A LIVE APP. A published copy of this
    | file predating the key lands on the same constant, so changing it would
    | alter authorization on a plain `composer update` for apps that edited
    | nothing. An existing install opts in itself: audit with
    | `php artisan sk:doctor --only=unresolved-routes`, then set the env var
    | (or this key) to false.
    |
    | ASYMMETRY, deliberate: unlike `allow_unmapped`, `allow_unresolved` keeps
    | applying in production once flipped. An unmapped permission is a DATA gap
    | the operator fixes on the host by seeding the row; an unresolved route is
    | a STRUCTURAL mismatch between the route table and the ability map, fixable
    | only by renaming a route or shipping code. The escape hatch therefore has
    | to exist on the host where it breaks.
    |
    | `unrestricted_routes` lists route-name patterns (Str::is wildcards, e.g.
    | 'api.v1.auth.*') that are DELIBERATELY permission-free: they pass with no
    | warning and are never denied. This is the supported way to declare intent.
    | Two limits worth knowing:
    |   - It is consulted ONLY on the UNRESOLVED axis. It can never disable the
    |     check for a route whose permission DOES resolve, so it cannot be used
    |     to bypass a real gate.
    |   - Keep the patterns TIGHT. A broad entry such as 'admin.*' exempts every
    |     unresolved admin route at once — including ones added later that you
    |     never reviewed — and permanently opts them out of the flip above.
    |     Prefer listing endpoints, not trees.
    |
    */

    'permissions' => [
        'allow_unmapped' => (bool) env('STARTER_KIT_ALLOW_UNMAPPED_PERMISSIONS', false),

        'allow_unresolved' => (bool) env(
            'STARTER_KIT_ALLOW_UNRESOLVED_ROUTES',
            CheckResourcePermission::ALLOW_UNRESOLVED_DEFAULT,
        ),

        'unrestricted_routes' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Passport OAuth2 configuration
    |--------------------------------------------------------------------------
    |
    | Token lifetimes and optional scope definitions. Scopes are opt-in:
    | leave empty to keep Passport's defaults (a single implicit scope).
    | When populated, StarterKitServiceProvider calls Passport::tokensCan()
    | at boot and Passport::setDefaultScope() with the configured default.
    |
    */

    'passport' => [
        // Auth provider backing the auto-registered `api` guard. The provider
        // is only synthesised when the consumer app has not already defined an
        // `api` guard, so a custom guard is never overridden. Point this at the
        // auth provider whose model is your Passport `HasApiTokens` user (the
        // key must exist under `auth.providers`).
        'provider' => env('STARTER_KIT_PASSPORT_PROVIDER', 'users'),

        // Access tokens are short-lived by default — leaked bearer tokens
        // should expire before they are abused. Prefer refresh tokens for
        // session longevity, not long access-token TTLs.
        'access_token_minutes' => (int) env('PASSPORT_TOKEN_MINUTES', 60),
        'refresh_token_days' => (int) env('PASSPORT_REFRESH_TOKEN_DAYS', 14),
        'personal_token_days' => (int) env('PASSPORT_PERSONAL_TOKEN_DAYS', 30),

        // Legacy keys kept for backward compatibility. If `access_token_days`
        // is set (non-null) it overrides `access_token_minutes`.
        'access_token_days' => env('PASSPORT_TOKEN_DAYS'),
        'personal_token_months' => env('PASSPORT_PERSONAL_TOKEN_MONTHS'),

        // Default catalog of scopes. Enforcement is opt-in: attach
        // `middleware('scope:users.read')` (or similar) to API routes you
        // want to restrict. Leaving `default_scopes` empty preserves
        // Passport's implicit `*` scope so existing clients keep working.
        'scopes' => [
            'users.read' => 'Read user data',
            'users.write' => 'Create and modify users',
            'files.read' => 'Read files and folders',
            'files.write' => 'Create, move, and delete files',
            'admin' => 'Full administrative access',
        ],

        'default_scopes' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security headers (SecurityHeaders middleware)
    |--------------------------------------------------------------------------
    |
    | Extra origins appended to the img-src / media-src / connect-src CSP
    | directives, on top of the origins derived automatically from the
    | media-library disk and the public disk (a disk `url`, an s3 `endpoint`,
    | or plain-AWS region/bucket). Use full origins, e.g.:
    |
    |   'csp_extra_origins' => ['https://cdn.example.com'],
    |
    */

    'security' => [
        'csp_extra_origins' => [],
    ],

];
