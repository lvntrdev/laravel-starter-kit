<?php

/*
|--------------------------------------------------------------------------
| Backward Compatibility — FileManager Route Names + URLs
|--------------------------------------------------------------------------
|
| Frontend Wayfinder generates typed route helpers from the route names
| and URI patterns shipped by the package. Any rename or URI shift would
| silently break every typed route call in the consumer app. This test
| pins both surfaces against the v13.4.x snapshot.
|
| Strategy:
|   - Force the package ServiceProvider to mount the vendor route file
|     by registering a manual `web` middleware group and including
|     src/routes/file-manager.php — Testbench's bare consumer skeleton
|     is treated as "no consumer route file present", so the package
|     would normally auto-mount under `web + auth + verified`. We mount
|     under plain `web` here to skip auth without altering route names.
|   - For each fixture entry, assert Route::has() resolves and that the
|     materialised URL matches the v13.4.x pattern.
|
*/

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    // Mount the vendor route file under a clean web group so route names
    // resolve without auth middleware getting in the way.
    Route::middleware('web')->group(function (): void {
        require dirname(__DIR__, 3).'/src/routes/file-manager.php';
    });
});

it('registers every v13.4.x route name', function (): void {
    $expected = require dirname(__DIR__, 2).'/fixtures/v13.4.x-route-names.php';

    expect($expected)->toBeArray()->and($expected)->not->toBeEmpty();

    foreach ($expected as $name) {
        expect(Route::has($name))->toBeTrue(
            "Route name `{$name}` is missing — Wayfinder typed routes will break."
        );
    }
});

it('preserves the v13.4.x URL prefix and parameter names', function (): void {
    // Sample of the URL contract — kept short on purpose to stay
    // review-friendly; full name list is covered by the previous test.
    $expectations = [
        'file-manager.tree' => '/file-manager/tree',
        'file-manager.contents' => '/file-manager/contents',
        'file-manager.favorites.contents' => '/file-manager/favorites/contents',
        'file-manager.favorites.add' => '/file-manager/favorites',
        'file-manager.favorites.remove' => '/file-manager/favorites',
        'file-manager.trash.contents' => '/file-manager/trash/contents',
        'file-manager.trash.empty' => '/file-manager/trash/empty',
        'file-manager.items.restore' => '/file-manager/items/restore',
        'file-manager.items.permanent' => '/file-manager/items/permanent',
        'file-manager.items.move' => '/file-manager/items/move',
        'file-manager.items.bulkDelete' => '/file-manager/items/bulk-delete',
        'file-manager.folders.store' => '/file-manager/folders',
    ];

    foreach ($expectations as $name => $expectedUrl) {
        $actualUrl = route($name, [], false);
        expect($actualUrl)->toBe(
            $expectedUrl,
            "Route URL drifted for `{$name}` — frontend axios calls keyed on this path will 404. Got: {$actualUrl}"
        );
    }
});

it('preserves URL patterns for parameterised routes', function (): void {
    // Use placeholder ids; we only care that the URL template carries
    // the right parameter name in the right position.
    expect(route('file-manager.folders.rename', ['folder' => 42], false))
        ->toBe('/file-manager/folders/42');

    expect(route('file-manager.folders.destroy', ['folder' => 42], false))
        ->toBe('/file-manager/folders/42');

    expect(route('file-manager.files.rename', ['media' => 7], false))
        ->toBe('/file-manager/files/7');

    expect(route('file-manager.files.copy', ['media' => 7], false))
        ->toBe('/file-manager/files/7/copy');

    expect(route('file-manager.files.destroy', ['media' => 7], false))
        ->toBe('/file-manager/files/7');

    expect(route('file-manager.files.download', ['media' => 7], false))
        ->toBe('/file-manager/files/7/download');
});

it('preserves HTTP methods for each route', function (): void {
    // Verb drift is just as breaking as URL drift — capture the
    // canonical method per route from the v13.4.x snapshot.
    $expectations = [
        'GET' => [
            'file-manager.tree',
            'file-manager.contents',
            'file-manager.favorites.contents',
            'file-manager.trash.contents',
        ],
        'POST' => [
            'file-manager.favorites.add',
            'file-manager.folders.store',
            'file-manager.items.bulkDelete',
            'file-manager.items.restore',
            'file-manager.files.upload',
        ],
        'DELETE' => [
            'file-manager.favorites.remove',
            'file-manager.trash.empty',
            'file-manager.items.permanent',
            'file-manager.folders.destroy',
            'file-manager.files.destroy',
        ],
        'PATCH' => [
            'file-manager.folders.rename',
            'file-manager.items.move',
            'file-manager.files.rename',
        ],
    ];

    foreach ($expectations as $method => $names) {
        foreach ($names as $name) {
            $route = Route::getRoutes()->getByName($name);
            expect($route)->not->toBeNull("Route `{$name}` not registered");

            $methods = $route->methods();
            expect(in_array($method, $methods, true))->toBeTrue(
                "Route `{$name}` no longer accepts {$method} — frontend calls will get 405. Got: ".implode(',', $methods)
            );
        }
    }
});
