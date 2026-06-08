<?php

use App\Http\Controllers\Admin\ComponentShowcaseController;
use Illuminate\Support\Facades\Route;

// Developer-only UI showcase. Gated by role (like the logs page) rather than a
// seeded permission, so it works without re-running sk:seed-permissions. The
// file is listed in web.php's $routesWithoutPermissionMiddleware bucket so the
// auto resource-permission middleware does not apply.
Route::middleware('role:system_admin')
    ->prefix('components')
    ->name('components.')
    ->controller(ComponentShowcaseController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
    });
