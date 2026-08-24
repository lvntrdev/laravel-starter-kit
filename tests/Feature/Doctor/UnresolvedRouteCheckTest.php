<?php

/*
|--------------------------------------------------------------------------
| UnresolvedRouteCheck Tests
|--------------------------------------------------------------------------
|
| CheckResourcePermission's UNRESOLVED axis (no permission could be derived
| from a route name at all) currently allows the request through with a
| throttled warning; `allow_unresolved` is scheduled to flip to deny by
| default. This check is the pre-flip visibility: it walks the registered
| route table with the same resolutionFor() rule the middleware itself uses
| and reports every route that would start denying.
|
| Testbench boots a fresh application per test, so routes registered here
| never leak between tests — each test defines its own route table.
|
*/

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Lvntr\StarterKit\Console\Doctor\Checks\UnresolvedRouteCheck;

it('passes when every check.permission-gated route resolves to a permission', function (): void {
    Route::get('/admin/users', fn () => 'ok')->name('admin.users.index')->middleware('check.permission');
    Route::post('/admin/users', fn () => 'ok')->name('admin.users.store')->middleware('check.resource.permission');

    $report = (new UnresolvedRouteCheck)->run();

    expect($report->isOk())->toBeTrue();
});

it('reports a nameless route gated by check.permission', function (): void {
    Route::get('/admin/x', fn () => 'ok')->middleware('check.permission');

    $report = (new UnresolvedRouteCheck)->run();

    expect($report->isFail())->toBeTrue()
        ->and($report->message)->toContain('GET /admin/x')
        ->and($report->message)->toContain('currently pass with a warning')
        ->and($report->hint)->toContain('DENIED');
});

it('reports a route whose name has fewer than two segments', function (): void {
    Route::get('/dashboard', fn () => 'ok')->name('dashboard')->middleware('check.permission');

    $report = (new UnresolvedRouteCheck)->run();

    expect($report->isFail())->toBeTrue()
        ->and($report->message)->toContain('dashboard');
});

it('reports a route whose action segment has no mapped ability', function (): void {
    Route::post('/admin/users/reorder', fn () => 'ok')->name('admin.users.reorder')->middleware('check.permission');

    $report = (new UnresolvedRouteCheck)->run();

    expect($report->isFail())->toBeTrue()
        ->and($report->message)->toContain('admin.users.reorder');
});

it('never reports a route with an explicit permission argument', function (): void {
    Route::get('/admin/dashboard', fn () => 'ok')->name('dashboard')->middleware('check.permission:reports.read');

    $report = (new UnresolvedRouteCheck)->run();

    expect($report->isOk())->toBeTrue();
});

it('never reports a route declared under unrestricted_routes', function (): void {
    config(['starter-kit.permissions.unrestricted_routes' => ['dashboard']]);

    Route::get('/dashboard', fn () => 'ok')->name('dashboard')->middleware('check.permission');

    $report = (new UnresolvedRouteCheck)->run();

    expect($report->isOk())->toBeTrue();
});

it('ignores routes that carry no permission middleware at all', function (): void {
    Route::get('/dashboard', fn () => 'ok')->name('dashboard');

    $report = (new UnresolvedRouteCheck)->run();

    expect($report->isOk())->toBeTrue();
});

it('fails regardless of the current allow_unresolved value', function (): void {
    config(['starter-kit.permissions.allow_unresolved' => false]);

    Route::get('/dashboard', fn () => 'ok')->name('dashboard')->middleware('check.permission');

    $report = (new UnresolvedRouteCheck)->run();

    // The flag controls RUNTIME behavior only — the doctor report still names
    // the gap so the consumer is not surprised when the flip lands.
    expect($report->isFail())->toBeTrue();
});

it('is addressable via --only=unresolved-routes', function (): void {
    Route::get('/dashboard', fn () => 'ok')->name('dashboard')->middleware('check.permission');

    Artisan::call('sk:doctor', ['--json' => true, '--only' => 'unresolved-routes']);
    $decoded = json_decode(Artisan::output(), true);

    expect($decoded['checks'])->toHaveCount(1)
        ->and($decoded['checks'][0]['name'])->toBe('Unresolved Routes')
        ->and($decoded['checks'][0]['status'])->toBe('fail');
});
