<?php

/*
|--------------------------------------------------------------------------
| Auth feature gating is fail-closed (red-line regression)
|--------------------------------------------------------------------------
|
| Turning registration (or password reset) OFF in the admin settings screen has
| to actually close the endpoint. Two things were wrong and are guarded here:
|
|   ORDERING — config('fortify.features') used to be rebuilt in
|              SettingsServiceProvider::boot(). Fortify is a discovered package
|              provider, so ITS boot() — where /register, POST /forgot-password
|              and POST /reset-password are bound behind Features::enabled() —
|              runs BEFORE any app provider's boot(). The gate therefore
|              arrived after the routes were already registered and the
|              endpoint stayed wide open. It now runs from the booting()
|              bridge installed in register().
|
|   DEPTH    — config is computed once per process and can drift from the DB
|              (cached config, a settings flip after boot, a consumer app that
|              dropped SettingsServiceProvider). RegisterUserAction,
|              CreateNewUser, ResetUserPassword and the `password.email` route
|              guard each re-read the setting and fail closed on their own.
|
| The route-build assertion below is not a proxy: it loads Fortify's REAL route
| file (vendor/laravel/fortify/routes/routes.php) into a throwaway Router under
| the gated config, which is exactly what Fortify's boot() does.
|
| Not covered — and deliberately so: the successful end of the register path.
| It reaches User::create() + assignRole() + Passport createToken(), i.e. the
| consumer's uuid users table, Spatie permission tables and Passport keys, none
| of which the package suite stands up. The "enabled" direction is asserted at
| every gate instead: the route IS built, and the action does NOT throw the
| gate exception.
|
*/

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\PasswordValidationRules;
use App\Domain\Auth\Actions\RegisterUserAction;
use App\Domain\Auth\DTOs\RegisterDTO;
use App\Http\Middleware\EnsurePasswordNotExpired;
use App\Models\Setting;
use App\Providers\SettingsServiceProvider;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Lvntr\StarterKit\Domain\Setting\SettingService;
use Lvntr\StarterKit\Exceptions\ApiException;
use Lvntr\StarterKit\Tests\Stubs\TestSetting;
use Symfony\Component\HttpKernel\Exception\HttpException;

// SettingService writes through the App\Models\Setting FQCN; the package suite
// does not autoload App\, so the stub is aliased in (same guarded pattern as
// AuthSettingsTest / SensitiveKeysFallbackTest — whichever file loads first
// wins, the guard keeps the second one from redeclaring).
if (! class_exists(Setting::class)) {
    class_alias(TestSetting::class, Setting::class);
}

$stubs = dirname(__DIR__, 3).'/stubs';

if (! class_exists(SettingsServiceProvider::class)) {
    require_once $stubs.'/app/Providers/SettingsServiceProvider.php';
}

if (! class_exists(RegisterDTO::class)) {
    require_once $stubs.'/app/Domain/Auth/DTOs/RegisterDTO.php';
}

if (! class_exists(RegisterUserAction::class)) {
    require_once $stubs.'/app/Domain/Auth/Actions/RegisterUserAction.php';
}

if (! trait_exists(PasswordValidationRules::class)) {
    require_once $stubs.'/app/Actions/Fortify/PasswordValidationRules.php';
}

if (! class_exists(CreateNewUser::class)) {
    require_once $stubs.'/app/Actions/Fortify/CreateNewUser.php';
}

/**
 * Reproduce the real boot sequence for this provider: run register(), then
 * fire ONLY the booting callbacks register() installed.
 *
 * Application::booting() never fires late — it just appends to the pending
 * list — so the callbacks are pulled off the app by reflection and invoked
 * here, which is precisely what Laravel does after every register() and before
 * any boot(), i.e. before Fortify binds its routes.
 *
 * This is what makes the assertions ordering-sensitive rather than
 * value-sensitive: move the feature gate into boot() and nothing this helper
 * runs will touch config('fortify.features').
 */
function runAuthBootingBridge(): void
{
    $app = app();
    $pending = new ReflectionProperty($app, 'bootingCallbacks');

    $before = count((array) $pending->getValue($app));

    new SettingsServiceProvider($app)->register();

    $installed = array_slice((array) $pending->getValue($app), $before);

    expect($installed)->not->toBeEmpty(); // register() must install the bridge

    foreach ($installed as $callback) {
        $callback($app);
    }
}

/**
 * Load Fortify's real route file into a throwaway Router and return the route
 * names it produced under the config as it currently stands.
 *
 * @return list<string>
 */
