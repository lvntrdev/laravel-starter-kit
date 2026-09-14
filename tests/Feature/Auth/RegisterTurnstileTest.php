<?php

/*
|--------------------------------------------------------------------------
| POST /register stays Turnstile-gated even when the field is never sent
|--------------------------------------------------------------------------
|
| THE FINDING. CreateNewUser validates `cf_turnstile_response` with a bare
| `[new TurnstileRule]` entry. Laravel skips a NON-IMPLICIT rule object when
| the attribute is absent or an empty string (Validator::presentOrRuleIsImplicit),
| so a client that simply omitted the field created an account with no CAPTCHA
| at all — while the admin settings screen reported Turnstile as ON. The rule
| cannot be promoted to `required`: it has to stay silent while Turnstile is
| disabled, otherwise every install without Cloudflare loses registration.
|
| THE FIX. The kit's `turnstile` middleware is attached to Fortify's register
| POST from the `Route::matched` block in stubs/app/Providers/
| FortifyServiceProvider.php, keyed on the route NAME (`register.store`, plus
| the bare `register`) so a custom `fortify.prefix` / `fortify.paths.register`
| cannot slip past it. `throttle:5,1` is attached AHEAD of it: Fortify ships
| POST /register with `guest:` only — no rate limit whatsoever on anonymous
| account creation — and turnstile costs one outbound Cloudflare call per
| request.
|
| WHAT THESE ASSERTIONS STAND ON.
|
|   WIRING    — Fortify's REAL route file is loaded into a throwaway Router
|               (same technique as tests/Feature/Settings/AuthFeatureGatingTest.php)
|               and a real RouteMatched event is fired at the register route,
|               which is exactly what Router::runRoute() does before it gathers
|               middleware. Nothing here re-implements the listener.
|
|   BEHAVIOUR — the probe route runs whatever class the kit's OWN alias map
|               (Lvntr\StarterKit\Bootstrap) binds to `turnstile`, so
|               repointing that alias at a no-op fails these tests.
|
|   "no user row created" — the package suite has no consumer users table (see
|               the note at the top of AuthFeatureGatingTest), so the terminal
|               handler the middleware protects increments a counter instead.
|               Counter still 0 == the User::create() side was never reached.
|
| The password.email branch of the same listener is deliberately NOT driven
| here: it reads App\Models\Setting, and tests/Feature/Auth runs on the
| DB-less TestCase.
|
*/

use App\Providers\FortifyServiceProvider;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Features;
use Lvntr\StarterKit\Bootstrap;
use Lvntr\StarterKit\Http\Middleware\ValidateTurnstile;
use Lvntr\StarterKit\Rules\TurnstileRule;

// The package suite does not autoload App\, so the stub provider under test is
// pulled in by path (same guarded pattern as AuthFeatureGatingTest — whichever
// file loads first wins, the guard keeps the second one from redeclaring).
if (! class_exists(FortifyServiceProvider::class)) {
    require_once dirname(__DIR__, 3).'/stubs/app/Providers/FortifyServiceProvider.php';
}

/**
 * Stand-in for the User::create() the register endpoint would reach.
 */
final class RegisterTurnstileProbe
{
    public static int $reached = 0;
}

/**
 * Load Fortify's real route file into a throwaway Router, wrapped in the same
 * group its own provider wraps it in (domain + `fortify.prefix`), and return
 * the route registered under $name.
 */
function skFortifyRoute(string $name): RoutingRoute
{
    $previous = app()->bound('router') ? app('router') : null;

    $router = new Router(app('events'), app());

    app()->instance('router', $router);
    Facade::clearResolvedInstance('router');

    try {
        Route::group([
            'namespace' => 'Laravel\Fortify\Http\Controllers',
            'domain' => config('fortify.domain'),
            'prefix' => config('fortify.prefix'),
        ], function (): void {
            require dirname(__DIR__, 3).'/vendor/laravel/fortify/routes/routes.php';
        });

        foreach ($router->getRoutes() as $route) {
            if ($route->getName() === $name) {
                return $route;
            }
        }

        throw new RuntimeException(
            "Fortify registered no route named [{$name}] — the gate below cannot run, "
            .'treat this as a failure, not as a fixture nit.'
        );
    } finally {
        if ($previous !== null) {
            app()->instance('router', $previous);
        }

        Facade::clearResolvedInstance('router');
    }
}

