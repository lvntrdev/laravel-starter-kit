<?php

use App\Http\Controllers\Admin\ApiClientController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Client (OAuth) Routes
|--------------------------------------------------------------------------
|
| Bu rotalar yalnızca system_admin rolüne açıktır. Settings sayfasındaki
| "API İstemcileri" tab'ından kullanılır; menüde bağımsız bir sayfa olarak
| görünmez.
|
| Permission: api-clients.read, api-clients.create, api-clients.update, api-clients.delete
| Role: system_admin (route-level guard)
| Guard: web (admin paneli — session tabanlı kimlik doğrulama)
|
| Rate limiting: store/update/destroy işlemleri throttle:30,1 ile korunur.
|
*/

Route::prefix('api-clients')
    ->name('api-clients.')
    ->controller(ApiClientController::class)
    ->middleware('role:system_admin')
    ->group(function () {
        Route::get('dt', 'dtApi')->name('dtApi');
        Route::get('{client}/data', 'data')->name('data');

        // Yazma işlemleri rate limit'e tabi
        Route::middleware('throttle:30,1')->group(function () {
            Route::put('{client}', 'update')->name('update');
            Route::delete('{client}', 'destroy')->name('destroy');
            Route::post('/', 'store')->name('store');
        });
    });
