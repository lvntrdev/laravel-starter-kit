<?php

use App\Http\Controllers\Admin\ApiTokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Token (Personal Access Token) Routes
|--------------------------------------------------------------------------
|
| Bu rotalar yalnızca system_admin rolüne açıktır. Settings sayfasındaki
| "API Tokenleri" tab'ından kullanılır; menüde bağımsız bir sayfa olarak
| görünmez.
|
| Permission: api-tokens.read, api-tokens.create, api-tokens.delete
| Role: system_admin (route-level guard)
| Guard: web (admin paneli — session tabanlı kimlik doğrulama)
|
| UYARI: store() response'unda access_token plaintext döner.
| Bu rota API guard'da değil web guard'da çalışır — CSRF koruması aktif.
|
| Rate limiting: store (token üretme) throttle:10,1; destroy throttle:30,1.
|
*/

Route::prefix('api-tokens')
    ->name('api-tokens.')
    ->controller(ApiTokenController::class)
    ->middleware('role:system_admin')
    ->group(function () {
        Route::get('dt', 'dtApi')->name('dtApi');
        Route::get('/', 'index')->name('index');

        // Token üretme: düşük limit (brute-force koruması)
        Route::post('/', 'store')->name('store')->middleware('throttle:10,1');

        // Revoke: daha yüksek limit (toplu iptal senaryosu için)
        Route::delete('{token}', 'destroy')->name('destroy')->middleware('throttle:30,1');
    });
