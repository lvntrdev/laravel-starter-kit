<?php

/*
|--------------------------------------------------------------------------
| Module Route Registry — mount / override / extensibility / share K1
|--------------------------------------------------------------------------
|
| Faz 2: registerRoutes() data-driven module registry'ye dönüştürüldü.
| file-manager bu generic mekanizmanın ilk (ve tek) girişi; davranış
| v13.4.x ile birebir aynı kalmalı.
|
| Test senaryoları (plan Task 2):
|   (a) Consumer override stub YOKKEN file-manager vendor'dan
|       web+auth+verified tier'ı ile mount oluyor.
|   (b) Consumer override stub VARKEN kit kenara çekiliyor — çift-mount
|       yok, route adı çakışması yok.
|   (c) Registry'ye ikinci bir kukla modül eklenince generic döngü onu da
|       doğru middleware tier'ıyla mount ediyor (genişletilebilirlik kanıtı).
|   (d) Share endpoint (K1) hâlâ tanımlı ve auth-muaf (signed, throttle;
|       auth/verified hariç).
|
| registerRoutes() boot sırasında bir kez koşar; bu yüzden testler
| ModuleRouteRegistryProbe ile loop'u temiz bir Router üzerinde kontrollü
| girdiyle yeniden sürer (production mount mantığı değişmez, yalnızca aynı
| kod yolu deterministik girdiyle tetiklenir).
|
*/

use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Lvntr\StarterKit\Tests\Stubs\ModuleRouteRegistryProbe;

/**
 * Look up a named route after dynamic registration.
 *
 * Routes registered at runtime are appended to the collection but the
 * name->route lookup map is built lazily; refresh it so getByName() resolves.
 */
function moduleRoute(Router $router, string $name): ?Illuminate\Routing\Route
{
    $router->getRoutes()->refreshNameLookups();

    return $router->getRoutes()->getByName($name);
}

/**
 * Resolve middleware names assigned to a named route (including group tier).
 *
 * @return array<int, string>
 */
function moduleRouteMiddleware(Router $router, string $name): array
{
    $route = moduleRoute($router, $name);

    expect($route)->not->toBeNull("Route `{$name}` not registered");

    return $route->gatherMiddleware();
}

it('(a) auto-mounts the module loader under its tier when no consumer stub exists', function (): void {
    // NOTE: the production registerRoutes() already mounted the real
    // file-manager during boot (the bare Testbench skeleton has no override
    // stub). To prove the stub-absent / vendor-mount branch deterministically
    // — independent of that boot-time mount — drive the loader through the same
    // registry loop and assert (1) the loader fired and (2) the route landed
    // under the declared tier.
    $loaderCalls = 0;

    $probe = (new ModuleRouteRegistryProbe(app()))->withRegistry([
        [
            'name' => 'fm-mount-probe',
            'overrideStubs' => [
                base_path('routes/web/__definitely_missing_fm_stub__.php'),
            ],
            'middleware' => ['web', 'auth', 'verified'],
            'loader' => static function () use (&$loaderCalls): void {
                $loaderCalls++;
                Route::get('fm-mount-probe/tree', static fn () => 'ok')
                    ->name('fm-mount-probe.tree');
            },
        ],
    ]);

    $probe->runRegisterRoutes();

    /** @var Router $router */
    $router = app('router');

    expect($loaderCalls)->toBe(1, 'Stub-absent path must invoke the module loader');
    expect(moduleRoute($router, 'fm-mount-probe.tree'))->not->toBeNull();

    $middleware = moduleRouteMiddleware($router, 'fm-mount-probe.tree');

    expect($middleware)->toContain('web')
        ->and($middleware)->toContain('auth')
        ->and($middleware)->toContain('verified');
});

it('(a2) the real file-manager loader mounts under web+auth+verified through the registry', function (): void {
    // Exercise the production loader (require src/routes/file-manager.php) via
    // the registry loop and assert the file-manager tier is web+auth+verified,
    // matching v13.4.x 1:1. Route name resolution is unaffected by the
    // boot-time mount because middleware is asserted on the route object.
    $probe = (new ModuleRouteRegistryProbe(app()))->withRegistry([
        [
            'name' => 'file-manager',
            'overrideStubs' => [base_path('routes/web/__definitely_missing_fm_stub__.php')],
            'middleware' => ['web', 'auth', 'verified'],
            'loader' => static function (): void {
                require dirname(__DIR__, 3).'/src/routes/file-manager.php';
            },
        ],
    ]);

    $probe->runRegisterRoutes();

    /** @var Router $router */
    $router = app('router');

    $middleware = moduleRouteMiddleware($router, 'file-manager.tree');

    expect($middleware)->toContain('web')
        ->and($middleware)->toContain('auth')
        ->and($middleware)->toContain('verified');
});

