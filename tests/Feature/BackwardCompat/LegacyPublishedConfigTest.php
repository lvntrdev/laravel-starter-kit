<?php

/*
|--------------------------------------------------------------------------
| Legacy published config × current runtime — golden scenarios
|--------------------------------------------------------------------------
|
| ConfigAdditiveMergeTest asserts that the SHIPPED config still carries the
| keys consumers depend on. It cannot catch the failure that actually costs an
| installation: a `config/starter-kit.php` published by an OLDER version, kept
| verbatim, running against today's package.
|
| That gap is real because `mergeConfigFrom` merges only the TOP level. When
| the consumer's file has a `permissions` array, the package's `permissions`
| array is not consulted at all — every key the consumer's copy lacks reads as
| absent, not as the package default. The runtime has to survive that.
|
| The scenarios below pin the guarantee the kit sells to existing installs:
| upgrading the package alone must not change who gets a 403. They use the
| UNRESOLVED axis on purpose — it is the newest policy and the one a fresh
| install now diverges on (its .env seeds false while the vendor default stays
| true, see CheckResourcePermission::ALLOW_UNRESOLVED_DEFAULT), so it is where
| a silent regression would land first, and it decides without touching the
| database.
|
*/

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Lvntr\StarterKit\Http\Middleware\CheckResourcePermission;

/**
 * The `permissions` array exactly as v13.6.14 shipped it: `allow_unmapped` and
 * nothing else. Both keys this release added are absent, which is what a
 * consumer who ran `sk:publish --tag=config` before the upgrade still has.
 */
function legacyPermissionsConfig(): array
{
    return ['allow_unmapped' => false];
}

function legacyConfigRequest(?string $routeName): Request
{
    $request = Request::create('/admin/anything', 'GET');

    $route = new Route(['GET'], '/admin/anything', ['uses' => fn () => null]);

    if ($routeName !== null) {
        $route->name($routeName);
    }

    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    return $request;
}

function runWithLegacyConfig(Request $request): Response
{
    return (new CheckResourcePermission)->handle(
        $request,
        fn (): Response => new Response('ok')
    );
}

beforeEach(function (): void {
    // The unresolved-route warning is throttled per route name for the request
    // lifecycle; without this a later scenario reusing a name would silently
    // take a different branch than the one under test.
    CheckResourcePermission::flushCache();
});

it('leaves an unresolved route passing when the published config predates allow_unresolved', function (): void {
    config()->set('starter-kit.permissions', legacyPermissionsConfig());

    // Single segment → nothing to derive a permission from. On v13.6.14 this
    // passed in silence; it must still pass, warning included.
    $response = runWithLegacyConfig(legacyConfigRequest('dashboard'));

    expect($response->getStatusCode())->toBe(200);
});

it('still exempts the package unrestricted routes when unrestricted_routes is absent', function (): void {
    config()->set('starter-kit.permissions', legacyPermissionsConfig());

    // The consumer-owned pattern list is missing entirely. The package's own
    // exempt names live in code, so the union must degrade to "package only"
    // rather than blowing up on a null pattern list.
    $response = runWithLegacyConfig(legacyConfigRequest('system-health.run'));

    expect($response->getStatusCode())->toBe(200);
});

it('denies an unresolved route once the operator adds allow_unresolved => false to the legacy array', function (): void {
    config()->set('starter-kit.permissions', legacyPermissionsConfig() + ['allow_unresolved' => false]);

    // The early opt-in path documented in UPGRADE.md: strictness is available
    // on 13.x today, it is just never imposed.
    expect(fn () => runWithLegacyConfig(legacyConfigRequest('dashboard')))
        ->toThrow(AuthorizationException::class);
});

it('resolves the permission for a mapped route name identically under a legacy config', function (): void {
    config()->set('starter-kit.permissions', legacyPermissionsConfig());

    $route = new Route(['GET'], '/admin/users', ['uses' => fn () => null]);
    $route->name('admin.users.index');

    // Derivation is pure and must not consult the permissions array at all —
    // a missing key can only affect what happens AFTER a permission resolves.
    expect(CheckResourcePermission::resolutionFor($route))->toBe('users.read');
});

it('ships allow_unresolved as an explicit true for a fresh install', function (): void {
    // A fresh install reads the package file, so the key is present rather than
    // falling back. Its value comes from the constant, keeping one source of
    // truth for the value every install without an explicit key falls back to.
    $config = require dirname(__DIR__, 3).'/config/starter-kit.php';

    expect($config['permissions'])->toHaveKey('allow_unresolved')
        ->and($config['permissions']['allow_unresolved'])->toBeTrue()
        ->and($config['permissions'])->toHaveKey('unrestricted_routes')
        ->and($config['permissions']['unrestricted_routes'])->toBe([]);
});

it('keeps the fallback default permissive so no release denies on its own', function (): void {
    // The guard on the constant itself. It reaches every install on a plain
    // `composer update` — including ones whose published config predates the
    // key — so a release that turns it false silently 403s live apps that
    // changed nothing. A fresh project gets strictness from its seeded .env
    // instead (tests/Feature/Install/EnvMergeTest), which is the only place the
    // two populations can legitimately diverge. If this assertion is ever
    // "fixed" by flipping the constant, docs/UPGRADE.md is wrong too.
    expect(CheckResourcePermission::ALLOW_UNRESOLVED_DEFAULT)->toBeTrue();
});

it('follows config rather than env, so a cached config decides', function (): void {
    // `config:cache` freezes values and stops loading .env — env() then returns
    // null. Setting only config here mirrors that state: the middleware must
    // reach its decision without consulting the environment.
    config()->set('starter-kit.permissions', legacyPermissionsConfig() + ['allow_unresolved' => false]);
    putenv('STARTER_KIT_ALLOW_UNRESOLVED_ROUTES=true');

    try {
        expect(fn () => runWithLegacyConfig(legacyConfigRequest('dashboard')))
            ->toThrow(AuthorizationException::class);
    } finally {
        putenv('STARTER_KIT_ALLOW_UNRESOLVED_ROUTES');
    }
});
