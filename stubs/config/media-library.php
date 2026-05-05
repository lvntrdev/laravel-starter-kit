<?php

use App\Models\Media;

/*
|--------------------------------------------------------------------------
| Spatie Media Library Configuration (Starter Kit override)
|--------------------------------------------------------------------------
|
| Minimal override published by `php artisan sk:install`. The starter kit
| ships its own `App\Models\Media` model that adds SoftDeletes support so
| the FileManager Trash feature works end-to-end. Without this override
| Spatie's default `Spatie\MediaLibrary\MediaCollections\Models\Media`
| would be used, the `deleted_at` column added by the starter kit
| migrations would be ignored at the model layer, and trash/restore
| operations would silently no-op.
|
| Laravel merges this file with Spatie's vendor defaults via
| `mergeConfigFrom()`, so any keys you don't list here continue to fall
| back to the package defaults. The `path_generator` key is set to NULL
| as a placeholder — InstallCommand fills it in with the starter kit
| path generator during the install flow (see injectMediaLibraryConfig).
|
*/

return [
    'media_model' => Media::class,

    'path_generator' => null,
];
