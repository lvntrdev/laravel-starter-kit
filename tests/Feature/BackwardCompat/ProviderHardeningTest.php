<?php

/*
|--------------------------------------------------------------------------
| Provider + helper hardening pins
|--------------------------------------------------------------------------
|
| Pins the Task 3 hardening contract: the kit's opinionated global mutations
| now sit behind config flags whose DEFAULTS must preserve historical
| behaviour, and two small correctness fixes (format_date timezone fallback +
| the empty 204 ApiResponse body). These are surface/behaviour assertions and
| need no database, so they ride the lightweight BackwardCompat TestCase.
|
*/

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lvntr\StarterKit\Http\Responses\ApiResponse;

it('defaults starter-kit.strict_models to true so strict mode stays enabled', function (): void {
    // Escape hatch for the opinionated Model::shouldBeStrict() mutation — the
    // default must keep the kit's historical (strict-outside-production)
    // behaviour, so consumers who never touch config see no change.
    expect(config('starter-kit.strict_models'))->toBeTrue();
});

it('defaults the passport api guard provider to users', function (): void {
    // The auto-synthesised `api` guard provider is now configurable; the
    // default must remain `users` so existing installs keep resolving the
    // same auth provider.
    expect(config('starter-kit.passport.provider'))->toBe('users');
});

it('falls back to the app timezone in format_date when display_timezone is unset', function (): void {
    // Bare installs (or pre-seed boot) have no app.display_timezone. The
    // helper must fall back to app.timezone (then UTC) instead of passing
    // null to setTimezone(), which would throw.
    config(['app.display_timezone' => null, 'app.timezone' => 'Europe/Istanbul']);

    // 05:36 UTC shifted into Europe/Istanbul (+03:00) → 08:36, proving the
    // fallback timezone was actually applied rather than silently ignored.
    $formatted = format_date(Carbon::create(2026, 3, 14, 5, 36, 0, 'UTC'));

    expect($formatted)->toBe('14-03-2026 08:36');
});

it('returns a genuinely empty 204 body from to_api and ApiResponse::noContent', function (): void {
    $viaHelper = to_api(status: 204);
    $viaFactory = ApiResponse::noContent();

    expect($viaHelper)->toBeInstanceOf(JsonResponse::class)
        ->and($viaHelper->getStatusCode())->toBe(204)
        ->and($viaFactory)->toBeInstanceOf(JsonResponse::class)
        ->and($viaFactory->getStatusCode())->toBe(204);

    // Symfony empties the body of a 204 during prepare(); assert the wire
    // representation is truly empty, not the JSON string `""`.
    $viaFactory->prepare(Request::create('/'));

    expect($viaFactory->getContent())->toBe('');
});
