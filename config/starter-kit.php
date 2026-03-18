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

];
