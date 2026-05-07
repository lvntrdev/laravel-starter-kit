<?php

use App\Http\Controllers\Admin\SystemHealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| System Health Routes
|--------------------------------------------------------------------------
|
| Bu rotalar yalnızca system_admin rolüne açıktır. Settings sayfasındaki
| "Sistem Sağlığı" tab'ından kullanılır; menüde bağımsız bir sayfa olarak
| görünmez.
|
| Role: system_admin (route-level guard)
| Guard: web (admin paneli — session tabanlı kimlik doğrulama)
|
*/

Route::prefix('system-health')
    ->name('system-health.')
    ->controller(SystemHealthController::class)
    ->middleware('role:system_admin')
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/run', 'run')->name('run');
    });
