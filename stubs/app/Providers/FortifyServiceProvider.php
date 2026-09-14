<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Actions\Fortify\ValidateTurnstile;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\EnsureLoginIsNotThrottled;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // ── Redirects ────────────────────────────────────────────────
        // Fortify's own default post-login target is `config('fortify.home')` = `/home`,
        // which this kit never registers (the real landing page is `/dashboard`) —
        // without this, every fresh install's login redirects to a 404. Fortify::redirects()
        // only *reads* this config (it isn't a setter), so the override goes here rather
        // than through that helper.
        config(['fortify.home' => '/dashboard']);

        // ── Inertia View Bindings ────────────────────────────────────
        Fortify::loginView(fn () => Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]));

        Fortify::registerView(function () {
            abort_unless(Features::enabled(Features::registration()), 404);

            return Inertia::render('Auth/Register');
        });

        Fortify::requestPasswordResetLinkView(function () {
            abort_unless(Features::enabled(Features::resetPasswords()), 404);

            return Inertia::render('Auth/ForgotPassword', [
                'status' => session('status'),
            ]);
        });

        Fortify::resetPasswordView(function (Request $request) {
            abort_unless(Features::enabled(Features::resetPasswords()), 404);

            return Inertia::render('Auth/ResetPassword', [
                'token' => $request->route('token'),
                'email' => $request->query('email'),
            ]);
        });

        Fortify::verifyEmailView(function () {
            abort_unless(Features::enabled(Features::emailVerification()), 404);

            return Inertia::render('Auth/VerifyEmail', [
                'status' => session('status'),
            ]);
        });

        Fortify::twoFactorChallengeView(function () {
            abort_unless(Features::enabled(Features::twoFactorAuthentication()), 404);

            return Inertia::render('Auth/TwoFactorChallenge');
        });

        Fortify::confirmPasswordView(fn () => Inertia::render('Auth/ConfirmPassword'));

        // ── authenticateUsing: inactive user block ───────────────────
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->input(Fortify::username()))->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return null;
            }

            if ($user->status !== 'active') {
                throw ValidationException::withMessages([
                    Fortify::username() => [__('sk-auth.inactive')],
                ]);
            }

            return $user;
        });

        // ── Login pipeline with Turnstile ────────────────────────────
        Fortify::authenticateThrough(fn () => array_filter([
            config('fortify.limiters.login') ? EnsureLoginIsNotThrottled::class : null,
            ValidateTurnstile::class,
            Features::enabled(Features::twoFactorAuthentication()) ? RedirectIfTwoFactorAuthenticatable::class : null,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]));

        // ── anonymous auth POSTs → reset gate + turnstile + throttle ──
        Route::matched(function ($event) {
            $request = $event->request;

            // Fail-closed twin of ResetUserPassword's guard. POST
            // /forgot-password (route name `password.email`) is served by
            // Fortify's own controller, so there is no app-owned action to
            // guard — the `auth.password_reset` setting is enforced here
            // instead. SettingsServiceProvider's booting() bridge already
            // keeps Fortify from registering the route at all while the
            // feature is off; this closes the gap if that config ever drifts
            // from the DB. Matching on the route NAME rather than the path
            // keeps the guard correct under a custom `fortify.prefix`.
            if ($request->isMethod('POST') && $event->route->getName() === 'password.email') {
                abort_unless((string) Setting::getValue('auth.password_reset', '1') === '1', 403);
            }

            // Turnstile is enforced as MIDDLEWARE, not only through the
            // TurnstileRule entry inside CreateNewUser. Laravel skips a
            // non-implicit rule object when the attribute is absent or an empty
            // string (Validator::presentOrRuleIsImplicit), so a POST /register
            // that simply omits `cf_turnstile_response` walked straight past
            // that rule and created the account with no CAPTCHA at all. The
            // middleware reads the input itself and fails closed. Making the
            // rule `required` is NOT the fix — it would break registration
            // wherever Turnstile is off; ValidateTurnstile is a no-op in that
            // mode, so this attach is safe for every consumer.
            //
            // Matching on the route NAME rather than the path keeps the guard
            // correct under a custom `fortify.prefix` / `fortify.paths.*`.
            // Fortify names the register POST `register.store` (the GET view
            // route is `register`) across the whole supported ^1.35 range; the
            // bare `register` name stays in the list so a consumer that rebinds
            // the endpoint under the short name is still covered.
            if ($request->isMethod('POST')) {
                $routeName = $event->route->getName();

                if (in_array($routeName, ['register', 'register.store'], true)) {
                    // Throttle stays FIRST in the list so a flood cannot force
                    // one outbound Cloudflare verification (5s timeout) per
                    // request — the same ordering rule routes/api/public-api.php
                    // states. Fortify ships POST /register with `guest:` only,
                    // i.e. no rate limit whatsoever on anonymous account
                    // creation; 5/min per IP matches the ceiling the API
                    // register endpoint already carries.
                    $event->route->middleware(['throttle:5,1', 'turnstile']);
                } elseif ($routeName === 'password.email') {
                    $event->route->middleware(['turnstile']);
                }
            }
        });

        // ── Rate Limiters ────────────────────────────────────────────
        RateLimiter::for('login', function (Request $request) {
            $email = Str::transliterate(Str::lower((string) $request->input(Fortify::username())));
            $ip = (string) $request->ip();

            return [
                Limit::perMinute(10)->by('ip:'.$ip),
                Limit::perMinute(5)->by('email-ip:'.$email.'|'.$ip),
                Limit::perMinute(3)->by('email:'.$email),
            ];
        });

        // Relaxed floor used when an admin turns the login-throttle setting OFF
        // (auth.login_throttle = '0'). SettingsServiceProvider swaps the strict
        // 'login' limiter above for this one instead of nulling it, so no
        // settings combination can leave web login fully unlimited (brute-force
        // red line). It is a deliberate downgrade from 10/5/3 to a single
        // generous 30/min-per-IP cap: light enough to barely touch a legitimate
        // user who fat-fingers a password, tight enough to stop a machine
        // hammering thousands of guesses per minute. Kept intentionally looser
        // than the API login route's own 'api-login' limiter (registered in the
        // package provider — see the note below), which this setting never
        // relaxes.
        RateLimiter::for('login-relaxed', function (Request $request) {
            return Limit::perMinute(30)->by('ip:'.(string) $request->ip());
        });

        // NOTE: the 'api-login' limiter that POST /api/v1/auth/login uses is
        // NOT declared here. It ships from the package
        // (Lvntr\StarterKit\StarterKitServiceProvider::configureRateLimiting)
        // so that it can never go missing: this provider and
        // routes/api/public-api.php are both published stubs and `sk:update`
        // refreshes them independently, so a customised copy of this file plus
        // a refreshed route file would otherwise leave the route naming a
        // limiter nobody registered — a 500 on every API login. To change its
        // numbers, re-declare RateLimiter::for('api-login', ...) here; this
        // provider boots after the package's and wins.

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
