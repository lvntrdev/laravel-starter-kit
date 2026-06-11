<?php

namespace App\Http\Middleware;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force a password rotation when the admin-configured expiry has lapsed.
 *
 * Reads `starter-kit.password_policy.expiry_days` (bridged from the Security
 * settings by SettingsServiceProvider) and the user's `password_changed_at`
 * stamp (maintained by the User model's saving hook). Expired users are
 * redirected to the dedicated, guest-style password-expired screen
 * (Auth/PasswordExpired.vue, route `password.expired`).
 *
 * Loop safety (two independent layers):
 *   1. Registration scope — the middleware is attached to the authenticated
 *      panel route group in routes/web.php. Fortify's own endpoints (login,
 *      logout fallback, two-factor challenge, PUT /user/password, password
 *      confirmation) are registered outside that group, so the routes a
 *      locked-out user needs are structurally unreachable by this guard.
 *   2. EXEMPT_ROUTES — the in-group escape hatch (the password-expired page
 *      itself + logout) and a defensive copy of the Fortify route names, so no
 *      redirect loop can form even if a consumer attaches this middleware
 *      globally.
 */
class EnsurePasswordNotExpired
{
    /**
     * Route names a password-expired user must always be able to reach.
     *
     * @var list<string>
     */
    private const array EXEMPT_ROUTES = [
        // In-group escape hatch: the dedicated password-expired page (the
        // redirect target, which hosts the password form) and logout.
        'password.expired',
        'logout',
        // Defensive exemptions (normally outside the guarded group):
        'login',
        'two-factor.login',
        'user-password.update',
        'password.confirm',
        'password.confirmation',
        'password.request',
        'password.email',
        'password.reset',
        'password.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::isExpired($request->user())) {
            return $next($request);
        }

        if ($request->routeIs(...self::EXEMPT_ROUTES)) {
            return $next($request);
        }

        return redirect()->route('password.expired');
    }

    /**
     * Whether the given user's password has lapsed the admin-configured expiry.
     *
     * Shared by the middleware (to gate the panel) and the `password.expired`
     * route handler (to bounce a user whose password is already current back to
     * the dashboard, so the screen cannot linger after a successful change).
     */
    public static function isExpired(mixed $user): bool
    {
        $days = (int) config('starter-kit.password_policy.expiry_days', 0);

        // 0 = unlimited (the backward-compatible default for existing installs).
        if ($days <= 0 || $user === null) {
            return false;
        }

        $changedAt = $user->password_changed_at ?? null;

        // Null = a row the shipped migration backfill has not touched (the
        // migration stamps existing rows with now()). Exempt instead of
        // hard-locking the account out of the panel.
        if ($changedAt === null) {
            return false;
        }

        if (! $changedAt instanceof CarbonInterface) {
            $changedAt = Date::parse($changedAt);
        }

        return ! $changedAt->copy()->addDays($days)->isFuture();
    }
}