function fortifyRouteNames(): array
{
    $previous = app()->bound('router') ? app('router') : null;

    $router = new Router(app('events'), app());

    app()->instance('router', $router);
    Facade::clearResolvedInstance('router');

    try {
        require dirname(__DIR__, 3).'/vendor/laravel/fortify/routes/routes.php';

        $names = [];

        foreach ($router->getRoutes() as $route) {
            if ($route->getName() !== null) {
                $names[] = $route->getName();
            }
        }

        return $names;
    } finally {
        if ($previous !== null) {
            app()->instance('router', $previous);
        }

        Facade::clearResolvedInstance('router');
    }
}

/**
 * Evaluate the middleware stack the stub's admin panel group mounts under,
 * against config as it currently stands.
 *
 * Why the expression is lifted out of the file instead of the file being
 * required: stubs/routes/web.php walks routes/web/*.php and resolves App\
 * controllers the package suite does not autoload. The stack expression itself
 * is self-contained, so it is captured and executed — the assertion then runs
 * the REAL code the stub ships, not a re-implementation of it.
 *
 * Reverting the stub to the old hardcoded `['auth', 'verified',
 * EnsurePasswordNotExpired::class]` does NOT quietly disarm this: that literal
 * is matched by the second pattern, evaluates fine, and always yields
 * 'verified' — which is exactly what the disabled-case assertion forbids.
 *
 * @return list<string>
 */
function adminPanelMiddleware(): array
{
    $source = (string) file_get_contents(dirname(__DIR__, 3).'/stubs/routes/web.php');

    // Current form: `$panelMiddleware = <expr>;` on its own statement.
    // Legacy/reverted form: the array passed inline to Route::middleware(...).
    if (! preg_match('/\$panelMiddleware = (.*?);\n/s', $source, $matched)
        && ! preg_match('/Route::middleware\((\[.*?\])\)->group\(/s', $source, $matched)) {
        throw new RuntimeException(
            'Could not locate the admin panel middleware stack in stubs/routes/web.php — '
            .'the guard below cannot run, treat this as a failure, not as a parser nit.'
        );
    }

    // eval() inherits the namespace but NOT the `use` imports, so every short
    // class name in the snippet is expanded from the stub's own import list
    // before it runs. `X::class` on a name literal is resolved at compile time
    // and never autoloads, so App\Http\Middleware\EnsurePasswordNotExpired
    // yields its string here without the class existing in the package suite.
    $expression = $matched[1];

    preg_match_all('/^use ([^\s;]+);$/m', $source, $imports);

    foreach ($imports[1] as $fqcn) {
        $short = substr($fqcn, (int) strrpos($fqcn, '\\') + 1);

        $expression = (string) preg_replace_callback(
            '/(?<![\\\\\w])'.preg_quote($short, '/').'::/',
            static fn (): string => '\\'.$fqcn.'::',
            $expression
        );
    }

    /** @var list<string> $stack */
    $stack = eval("return {$expression};");

    return array_values($stack);
}

/** @return array<string, mixed> */
function registerPayload(): array
{
    return [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.test',
        'password' => 'SYNTHETIC-PLACEHOLDER-Passw0rd!',
    ];
}

beforeEach(function (): void {
    // The gate only ever ADDS to whatever the consumer configured, so start
    // from Fortify's own shipped feature list.
    config(['fortify.features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication(),
    ]]);
});

// ──────────────────────────────────────────────────────────────────────────────
// 1. The booting bridge gates the features array
// ──────────────────────────────────────────────────────────────────────────────

it('removes registration from fortify.features when the setting is disabled', function (): void {
    app(SettingService::class)->setValue('auth.registration', '0');

    runAuthBootingBridge();

    expect(config('fortify.features'))->not->toContain(Features::registration())
        ->and(Features::enabled(Features::registration()))->toBeFalse();
});

it('keeps registration in fortify.features when the setting is enabled or absent', function (): void {
    // Absent key → enabled (matches SettingsDefaultsQuery::auth()).
    runAuthBootingBridge();
    expect(Features::enabled(Features::registration()))->toBeTrue();

    app(SettingService::class)->setValue('auth.registration', '1');
    runAuthBootingBridge();
    expect(Features::enabled(Features::registration()))->toBeTrue();
});

it('removes password reset from fortify.features when the setting is disabled', function (): void {
    app(SettingService::class)->setValue('auth.password_reset', '0');

    runAuthBootingBridge();

    expect(config('fortify.features'))->not->toContain(Features::resetPasswords())
        ->and(Features::enabled(Features::resetPasswords()))->toBeFalse();
});

