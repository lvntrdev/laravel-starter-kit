<?php

use App\Http\Controllers\Admin\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('activity-logs')
    ->name('activity-logs.')
    ->controller(ActivityLogController::class)
    ->group(function () {
        Route::get('dt', 'dtApi')->name('dtApi');
        Route::get('{activity}', 'show')->name('show');
        Route::get('/', 'index')->name('index');
    });