/**
 * Fire the real RouteMatched event at $route and return the middleware the
 * route carries afterwards.
 *
 * @return list<string>
 */
function skFireRouteMatched(RoutingRoute $route, string $method, string $uri): array
{
    $request = Request::create($uri, $method);
    $request->setRouteResolver(fn () => $route);

    event(new RouteMatched($route, $request));

    return array_values($route->middleware());
}

/**
 * The class the kit's own bootstrap binds to the `turnstile` alias.
 */
function skTurnstileAlias(): string
{
    $middleware = new Middleware;

    Bootstrap::middleware($middleware);

    $aliases = $middleware->getMiddlewareAliases();

    expect($aliases)->toHaveKey('turnstile');

    return $aliases['turnstile'];
}

/**
 * Register a probe endpoint behind the real `turnstile` alias. The closure
 * stands in for the account-creating tail of POST /register.
 */
function skRegisterProbe(): void
{
    Route::aliasMiddleware('turnstile', skTurnstileAlias());

    Route::middleware(['turnstile'])->post('/sk-register-probe', function (): string {
        RegisterTurnstileProbe::$reached++;

        return 'created';
    });

    Route::getRoutes()->refreshNameLookups();
}

beforeEach(function (): void {
    RegisterTurnstileProbe::$reached = 0;

    config([
        // Registration must be ON or Fortify never binds the route at all.
        'fortify.features' => [Features::registration(), Features::resetPasswords()],
        'fortify.prefix' => null,
        'fortify.paths' => [],
        'services.turnstile.enabled' => true,
        'services.turnstile.site_key' => 'SYNTHETIC-PLACEHOLDER-site-key',
        'services.turnstile.secret_key' => 'SYNTHETIC-PLACEHOLDER-secret-key',
        'services.turnstile.verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
    ]);

    // Installs the Route::matched listener under test.
    new FortifyServiceProvider($this->app)->boot();
});

// ──────────────────────────────────────────────────────────────────────────────
// 1. Wiring — the gate reaches Fortify's own register POST
// ──────────────────────────────────────────────────────────────────────────────

it('resolves the turnstile alias to the package middleware', function (): void {
    expect(skTurnstileAlias())->toBe(ValidateTurnstile::class);
});

it('attaches throttle and turnstile to the Fortify register POST', function (): void {
    $route = skFortifyRoute('register.store');

    expect($route->uri())->toBe('register');

    $middleware = skFireRouteMatched($route, 'POST', '/register');

    expect($middleware)->toContain('turnstile');
    expect($middleware)->toContain('throttle:5,1');

    // Throttle FIRST: turnstile costs one outbound Cloudflare verification per
    // request, so an unthrottled flood would amplify into Cloudflare traffic.
    expect(array_search('throttle:5,1', $middleware, true))
        ->toBeLessThan(array_search('turnstile', $middleware, true));
});

it('still gates the register POST under a custom fortify prefix and path', function (): void {
    config(['fortify.prefix' => 'accounts', 'fortify.paths' => ['register' => '/sign-up']]);

    $route = skFortifyRoute('register.store');

    // Path matching (`$request->is('register')`) would miss this route
    // entirely; name matching does not.
    expect($route->uri())->toBe('accounts/sign-up');

    expect(skFireRouteMatched($route, 'POST', '/accounts/sign-up'))->toContain('turnstile');
});