it('keeps password reset in fortify.features when the setting is enabled or absent', function (): void {
    runAuthBootingBridge();
    expect(Features::enabled(Features::resetPasswords()))->toBeTrue();

    app(SettingService::class)->setValue('auth.password_reset', '1');
    runAuthBootingBridge();
    expect(Features::enabled(Features::resetPasswords()))->toBeTrue();
});

it('carries over a feature the auth settings screen does not govern', function (): void {
    // The gate closes what the admin turned off; it must not silently strip a
    // feature it has no toggle for (Fortify ships passkeys enabled).
    config(['fortify.features' => [...config('fortify.features'), Features::passkeys()]]);

    app(SettingService::class)->setValue('auth.registration', '0');

    runAuthBootingBridge();

    expect(config('fortify.features'))->toContain(Features::passkeys())
        ->and(config('fortify.features'))->not->toContain(Features::registration());
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. Route-build time — Fortify's own route file, under the gated config
// ──────────────────────────────────────────────────────────────────────────────

it('never builds the register routes once the booting bridge has run with registration off', function (): void {
    app(SettingService::class)->setValue('auth.registration', '0');

    runAuthBootingBridge();

    $names = fortifyRouteNames();

    expect($names)->not->toContain('register')
        ->and($names)->not->toContain('register.store')
        // Sanity: the file really did register routes — an empty result would
        // make the two assertions above vacuous.
        ->and($names)->toContain('login.store');
});

it('builds the register routes when registration is on', function (): void {
    app(SettingService::class)->setValue('auth.registration', '1');

    runAuthBootingBridge();

    expect(fortifyRouteNames())->toContain('register.store');
});

it('never builds the forgot-password / reset-password routes with password reset off', function (): void {
    app(SettingService::class)->setValue('auth.password_reset', '0');

    runAuthBootingBridge();

    $names = fortifyRouteNames();

    expect($names)->not->toContain('password.email')
        ->and($names)->not->toContain('password.update')
        ->and($names)->toContain('login.store');
});

it('builds the forgot-password / reset-password routes when password reset is on', function (): void {
    app(SettingService::class)->setValue('auth.password_reset', '1');

    runAuthBootingBridge();

    $names = fortifyRouteNames();

    expect($names)->toContain('password.email')
        ->and($names)->toContain('password.update');
});

it('applies the gate from register(), not from boot()', function (): void {
    // The ordering fix itself. boot() runs after Fortify has already bound its
    // routes, so a features array rebuilt there is worthless — the stub must
    // not be relying on it.
    $source = file_get_contents(dirname(__DIR__, 3).'/stubs/app/Providers/SettingsServiceProvider.php');

    expect($source)->toContain('$this->app->booting(')
        ->toContain('$this->gateFortifyFeatures($auth);');

    $bootBody = substr((string) $source, (int) strpos((string) $source, 'public function boot()'));

    expect($bootBody)->not->toContain("'fortify.features'");
});

// ──────────────────────────────────────────────────────────────────────────────
// 2b. Email verification — the gate must not leave a dangling route reference
//
// `auth.email_verification` is seeded '0' on every fresh install
// (stubs/database/seeders/_03_SettingSeeder.php), so on a default install the
// booting bridge now really does unbind verification.notice / .verify / .send —
// which the boot()-time rebuild never managed to do. Anything that still points
// at those route NAMES turns into a RouteNotFoundException, i.e. an HTTP 500.
// The `verified` middleware alias (Illuminate\Auth\Middleware\
// EnsureEmailIsVerified) is precisely such a pointer: its deny branch calls
// URL::route('verification.notice'). It therefore cannot be a hardcoded entry
// on the admin panel group that wraps the entire panel.
// ──────────────────────────────────────────────────────────────────────────────

it('unbinds the verification routes when email verification is disabled', function (): void {
    app(SettingService::class)->setValue('auth.email_verification', '0');

    runAuthBootingBridge();

    $names = fortifyRouteNames();

    expect($names)->not->toContain('verification.notice')
        ->and($names)->not->toContain('verification.verify')
        ->and($names)->not->toContain('verification.send')
        ->and($names)->toContain('login.store');
});

it('builds the verification routes when email verification is enabled', function (): void {
    app(SettingService::class)->setValue('auth.email_verification', '1');

    runAuthBootingBridge();

    expect(fortifyRouteNames())->toContain('verification.notice');
});

it('drops the verified middleware from the admin panel group when verification is off', function (): void {
    // The blocker itself. With 'verified' hardcoded, every /admin/* request of
    // an unverified user resolves route('verification.notice') — a route the
    // gate just removed — and the whole panel answers 500 on a default install.
    app(SettingService::class)->setValue('auth.email_verification', '0');

    runAuthBootingBridge();

    expect(Features::enabled(Features::emailVerification()))->toBeFalse()
        ->and(adminPanelMiddleware())->not->toContain('verified');
});

it('keeps the verified middleware on the admin panel group when verification is on', function (): void {
    // The other half: the conditional must not silently drop the check when the
    // admin actually wants email verification enforced.
    app(SettingService::class)->setValue('auth.email_verification', '1');

    runAuthBootingBridge();

    expect(adminPanelMiddleware())->toContain('verified');
});

it('never drops auth or the password-expiry guard from the admin panel group', function (): void {
    // Only the verification entry is conditional. If array_filter ever starts
    // eating the unconditional ones, the panel loses authentication itself.
    foreach (['0', '1'] as $emailVerification) {
        app(SettingService::class)->setValue('auth.email_verification', $emailVerification);

        runAuthBootingBridge();

        $stack = adminPanelMiddleware();

        expect($stack)->toContain('auth')
            ->and($stack)->toContain(EnsurePasswordNotExpired::class)
            // array_filter must not leave a null hole in the stack either.
            ->and($stack)->each->toBeString();
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. Action-level guard — holds even when the config layer is permissive
// ──────────────────────────────────────────────────────────────────────────────

it('RegisterUserAction refuses with 403 while config(fortify.features) still allows registration', function (): void {
    // Simulates the drift the config gate cannot cover: cached/stale config
    // that still lists registration, DB says off.
    config(['fortify.features' => [Features::registration()]]);
    expect(Features::enabled(Features::registration()))->toBeTrue();

    app(SettingService::class)->setValue('auth.registration', '0');

    try {
        app(RegisterUserAction::class)->execute(RegisterDTO::fromArray(registerPayload()));
        $thrown = null;
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(ApiException::class)
        ->and($thrown->getStatusCode())->toBe(403);
});

it('RegisterUserAction lets the request through the gate when registration is enabled or absent', function (): void {
    // The row can never be written here (App\Models\User needs the consumer's
    // uuid users table, Spatie roles and Passport). What is asserted is that
    // whatever stops the action is NOT the registration gate.
    foreach ([null, '1'] as $stored) {
        if ($stored !== null) {
            app(SettingService::class)->setValue('auth.registration', $stored);
        }

        try {
            app(RegisterUserAction::class)->execute(RegisterDTO::fromArray(registerPayload()));
            $thrown = null;
        } catch (Throwable $e) {
            $thrown = $e;
        }

        expect($thrown)->not->toBeInstanceOf(ApiException::class);
    }
});

it('CreateNewUser aborts with 403 when registration is disabled', function (): void {
    config(['fortify.features' => [Features::registration()]]);
    app(SettingService::class)->setValue('auth.registration', '0');

    try {
        new CreateNewUser()->create(registerPayload());
        $thrown = null;
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(HttpException::class)
        ->and($thrown->getStatusCode())->toBe(403);
});

it('CreateNewUser does not abort when registration is enabled', function (): void {
    app(SettingService::class)->setValue('auth.registration', '1');

    try {
        new CreateNewUser()->create(registerPayload());
        $thrown = null;
    } catch (Throwable $e) {
        $thrown = $e;
    }

    // Validation, the missing App\ model, anything — but never the 403 gate.
    expect($thrown)->not->toBeInstanceOf(HttpException::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. Password-reset guards that cannot be driven without the consumer app
// ──────────────────────────────────────────────────────────────────────────────

it('ResetUserPassword fails closed on the auth.password_reset setting', function (): void {
    // reset(User $user, ...) type-hints App\Models\User, so the method cannot
    // be invoked in the package suite at all. The guard itself is pinned here.
    $source = file_get_contents(dirname(__DIR__, 3).'/stubs/app/Actions/Fortify/ResetUserPassword.php');

    expect($source)->toContain("abort_unless((string) Setting::getValue('auth.password_reset', '1') === '1', 403);");

    // …and it must be the FIRST statement of reset(), before the password
    // column can be touched.
    $body = substr((string) $source, (int) strpos((string) $source, 'public function reset('));

    expect(strpos($body, 'abort_unless'))->toBeLessThan((int) strpos($body, 'forceFill'));
});

it('POST forgot-password is gated on the route name, not on a path that a custom prefix would move', function (): void {
    $source = file_get_contents(dirname(__DIR__, 3).'/stubs/app/Providers/FortifyServiceProvider.php');

    expect($source)->toContain("\$event->route->getName() === 'password.email'")
        ->toContain("abort_unless((string) Setting::getValue('auth.password_reset', '1') === '1', 403);");
});
