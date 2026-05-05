<?php

use App\Models\FileFavorite;
use App\Models\FileFolder;
use App\Models\GlobalFileBucket;

return [

    /*
    |--------------------------------------------------------------------------
    | FileManager Model Bindings
    |--------------------------------------------------------------------------
    |
    | These class names are used by the FileManager domain layer to interact
    | with Eloquent models. Override any entry in your application's published
    | config/file-manager.php to substitute a custom model.
    |
    | The `media` binding is intentionally absent — Spatie MediaLibrary resolves
    | its own Media model via its own configuration (config/media-library.php).
    |
    | Note: the `folder` override class MUST extend App\Models\FileFolder
    | because FileManagerController uses route model binding with the concrete
    | class type-hint. A full polymorphic swap (interface-based binding) is on
    | the v13.6 roadmap; for now consumers can only subclass FileFolder, not
    | replace it outright.
    |
    */

    'models' => [
        'folder' => FileFolder::class,
        'favorite' => FileFavorite::class,
        'global_bucket' => GlobalFileBucket::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | FileManager Upload Settings
    |--------------------------------------------------------------------------
    |
    | Runtime upload constraints. These defaults are used by UploadFileRequest
    | when no application-level override is present. Consumer apps typically
    | populate these via their SettingsServiceProvider by writing DB-stored
    | values into config('file-manager.settings.*') at boot time.
    |
    | max_size_mb        — Maximum allowed upload size in megabytes (default 10 MB).
    | accepted_mimes     — Array of accepted MIME types, or null to use built-in
    |                      defaults. Overridden by admin settings at runtime.
    | allow_video        — Whether video MIME types are accepted.
    | allow_audio        — Whether audio MIME types are accepted.
    | storage_quota_gb   — GB cinsinden, tüm Media kayıtları üzerinde tek bir
    |                      disk-genel kota (default 10 GB).
    |
    */

    'settings' => [
        'max_size_mb' => 10,
        'accepted_mimes' => null,
        'allow_video' => false,
        'allow_audio' => false,
        'storage_quota_gb' => 10,
    ],

];
