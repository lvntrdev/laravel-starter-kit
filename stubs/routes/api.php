<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes automatically receive the /api prefix.
| Passport middleware: auth:api
|
*/

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function () {
    // API route files are always loaded behind /api/v1 + throttle:api.
    // Some routes are public, some require auth:api, and some require both auth:api and check.permission.
    $excludedFiles = [];
    $publicRouteFiles = ['public-api.php'];
    $authenticatedRouteFiles = ['auth-route.php', 'service-route.php'];
    $permissionProtectedRouteFiles = [];

    foreach (File::files(__DIR__.'/api') as $file) {
        if (in_array($file->getFilename(), $excludedFiles)) {
            continue;
        }

        if (! str_ends_with($file->getFilename(), '.php')) {
            continue;
        }

        if (in_array($file->getFilename(), $publicRouteFiles)) {
            require $file->getPathname();

            continue;
        }

        if (in_array($file->getFilename(), $authenticatedRouteFiles)) {
            Route::middleware('auth:api')->group(function () use ($file) {
                require $file->getPathname();
            });

            continue;
        }

        $permissionProtectedRouteFiles[] = $file->getPathname();
    }

    Route::middleware(['auth:api', 'check.permission'])->group(function () use ($permissionProtectedRouteFiles) {
        foreach ($permissionProtectedRouteFiles as $permissionProtectedRouteFile) {
            require $permissionProtectedRouteFile;
        }
    });
});
