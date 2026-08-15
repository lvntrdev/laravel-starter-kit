<?php

use App\Http\Controllers\Api\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->controller(AuthController::class)->group(function () {
    // `turnstile` middleware is a no-op when Cloudflare Turnstile is
    // disabled in settings, so enabling it here is safe for existing
    // clients; once an operator turns Turnstile on it immediately covers
    // the API register and login endpoints in addition to the web flow.
    // `register` stays UNCONDITIONALLY registered even when the
    // `auth.registration` setting is off. RegisterUserAction enforces the
    // setting and answers 403; registering the route conditionally would turn
    // that into a 404 and break the client error-handling contract (a client
    // cannot tell "feature disabled" from "wrong URL / wrong API version").
    Route::post('register', 'register')
        ->name('register')
        ->middleware(['throttle:5,1', 'turnstile']);
    Route::post('login', 'login')
        ->name('login')
        ->middleware(['throttle:5,1', 'turnstile']);
    Route::post('two-factor-challenge', 'twoFactorChallenge')
        ->name('twoFactorChallenge')
        ->middleware('throttle:5,1');
});
