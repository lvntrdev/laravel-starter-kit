<?php

use Illuminate\Support\Facades\Route;
use Lvntr\StarterKit\Http\Controllers\ComponentShowcaseController;

/*
|--------------------------------------------------------------------------
| SK Component Showcase Vendor Routes
|--------------------------------------------------------------------------
|
| Mounted by StarterKitServiceProvider::registerRoutes() via the
| moduleRouteRegistry() descriptor. Like file-manager.php, this file is
| inert — the outer middleware tier (web + auth + verified) comes from the
| registry; only the role gate lives here.
|
| Developer-only UI showcase, gated by role (like the logs page) rather
| than a seeded permission, so it works without re-running
| sk:seed-permissions. The /sk-components prefix is deliberately distinct
| from the legacy stub's /components/* routes ("components.*" names) so a
| consumer still shipping the old published showcase never double-registers.
|
*/

Route::middleware('role:system_admin')
    ->get('sk-components', [ComponentShowcaseController::class, 'show'])
    ->name('sk-components.show');
