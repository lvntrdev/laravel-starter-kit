<?php

use App\Http\Controllers\Admin\RoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('roles')->name('roles.')->controller(RoleController::class)->group(function () {
    Route::get('dt', 'dtApi')->name('dtApi');
    Route::get('{role}/data', 'data')->name('data');
    Route::post('sync-permissions', 'syncPermissions')->name('syncPermissions');
});

Route::resource('roles', RoleController::class);
