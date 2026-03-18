<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::controller(ProfileController::class)->group(function () {
    Route::get('profile', 'index')->name('profile');
    Route::post('logout', 'logout')->name('logout');

    Route::prefix('user/avatar')->name('user.avatar.')->group(function () {
        Route::post('/', 'uploadAvatar')->name('store');
        Route::delete('/', 'deleteAvatar')->name('destroy');
    });

    Route::prefix('browser-sessions')->name('browser-sessions.')->group(function () {
        Route::get('/', 'sessions')->name('index');
        Route::delete('/', 'destroySessions')->name('destroy');
    });
});
