<?php

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
    | Set `allow_unmapped` to true (env: STARTER_KIT_ALLOW_UNMAPPED_PERMISSIONS)
    | to restore the legacy behavior where ANY non-production environment lets
    | the unmapped permission through with a warning. Production always denies
    | regardless of this flag; local always allows.
    |
    */

    'permissions' => [
        'allow_unmapped' => (bool) env('STARTER_KIT_ALLOW_UNMAPPED_PERMISSIONS', false),
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

];