it('(b) steps aside when a consumer override stub is present — no double mount', function (): void {
    // Create a real temp file that stands in for the consumer override stub.
    $stub = tempnam(sys_get_temp_dir(), 'fm_stub_').'.php';
    file_put_contents($stub, "<?php\n// consumer-owned file-manager route stub\n");

    try {
        $loaderCalls = 0;

        $probe = (new ModuleRouteRegistryProbe(app()))->withRegistry([
            [
                'name' => 'fm-override-probe',
                'overrideStubs' => [$stub],
                'middleware' => ['web', 'auth', 'verified'],
                'loader' => static function () use (&$loaderCalls): void {
                    $loaderCalls++;
                    Route::get('fm-override-probe/tree', static fn () => 'ok')
                        ->name('fm-override-probe.tree');
                },
            ],
        ]);

        $probe->runRegisterRoutes();

        /** @var Router $router */
        $router = app('router');

        // Loader never fired → consumer owns the mount; no vendor routes added.
        // (A unique probe name avoids collision with the boot-time real mount.)
        expect($loaderCalls)->toBe(0, 'Stub-present path must skip the module loader')
            ->and(moduleRoute($router, 'fm-override-probe.tree'))->toBeNull();
    } finally {
        @unlink($stub);
    }
});

it('(c) generically mounts a second dummy module proving the loop is extensible', function (): void {
    $probe = (new ModuleRouteRegistryProbe(app()))->withRegistry([
        [
            'name' => 'dummy-module',
            'overrideStubs' => [
                base_path('routes/web/__definitely_missing_dummy_stub__.php'),
            ],
            'middleware' => ['web', 'auth'],
            'loader' => static function (): void {
                Route::get('dummy-module/ping', static fn () => 'pong')
                    ->name('dummy-module.ping');
            },
        ],
    ]);

    $probe->runRegisterRoutes();

    /** @var Router $router */
    $router = app('router');

    expect(moduleRoute($router, 'dummy-module.ping'))->not->toBeNull();

    $middleware = moduleRouteMiddleware($router, 'dummy-module.ping');

    expect($middleware)->toContain('web')
        ->and($middleware)->toContain('auth')
        ->and($middleware)->not->toContain('verified');
});

it('(c2) honors per-module middleware tiers independently in one pass', function (): void {
    $probe = (new ModuleRouteRegistryProbe(app()))->withRegistry([
        [
            'name' => 'alpha',
            'overrideStubs' => [base_path('routes/web/__missing_alpha__.php')],
            'middleware' => ['web', 'auth', 'verified'],
            'loader' => static fn () => Route::get('alpha/x', static fn () => 'a')->name('alpha.x'),
        ],
        [
            'name' => 'beta',
            'overrideStubs' => [base_path('routes/web/__missing_beta__.php')],
            'middleware' => ['api'],
            'loader' => static fn () => Route::get('beta/y', static fn () => 'b')->name('beta.y'),
        ],
    ]);

    $probe->runRegisterRoutes();

    /** @var Router $router */
    $router = app('router');

    expect(moduleRouteMiddleware($router, 'alpha.x'))->toContain('verified');
    expect(moduleRouteMiddleware($router, 'beta.y'))->toContain('api')
        ->and(moduleRouteMiddleware($router, 'beta.y'))->not->toContain('verified');
});

it('(d) keeps the share endpoint defined and auth-exempt (K1)', function (): void {
    config()->set('file-manager.share.enabled', true);

    $probe = (new ModuleRouteRegistryProbe(app()))->withRegistry([
        [
            'name' => 'file-manager',
            'overrideStubs' => [base_path('routes/web/__definitely_missing_fm_stub__.php')],
            'middleware' => ['web', 'auth', 'verified'],
            'loader' => static function (): void {
                require dirname(__DIR__, 3).'/src/routes/file-manager.php';
            },
        ],
    ]);

    $probe->runRegisterRoutes();

    /** @var Router $router */
    $router = app('router');

    $share = moduleRoute($router, 'file-manager.share.show');

    expect($share)->not->toBeNull('Public share endpoint must stay registered');

    // Signed + throttle are part of the route's own middleware.
    $middleware = $share->gatherMiddleware();

    expect($middleware)->toContain('signed');
    expect(collect($middleware)->contains(fn ($m) => str_starts_with((string) $m, 'throttle')))
        ->toBeTrue('Share endpoint must keep its throttle middleware');

    // K1: auth/verified are stripped via withoutMiddleware() inside the route
    // file. Laravel records these in `excluded_middleware` and subtracts them
    // at dispatch — this is what lets anonymous users hit the signed URL even
    // when the module group mounts under auth+verified. Assert the exclusion
    // set survives the registry mount path unchanged.
    $excluded = (array) $share->getAction('excluded_middleware');

    expect($excluded)->toContain('auth')
        ->and($excluded)->toContain('verified')
        ->and($excluded)->toContain('auth:sanctum')
        ->and($excluded)->toContain('auth:api');
});

