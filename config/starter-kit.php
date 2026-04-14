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
    */

    'datatable' => [
        'default_per_page' => (int) env('STARTER_KIT_DATATABLE_PER_PAGE', 10),
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
        'access_token_days' => (int) env('PASSPORT_TOKEN_DAYS', 15),
        'refresh_token_days' => (int) env('PASSPORT_REFRESH_TOKEN_DAYS', 30),
        'personal_token_months' => (int) env('PASSPORT_PERSONAL_TOKEN_MONTHS', 6),

        // ['read' => 'Read access to user data', 'write' => 'Modify user data']
        'scopes' => [],

        // ['read'] — requested scopes when the client sends none
        'default_scopes' => [],
    ],

];
