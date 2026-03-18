<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->controller(AuthController::class)->group(function () {
    Route::post('register', 'register')->name('register')->middleware('throttle:5,1');
    Route::post('login', 'login')->name('login')->middleware('throttle:5,1');
});
