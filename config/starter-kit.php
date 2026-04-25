<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Package Migrations
    |--------------------------------------------------------------------------
    |
    | When true, the package will load its own migrations.
    | When false (default), migrations are published to the app.
    |
    */

    'run_migrations' => false,

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
