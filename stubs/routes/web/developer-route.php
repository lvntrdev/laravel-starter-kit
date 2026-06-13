<?php

use Illuminate\Support\Facades\Route;
use Lvntr\StarterKit\Http\Controllers\Admin\ApiRouteController;

Route::prefix('api-routes')
    ->name('api-routes.')
    ->controller(ApiRouteController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('regenerate-docs', 'regenerateDocs')->name('regenerateDocs');
        Route::post('postman-sync', 'syncPostman')->name('syncPostman');
        Route::post('apidog-sync', 'syncApidog')->name('syncApidog');
    });
