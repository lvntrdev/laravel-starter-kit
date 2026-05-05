<?php

/*
|--------------------------------------------------------------------------
| FileManager Route Names — v13.4.x Snapshot (immutable)
|--------------------------------------------------------------------------
|
| This fixture pins the public route name list shipped with v13.4.x. The
| package may add new names in future releases, but every entry below
| MUST keep resolving via Route::has() so frontend Wayfinder typed routes
| and existing axios calls do not break on `composer update`.
|
| Source of truth: `php artisan route:list --path=file-manager --json`
| against the v13.4.9 starter-kit-app at the time of the v13.5.0 cutover.
|
| 19 names — sorted by group for review-friendly diffs only; ordering is
| not load-bearing.
|
*/

return [
    // Listing endpoints
    'file-manager.contents',
    'file-manager.tree',

    // Favourites
    'file-manager.favorites.add',
    'file-manager.favorites.remove',
    'file-manager.favorites.contents',

    // Files (CRUD on Spatie media)
    'file-manager.files.upload',
    'file-manager.files.rename',
    'file-manager.files.copy',
    'file-manager.files.destroy',
    'file-manager.files.download',

    // Folders (CRUD on FileFolder)
    'file-manager.folders.store',
    'file-manager.folders.rename',
    'file-manager.folders.destroy',

    // Bulk + move + restore + permanent delete
    'file-manager.items.bulkDelete',
    'file-manager.items.move',
    'file-manager.items.permanent',
    'file-manager.items.restore',

    // Trash
    'file-manager.trash.contents',
    'file-manager.trash.empty',
];
