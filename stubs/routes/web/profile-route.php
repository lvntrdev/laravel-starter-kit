<?php

use App\Http\Controllers\ProfileController;
use App\Http\Middleware\EnsurePasswordNotExpired;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Dedicated, guest-style screen a password-expired user is forced onto by
// EnsurePasswordNotExpired. Exempt from that guard by name, so it is reachable
// while every other panel route redirects here. Once the password is current
// again (e.g. after a successful PUT /user/password) the user is bounced to the
// dashboard so the screen never lingers.
Route::get('password-expired', function (Request $request) {
    if (! EnsurePasswordNotExpired::isExpired($request->user())) {
        return redirect('/dashboard');
    }

    return Inertia::render('Auth/PasswordExpired');
})->name('password.expired');

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