it('leaves the register view route ungated', function (): void {
    $middleware = skFireRouteMatched(skFortifyRoute('register'), 'GET', '/register');

    expect($middleware)->not->toContain('turnstile');
    expect($middleware)->not->toContain('throttle:5,1');
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. Behaviour — enabled Turnstile closes the door
// ──────────────────────────────────────────────────────────────────────────────

it('rejects a register POST that omits the turnstile field entirely', function (): void {
    Http::fake();
    skRegisterProbe();

    $this->postJson('/sk-register-probe', ['email' => 'ada@example.test'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('cf_turnstile_response');

    expect(RegisterTurnstileProbe::$reached)->toBe(0);

    // An absent token never reaches Cloudflare, so the missing-field path
    // cannot be used to amplify traffic at the verify endpoint.
    Http::assertNothingSent();
});

it('rejects a register POST carrying an empty turnstile field', function (): void {
    Http::fake();
    skRegisterProbe();

    $this->postJson('/sk-register-probe', ['cf_turnstile_response' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('cf_turnstile_response');

    expect(RegisterTurnstileProbe::$reached)->toBe(0);
    Http::assertNothingSent();
});

it('rejects a register POST whose token Cloudflare refuses', function (): void {
    Http::fake([
        'challenges.cloudflare.com/*' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ]),
    ]);

    skRegisterProbe();

    $this->postJson('/sk-register-probe', ['cf_turnstile_response' => 'SYNTHETIC-PLACEHOLDER-token'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('cf_turnstile_response');

    expect(RegisterTurnstileProbe::$reached)->toBe(0);
    Http::assertSentCount(1);
});

it('lets a register POST through when Cloudflare accepts the token', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

    skRegisterProbe();

    $this->postJson('/sk-register-probe', ['cf_turnstile_response' => 'SYNTHETIC-PLACEHOLDER-token'])
        ->assertOk();

    expect(RegisterTurnstileProbe::$reached)->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. Disabled Turnstile — the gate must stay fully open
// ──────────────────────────────────────────────────────────────────────────────

it('lets a register POST with no turnstile field through while turnstile is disabled', function (): void {
    config(['services.turnstile.enabled' => false]);

    Http::fake();
    skRegisterProbe();

    $this->postJson('/sk-register-probe', ['email' => 'ada@example.test'])->assertOk();

    expect(RegisterTurnstileProbe::$reached)->toBe(1);
    Http::assertNothingSent();
});

it('attaches the middleware even while turnstile is disabled, because it no-ops', function (): void {
    config(['services.turnstile.enabled' => false]);

    // The attach is unconditional on purpose: it must not depend on a config
    // value that SettingsServiceProvider can flip after boot.
    expect(skFireRouteMatched(skFortifyRoute('register.store'), 'POST', '/register'))
        ->toContain('turnstile');
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. Why the middleware is required at all
// ──────────────────────────────────────────────────────────────────────────────

it('pins the finding: the CreateNewUser rule alone never fires on an absent or empty field', function (): void {
    Http::fake();

    // Turnstile is ENABLED for both of these. Both pass — which is precisely
    // the hole the middleware closes. Delete the middleware attach and the
    // behaviour tests above go red while this one stays green.
    expect(Validator::make([], ['cf_turnstile_response' => [new TurnstileRule]])->passes())->toBeTrue();
    expect(Validator::make(['cf_turnstile_response' => ''], ['cf_turnstile_response' => [new TurnstileRule]])->passes())->toBeTrue();

    Http::assertNothingSent();
});

// ──────────────────────────────────────────────────────────────────────────────
// 5. The token is spent EXACTLY once
// ──────────────────────────────────────────────────────────────────────────────

it('leaves no second Turnstile verification inside CreateNewUser', function (): void {
    // A Cloudflare token is SINGLE-USE. The middleware spends it against
    // siteverify before Fortify reaches CreatesNewUsers, so a second
    // TurnstileRule entry in that action's validator would come back
    // `timeout-or-duplicate` and reject every LEGITIMATE registration —
    // the CAPTCHA would look enforced while quietly breaking signup.
    $source = file_get_contents(__DIR__.'/../../../stubs/app/Actions/Fortify/CreateNewUser.php');

    expect($source)->not->toContain('new TurnstileRule');
    expect($source)->not->toContain("'cf_turnstile_response' =>");
});
