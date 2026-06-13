<?php

use Illuminate\Support\Facades\Route;
use Lvntr\StarterKit\Http\Controllers\Admin\ActivityLogController;

Route::prefix('activity-logs')
    ->name('activity-logs.')
    ->controller(ActivityLogController::class)
    ->group(function () {
        Route::get('dt', 'dtApi')->name('dtApi');
        Route::get('{activity}', 'show')->name('show');
        Route::get('/', 'index')->name('index');
    });