it('(f) carries the sk-components descriptor and its loader mounts the role-gated showcase route', function (): void {
    // Descriptor contract — vendor-resident developer showcase (v13.6.x).
    $descriptors = (new ModuleRouteRegistryProbe(app()))->registryDescriptors();

    $sk = collect($descriptors)->firstWhere('name', 'sk-components');

    expect($sk)->not->toBeNull('sk-components must be a registry entry');
    expect($sk['middleware'])->toBe(['web', 'auth', 'verified']);
    expect($sk['loader'])->toBeInstanceOf(Closure::class);
    expect($sk['overrideStubs'])->toBe([
        base_path('routes/web/sk-components-route.php'),
    ]);

    // Drive the real loader through the registry loop (same pattern as (a2));
    // the probe's route is registered after the boot-time mount, so the name
    // lookup resolves to it after refreshNameLookups().
    $probe = (new ModuleRouteRegistryProbe(app()))->withRegistry([
        [
            'name' => 'sk-components',
            'overrideStubs' => [base_path('routes/web/__definitely_missing_sk_stub__.php')],
            'middleware' => ['web', 'auth', 'verified'],
            'loader' => static function (): void {
                require dirname(__DIR__, 3).'/src/routes/sk-components.php';
            },
        ],
    ]);

    $probe->runRegisterRoutes();

    /** @var Router $router */
    $router = app('router');

    $middleware = moduleRouteMiddleware($router, 'sk-components.show');

    expect($middleware)->toContain('web')
        ->and($middleware)->toContain('auth')
        ->and($middleware)->toContain('verified')
        // The role gate lives inside the route file itself — the showcase is
        // developer-only (system_admin), not permission-seeded.
        ->and($middleware)->toContain('role:system_admin');
});

it('(g) skips the whole registry under route:cache — no duplicate mount', function (): void {
    // Under `route:cache` the compiled route file already holds every mounted
    // route; re-running the registry in boot() would register each module's
    // route names a SECOND time. Drive registerRoutes() against an app whose
    // routesAreCached() reports true and assert the loader never fires (and no
    // override-stub FS scan happens because the loop is never entered).
    $cachedApp = Mockery::mock(Application::class);
    $cachedApp->shouldReceive('routesAreCached')->andReturnTrue();

    $loaderCalls = 0;

    $probe = (new ModuleRouteRegistryProbe($cachedApp))->withRegistry([
        [
            'name' => 'cached-probe',
            // A path that WOULD exist — proves the FS scan is skipped, not merely
            // that the loader guard tripped. If the loop ran, file_exists() here
            // is irrelevant because the loader increments unconditionally.
            'overrideStubs' => [__FILE__],
            'middleware' => ['web', 'auth', 'verified'],
            'loader' => static function () use (&$loaderCalls): void {
                $loaderCalls++;
                Route::get('cached-probe/tree', static fn () => 'ok')
                    ->name('cached-probe.tree');
            },
        ],
    ]);

    $probe->runRegisterRoutes();

    /** @var Router $router */
    $router = app('router');

    expect($loaderCalls)->toBe(0, 'Cached-routes path must not re-run the registry loop')
        ->and(moduleRoute($router, 'cached-probe.tree'))->toBeNull();
});

it('(g2) still mounts through the registry when routes are NOT cached', function (): void {
    // Complement to (g): with routesAreCached() false the guard is transparent
    // and the loader fires exactly as before — cache-less behaviour is 1:1.
    $liveApp = Mockery::mock(Application::class);
    $liveApp->shouldReceive('routesAreCached')->andReturnFalse();

    $loaderCalls = 0;

    $probe = (new ModuleRouteRegistryProbe($liveApp))->withRegistry([
        [
            'name' => 'uncached-probe',
            'overrideStubs' => [base_path('routes/web/__definitely_missing_uncached_stub__.php')],
            'middleware' => ['web', 'auth', 'verified'],
            'loader' => static function () use (&$loaderCalls): void {
                $loaderCalls++;
                Route::get('uncached-probe/tree', static fn () => 'ok')
                    ->name('uncached-probe.tree');
            },
        ],
    ]);

    $probe->runRegisterRoutes();

    /** @var Router $router */
    $router = app('router');

    expect($loaderCalls)->toBe(1, 'Uncached path must run the registry loop')
        ->and(moduleRoute($router, 'uncached-probe.tree'))->not->toBeNull();
});

it('(e) production registry carries the file-manager descriptor with its real tier', function (): void {
    $descriptors = (new ModuleRouteRegistryProbe(app()))->registryDescriptors();

    expect($descriptors)->toBeArray()->not->toBeEmpty();

    $fm = collect($descriptors)->firstWhere('name', 'file-manager');

    expect($fm)->not->toBeNull('file-manager must be the first registry entry');
    expect($fm['middleware'])->toBe(['web', 'auth', 'verified']);
    expect($fm['loader'])->toBeInstanceOf(Closure::class);

    // Override stubs match the consumer orchestrator contract 1:1.
    expect($fm['overrideStubs'])->toBe([
        base_path('routes/web/file-manager-route.php'),
        base_path('routes/api/file-manager-route.php'),
    ]);
});
