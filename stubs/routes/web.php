<?php

use App\Http\Middleware\EnsurePasswordNotExpired;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Lvntr\StarterKit\Http\Controllers\Api\MediaUploadController;

Route::redirect('/', '/dashboard');

$excludedFiles = [];
$publicRouteFiles = ['public-route.php'];

foreach (File::files(__DIR__.'/web') as $file) {
    if (in_array($file->getFilename(), $excludedFiles)) {
        continue;
    }

    if (! str_ends_with($file->getFilename(), '-route.php')) {
        continue;
    }

    if (in_array($file->getFilename(), $publicRouteFiles)) {
        require $file->getPathname();
    }
}

// EnsurePasswordNotExpired guards only this authenticated panel group —
// Fortify's endpoints (login, 2FA challenge, PUT /user/password, password
// confirm/reset) register outside it, and the middleware additionally
// exempts the profile + logout routes by name, so an expired user can
// always reach the password form (no redirect loop).
//
// `verified` is CONDITIONAL, and it has to be. The alias resolves to
// Illuminate\Auth\Middleware\EnsureEmailIsVerified, whose deny branch calls
// URL::route('verification.notice') — a route Fortify only registers while
// Features::emailVerification() is in config('fortify.features'). Since
// SettingsServiceProvider gates that array from a booting() callback, the
// admin toggle `auth.email_verification` (seeded '0' on a fresh install) now
// really does unbind verification.notice/verify/send. Hardcoding 'verified'
// here would leave one RouteNotFoundException between the panel and a 500 the
// moment anything makes the middleware take its deny branch (a consumer that
// drops the User::hasVerifiedEmail() override, a stale route:cache, a custom
// user model). Keeping the middleware in lockstep with the feature that owns
// its redirect target is the only stack that cannot dereference a route that
// does not exist.
//
// Ordering is safe: this file is loaded from RouteServiceProvider's booted()
// callback, i.e. after every provider boot() and therefore long after the
// SettingsServiceProvider booting() bridge rewrote config('fortify.features').
$panelMiddleware = array_values(array_filter([
    'auth',
    Features::enabled(Features::emailVerification()) ? 'verified' : null,
    EnsurePasswordNotExpired::class,
]));

Route::middleware($panelMiddleware)->group(function () use ($excludedFiles, $publicRouteFiles) {
    Route::delete('/media/{media}', [MediaUploadController::class, 'destroy'])->name('media.destroy');

    // Web route files inside this group are authenticated.
    // Some skip permission checks, but they are still not public.
    // 'components-route.php' is legacy: the showcase moved into the package
    // (/sk-components, vendor-mounted). The entry stays so older projects that
    // still ship the published file keep their role-gated routes out of the
    // dynamic permission middleware (which would deny the unmapped
    // "components.*" names in production).
    $routesWithoutPermissionMiddleware = ['profile-route.php', 'service-route.php', 'file-manager-route.php', 'log-route.php', 'components-route.php'];
    $permissionProtectedRouteFiles = [];

    foreach (File::files(__DIR__.'/web') as $file) {
        if (in_array($file->getFilename(), $excludedFiles)) {
            continue;
        }

        if (! str_ends_with($file->getFilename(), '-route.php')) {
            continue;
        }

        if (in_array($file->getFilename(), $publicRouteFiles)) {
            continue;
        }

        if (in_array($file->getFilename(), $routesWithoutPermissionMiddleware)) {
            require $file->getPathname();

            continue;
        }

        $permissionProtectedRouteFiles[] = $file->getPathname();
    }

    Route::middleware('check.permission')->group(function () use ($permissionProtectedRouteFiles) {
        foreach ($permissionProtectedRouteFiles as $permissionProtectedRouteFile) {
            require $permissionProtectedRouteFile;
        }
    });
});
